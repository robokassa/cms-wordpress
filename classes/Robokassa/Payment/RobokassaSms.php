<?php

namespace Robokassa\Payment;

class RobokassaSms {
	const META_PREFIX = '_robokassa_sms_';

	private $robokassa;
	private $dataBase;
	private $phone;
	private $message;
	private $translit;
	private $order_id;
	private $type;
	private static $activeLocks = array();

	public function __construct(RoboDataBase $dataBase, RobokassaPayAPI $roboKassa, $phone, $message, $translit, $order_id, $type) {
		$this->robokassa = $roboKassa;
		$this->dataBase = $dataBase;
		$this->phone = preg_replace('/[^0-9]/', '', (string)$phone);
		$this->message = (string)$message;
		$this->translit = (bool)$translit;
		$this->order_id = (int)$order_id;
		$this->type = (int)$type;
	}

	/** @return bool|null|\WP_Error */
	public function send() {
		if ($this->order_id <= 0 || !in_array($this->type, array(1, 2), true)) {
			return new \WP_Error('robokassa_sms_invalid_order', 'INVALID SMS ORDER', 400);
		}
		$lock = 'robokassa_sms_' . md5((defined('DB_NAME') ? DB_NAME : '') . ':' . robokassa_payment_getDbPrefix() . ':' . $this->order_id . ':' . $this->type);
		if (isset(self::$activeLocks[$lock])) {
			return new \WP_Error('robokassa_sms_busy', 'RETRY LATER', 503);
		}
		$locked = $this->dataBase->getVar('SELECT GET_LOCK(%s, 10)', array($lock));
		if ((string)$locked !== '1') {
			return new \WP_Error('robokassa_sms_busy', 'RETRY LATER', 503);
		}
		self::$activeLocks[$lock] = true;
		$order = null;
		try {
			// читаем состояние только после получения блокировки
			$store = \WC_Data_Store::load('order');
			if (method_exists($store->get_current_class_name(), 'clear_cached_data')) {
				$store->clear_cached_data(array($this->order_id));
			}
			wp_cache_delete($this->order_id, 'posts');
			wp_cache_delete($this->order_id, 'post_meta');
			$order = wc_get_order($this->order_id);
			if (!$order instanceof \WC_Order) {
				return new \WP_Error('robokassa_sms_order_missing', 'ORDER NOT FOUND', 404);
			}
			$order->read_meta_data(true);
			$state = $order->get_meta(self::META_PREFIX . $this->type, true);
			if ($state === '') {
				$state = $this->readPreviousAttempt($order);
				if ($state !== null) {
					$this->saveState($order, $state);
				}
			}
			if ($state !== null && $state !== '') {
				if (!is_array($state) || ($state['version'] ?? null) !== 1
					|| !in_array($state['status'] ?? '', array('attempted', 'sent', 'failed', 'uncertain'), true)) {
					throw new \RuntimeException('invalid sms state');
				}
				if ($state['status'] === 'attempted') {
					$state['status'] = 'uncertain';
					$this->saveState($order, $state);
				}
				return $this->finish($order, $state);
			}

			$this->message = $this->filterSms($order);
			$this->message = $this->transliterate();
			if ($this->phone === '' || trim($this->message) === '') {
				$this->warn($order, 'не отправлена: проверьте номер телефона и текст сообщения');
				return new \WP_Error('robokassa_sms_invalid_message', 'INVALID SMS MESSAGE', 400);
			}
			// сохраняем попытку до запроса, чтобы не повторить неизвестный результат
			$state = array('version' => 1, 'status' => 'attempted', 'attempted_at' => time());
			$this->saveState($order, $state);
			if ((string)$this->dataBase->getVar('SELECT IS_USED_LOCK(%s) = CONNECTION_ID()', array($lock)) !== '1') {
				throw new \RuntimeException('sms lock was lost');
			}
			try {
				$result = $this->robokassa->sendSms($this->phone, $this->message);
				$state['status'] = $result === true ? 'sent' : ($result === false ? 'failed' : 'uncertain');
			} catch (\Throwable $error) {
				$state['status'] = 'uncertain';
			}
			$state['finished_at'] = time();
			$this->saveState($order, $state);
			return $this->finish($order, $state);
		} catch (\Throwable $error) {
			if ($order instanceof \WC_Order) {
				try {
					$this->warn($order, 'результат отправки не подтверждён. Проверьте доставку перед ручной отправкой. Автоматический повтор отключён');
				} catch (\Throwable $noteError) {
				}
			}
			return new \WP_Error('robokassa_sms_state_unavailable', 'SMS STATE UNAVAILABLE', 503);
		} finally {
			unset(self::$activeLocks[$lock]);
			$this->dataBase->getVar('SELECT RELEASE_LOCK(%s)', array($lock));
		}
	}

	private function saveState(\WC_Order $order, array $state) {
		$key = self::META_PREFIX . $this->type;
		$order->update_meta_data($key, $state);
		$order->save_meta_data();
		$order->read_meta_data(true);
		if ($order->get_meta($key, true) !== $state) {
			throw new \RuntimeException('sms state was not saved');
		}
	}

	private function readPreviousAttempt(\WC_Order $order) {
		// учитываем состояние sms из предыдущего обработчика resulturl
		$progress = $order->get_meta('_robokassa_result_progress', true);
		if ($this->type === 1 && is_array($progress)
			&& in_array($progress['sms'] ?? '', array('attempted', 'sent', 'failed', 'uncertain'), true)) {
			return array('version' => 1, 'status' => $progress['sms'] === 'sent' ? 'sent' : 'uncertain', 'attempted_at' => 0, 'source' => 'result_progress');
		}
		// старая таблица только читается и может отсутствовать
		$table = robokassa_payment_getDbPrefix() . 'sms_stats';
		$exists = $this->dataBase->getVar('SHOW TABLES LIKE %s', array(addcslashes($table, '\\_%')));
		if ($this->dataBase->hasError() || ($exists !== null && $exists !== $table)) {
			throw new \RuntimeException('legacy sms history is unavailable');
		}
		if ($exists === null) {
			return null;
		}
		$count = $this->dataBase->getVar("SELECT COUNT(*) FROM {$table} WHERE order_id = %d AND type = %d", array($this->order_id, $this->type));
		if ($this->dataBase->hasError() || $count === null || !ctype_digit((string)$count)) {
			throw new \RuntimeException('legacy sms history is unavailable');
		}
		if ((int)$count > 0) {
			return array('version' => 1, 'status' => 'uncertain', 'attempted_at' => 0, 'source' => 'legacy');
		}
		return null;
	}

	private function finish(\WC_Order $order, array $state) {
		if ($state['status'] === 'sent') {
			return true;
		}
		$this->warn($order, $state['status'] === 'failed'
			? 'не принята сервисом. Автоматический повтор отключён'
			: 'результат отправки не подтверждён. Проверьте доставку перед ручной отправкой. Автоматический повтор отключён');
		return $state['status'] === 'failed' ? false : null;
	}

	private function warn(\WC_Order $order, $reason) {
		$message = 'Robokassa: SMS ' . ($this->type === 1 ? 'об оплате ' : 'о выполнении заказа ') . $reason . '.';
		foreach (wc_get_order_notes(array('order_id' => $order->get_id(), 'search' => $message)) as $note) {
			if ($note->content === $message) {
				return;
			}
		}
		if (!$order->add_order_note($message)) {
			throw new \RuntimeException('sms warning was not saved');
		}
	}

	public static function renderOrderStatus($order) {
		if (!$order instanceof \WC_Order) {
			return;
		}
		$labels = array('attempted' => 'попытка начата, результат не подтверждён', 'sent' => 'принята сервисом', 'failed' => 'не принята сервисом', 'uncertain' => 'результат неизвестен, проверьте доставку');
		foreach (array(1 => 'SMS об оплате', 2 => 'SMS о выполнении заказа') as $type => $title) {
			$state = $order->get_meta(self::META_PREFIX . $type, true);
			if (!is_array($state) || ($state['version'] ?? null) !== 1
				|| !is_string($state['status'] ?? null) || !isset($labels[$state['status']])) {
				continue;
			}
			$stamp = is_int($state['attempted_at'] ?? null) ? $state['attempted_at'] : 0;
			$time = $stamp > 0 ? ' (' . wp_date('d.m.Y H:i', $stamp) . ')' : '';
			echo '<p><strong>' . esc_html($title) . ':</strong> ' . esc_html($labels[$state['status']] . $time) . '</p>';
		}
	}

	private function filterSms(\WC_Order $order) {
		$address = trim($order->get_billing_address_1() . ' ' . $order->get_billing_address_2());
		$name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
		if (mb_strlen($address, 'UTF-8') > 30) {
			$address = mb_strimwidth($address, 0, 30, '...', 'UTF-8');
		}
		if (mb_strlen($name, 'UTF-8') > 30) {
			$name = mb_strimwidth($name, 0, 30, '...', 'UTF-8');
		}
		return str_replace(array('{address}', '{fio}', '{order_number}'), array($address, $name, $this->order_id), $this->message);
	}

	private function transliterate() {
		return (!$this->translit)
			? $this->message
			: str_replace(array(
				'А',
				'Б',
				'В',
				'Г',
				'Д',
				'Е',
				'Ё',
				'Ж',
				'З',
				'И',
				'Й',
				'К',
				'Л',
				'М',
				'Н',
				'О',
				'П',
				'Р',
				'С',
				'Т',
				'У',
				'Ф',
				'Х',
				'Ц',
				'Ч',
				'Ш',
				'Щ',
				'Ъ',
				'Ы',
				'Ь',
				'Э',
				'Ю',
				'Я',
				'а',
				'б',
				'в',
				'г',
				'д',
				'е',
				'ё',
				'ж',
				'з',
				'и',
				'й',
				'к',
				'л',
				'м',
				'н',
				'о',
				'п',
				'р',
				'с',
				'т',
				'у',
				'ф',
				'х',
				'ц',
				'ч',
				'ш',
				'щ',
				'ъ',
				'ы',
				'ь',
				'э',
				'ю',
				'я',
			), array(
				'A',
				'B',
				'V',
				'G',
				'D',
				'E',
				'E',
				'Gh',
				'Z',
				'I',
				'Y',
				'K',
				'L',
				'M',
				'N',
				'O',
				'P',
				'R',
				'S',
				'T',
				'U',
				'F',
				'H',
				'C',
				'Ch',
				'Sh',
				'Sch',
				'Y',
				'Y',
				'Y',
				'E',
				'Yu',
				'Ya',
				'a',
				'b',
				'v',
				'g',
				'd',
				'e',
				'e',
				'gh',
				'z',
				'i',
				'y',
				'k',
				'l',
				'm',
				'n',
				'o',
				'p',
				'r',
				's',
				't',
				'u',
				'f',
				'h',
				'c',
				'ch',
				'sh',
				'sch',
				'y',
				'y',
				'y',
				'e',
				'yu',
				'ya',
			), $this->message);
	}

}
