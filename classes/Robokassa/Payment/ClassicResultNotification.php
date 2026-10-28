<?php

namespace Robokassa\Payment;

class ClassicResultNotification
{
	const PROCESSED_META_KEY = '_robokassa_result_processed';
	const PROGRESS_META_KEY = '_robokassa_result_progress';
	const NOTE_META_KEY = '_robokassa_result_note';

	private $database;

	public function __construct(\wpdb $database)
	{
		$this->database = $database;
	}

	public function process(array $request, $orderStatus)
	{
		if (!robokassa_payment_is_valid_callback_signature($request, robokassa_payment_get_callback_password(2))) {
			return new \WP_Error('robokassa_result_bad_signature', 'BAD SIGN', 400);
		}
		$invoiceId = (string)$request['InvId'];
		$orderId = (int)$invoiceId;
		if (!ctype_digit($invoiceId) || $orderId <= 0 || ltrim($invoiceId, '0') !== (string)$orderId) {
			return new \WP_Error('robokassa_result_bad_invoice', 'INVALID INVOICE', 400);
		}

		$lockName = 'robokassa_result_' . md5((defined('DB_NAME') ? DB_NAME : '') . ':' . $this->database->prefix . ':' . $orderId);
		$locked = $this->database->get_var($this->database->prepare('SELECT GET_LOCK(%s, 10)', $lockName));
		if ((string)$locked !== '1') {
			return new \WP_Error('robokassa_result_busy', 'RETRY LATER', 503);
		}

		try {
			// заказ и его метаданные читаем только после получения блокировки
			$dataStore = \WC_Data_Store::load('order');
			if (method_exists($dataStore->get_current_class_name(), 'clear_cached_data')) {
				$dataStore->clear_cached_data(array($orderId));
			}
			wp_cache_delete($orderId, 'posts');
			wp_cache_delete($orderId, 'post_meta');
			try {
				$order = new \WC_Order($orderId);
			} catch (\Exception $error) {
				return new \WP_Error('robokassa_result_order_missing', 'ORDER NOT FOUND', 404);
			}
			if (!$order->get_id()) {
				return new \WP_Error('robokassa_result_order_missing', 'ORDER NOT FOUND', 404);
			}
			$order->read_meta_data(true);
			if ($order->get_meta(self::PROCESSED_META_KEY, true) === 'yes') {
				return 'OK' . $invoiceId;
			}
			$progress = $order->get_meta(self::PROGRESS_META_KEY, true);
			if ($progress === '') {
				// старые оплаченные заказы не запускают новый цикл обработки
				if ($this->isPaid($order)) {
					return 'OK' . $invoiceId;
				}
				$progress = array(
					'version' => 1,
					'target_status' => preg_replace('/^wc-/', '', (string)$orderStatus),
					'done' => array(),
					'sms' => get_option('robokassa_payment_sms1_enabled') === 'on' ? 'pending' : 'disabled',
				);
				$this->saveProgress($order, $progress);
			}
			if (!is_array($progress) || ($progress['version'] ?? null) !== 1
				|| !isset($progress['target_status'], $progress['done'], $progress['sms'])
				|| !is_array($progress['done'])) {
				throw new \RuntimeException('invalid result progress');
			}

			$this->prepareSubscriptions($order, $progress);
			$this->completePayment($order, $progress);
			$this->completeStatus($order, $progress);
			if (!empty($progress['payment_recovered'])) {
				$this->ensureNote($order, 'payment_recovered', 'Robokassa: оплата сохранена, но завершение обработчиков WooCommerce не подтверждено. Проверьте склад, доступ к загрузкам и действия сторонних расширений.');
			}
			if (empty($progress['done']['note'])) {
				$this->ensureNote($order, 'paid', 'Заказ успешно оплачен!');
				$progress['done']['note'] = true;
				$this->saveProgress($order, $progress);
			}
			if (empty($progress['done']['cart'])) {
				$cart = WC()->cart;
				if ($cart && !$cart->is_empty()) {
					$cart->empty_cart();
				}
				$progress['done']['cart'] = true;
				$this->saveProgress($order, $progress);
			}
			$this->completeSubscriptions($order, $progress);
			$this->completeSms($order, $progress);
			$order->update_meta_data(self::PROCESSED_META_KEY, 'yes');
			$order->save_meta_data();
			$order->read_meta_data(true);
			if ($order->get_meta(self::PROCESSED_META_KEY, true) !== 'yes') {
				throw new \RuntimeException('result completion was not saved');
			}

			return 'OK' . $invoiceId;
		} catch (\Throwable $error) {
			return new \WP_Error('robokassa_result_processing_failed', 'RETRY LATER', 503);
		} finally {
			$this->database->get_var($this->database->prepare('SELECT RELEASE_LOCK(%s)', $lockName));
		}
	}

	private function isPaid(\WC_Order $order)
	{
		return $order->get_date_paid('edit') || $order->is_paid();
	}

	private function saveProgress(\WC_Order $order, array $progress)
	{
		// сохраняем только метаданные, не повторяя переходы статуса заказа
		$order->update_meta_data(self::PROGRESS_META_KEY, $progress);
		$order->save_meta_data();
		$order->read_meta_data(true);
		if ($order->get_meta(self::PROGRESS_META_KEY, true) !== $progress) {
			throw new \RuntimeException('result progress was not saved');
		}
	}

	private function completePayment(\WC_Order $order, array &$progress)
	{
		if (!empty($progress['done']['payment'])) {
			return;
		}
		if (!$this->isPaid($order)) {
			// ожидаемый статус сохраняется вместе с оплатой, до вызова внешних хуков
			$captureStatus = function ($status, $orderId) use ($order, &$progress) {
				if ((int)$orderId === $order->get_id()) {
					$progress['payment_status'] = $status;
					$order->update_meta_data(self::PROGRESS_META_KEY, $progress);
				}
				return $status;
			};
			add_filter('woocommerce_payment_complete_order_status', $captureStatus, PHP_INT_MAX, 2);
			try {
				if (!$order->payment_complete() || !$this->isPaid($order)) {
					throw new \RuntimeException('payment was not completed');
				}
			} finally {
				remove_filter('woocommerce_payment_complete_order_status', $captureStatus, PHP_INT_MAX);
			}
		} elseif (empty($progress['done']['payment'])) {
			// произвольные хуки расширений нельзя безопасно повторять после сохранения оплаты
			$progress['payment_recovered'] = true;
		}
		if (empty($progress['done']['payment'])) {
			$progress['done']['payment'] = true;
			$this->saveProgress($order, $progress);
		}
	}

	private function completeStatus(\WC_Order $order, array &$progress)
	{
		if (!empty($progress['done']['status'])) {
			return;
		}
		$target = $progress['target_status'];
		// ручную смену статуса между попытками сохраняем
		if ($target !== '' && $order->get_status() !== $target
			&& $order->get_status() === ($progress['payment_status'] ?? '')) {
			if (!$order->update_status($target)) {
				throw new \RuntimeException('payment status was not saved');
			}
		}
		$progress['done']['status'] = true;
		$this->saveProgress($order, $progress);
	}

	private function ensureNote(\WC_Order $order, $key, $message)
	{
		if (wc_get_order_notes(array('order_id' => $order->get_id(), 'limit' => 1,
			'meta_key' => self::NOTE_META_KEY, 'meta_value' => $key))) {
			return;
		}
		// учитываем сбой между вставкой заметки и сохранением её метаданных
		foreach (wc_get_order_notes(array('order_id' => $order->get_id(), 'search' => $message)) as $note) {
			if ($note->content === $message) {
				return;
			}
		}
		// метка позволяет найти заметку после сбоя обработчиков
		$markNote = function ($data, $args) use ($order, $key, $message) {
			if ((int)$args['order_id'] === $order->get_id() && $data['comment_content'] === $message) {
				$data['comment_meta'][self::NOTE_META_KEY] = $key;
			}
			return $data;
		};
		add_filter('woocommerce_new_order_note_data', $markNote, PHP_INT_MAX, 2);
		try {
			if (!$order->add_order_note($message)) {
				throw new \RuntimeException('payment note was not saved');
			}
		} finally {
			remove_filter('woocommerce_new_order_note_data', $markNote, PHP_INT_MAX);
		}
	}

	private function prepareSubscriptions(\WC_Order $order, array &$progress)
	{
		if (!isset($progress['subscriptions'])) {
			$subscriptions = function_exists('wcs_get_subscriptions_for_order')
				? (wcs_get_subscriptions_for_order($order->get_id()) ?: wcs_get_subscriptions_for_renewal_order($order->get_id()))
				: array();
			$progress['subscriptions'] = array();
			foreach ($subscriptions as $subscription) {
				$progress['subscriptions'][$subscription->get_id()] = false;
			}
			$this->saveProgress($order, $progress);
		}
	}

	private function completeSubscriptions(\WC_Order $order, array &$progress)
	{
		foreach ($progress['subscriptions'] as $id => $done) {
			if ($done) {
				continue;
			}
			$subscription = function_exists('wcs_get_subscription') ? wcs_get_subscription($id) : false;
			if (!$subscription) {
				throw new \RuntimeException('subscription is unavailable');
			}
			// активные и завершённые подписки не активируем повторно
			if (!in_array($order->get_status(), array('cancelled', 'refunded', 'failed'), true)
				&& in_array($subscription->get_status(), array('pending', 'on-hold'), true)) {
				$subscription->update_status('active');
				if ($subscription->get_status() !== 'active') {
					throw new \RuntimeException('subscription was not activated');
				}
			}
			$progress['subscriptions'][$id] = true;
			$this->saveProgress($order, $progress);
		}
	}

	private function completeSms(\WC_Order $order, array &$progress)
	{
		if (in_array($progress['sms'], array('pending', 'attempted'), true)) {
			$result = robokassa_payment_send_result_sms($order);
			if (is_wp_error($result)) {
				throw new \RuntimeException('sms processing is unavailable');
			}
			$progress['sms'] = $result === true ? 'sent' : ($result === false ? 'failed' : 'uncertain');
			$this->saveProgress($order, $progress);
		}
	}
}
