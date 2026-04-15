<?php

/**
 * Подготавливает список элементов в массив.
 *
 * @param mixed $value
 *
 * @return array
 */
if (!function_exists('robokassa_labels_wrap_list')) {
	function robokassa_labels_wrap_list($value)
	{
		if (!is_array($value)) {
			return $value === null ? [] : [$value];
		}

		return array_keys($value) === range(0, count($value) - 1) ? $value : [$value];
	}
}

/**
 * Добавляет данные по алиасу в результирующий массив.
 *
 * @param array $aliases
 * @param array $attributes
 *
 * @return void
 */
if (!function_exists('robokassa_labels_register_alias')) {
	function robokassa_labels_register_alias(array &$aliases, array $attributes)
	{
		$alias = strtoupper(trim((string)($attributes['Alias'] ?? '')));
		if ($alias === '') {
			return;
		}
		if (!isset($aliases[$alias])) {
			$aliases[$alias] = [
				'Alias' => $alias,
			];
		}
		if (isset($attributes['MinValue']) && $attributes['MinValue'] !== '') {
			$aliases[$alias]['MinValue'] = (string)$attributes['MinValue'];
		}
		if (isset($attributes['MaxValue']) && $attributes['MaxValue'] !== '') {
			$aliases[$alias]['MaxValue'] = (string)$attributes['MaxValue'];
		}
	}
}

/**
 * Формирует карту доступных алиасов с ограничениями сумм.
 *
 * @param mixed $currencies
 *
 * @return array
 */
if (!function_exists('robokassa_labels_collect_aliases')) {
	function robokassa_labels_collect_aliases($currencies)
	{
		$data = json_decode(json_encode($currencies), true);
		if (!is_array($data)) {
			return [];
		}
		$aliases = [];
		$groups = $data['Groups']['Group'] ?? [];
		foreach (robokassa_labels_wrap_list($groups) as $group) {
			if (!is_array($group)) {
				continue;
			}
			$items = $group['Items']['Currency'] ?? [];
			foreach (robokassa_labels_wrap_list($items) as $currency) {
				if (!is_array($currency)) {
					continue;
				}
				$attributes = $currency['@attributes'] ?? [];
				if (!is_array($attributes)) {
					continue;
				}
				robokassa_labels_register_alias($aliases, $attributes);
			}
		}
		ksort($aliases);
		return $aliases;
	}
}

/**
 * Обновляет локальный список доступных способов оплаты Robokassa.
 *
 * @param bool $display_errors
 *
 * @return bool
 */
if (!function_exists('robokassa_update_currency_labels')) {
	function robokassa_update_currency_labels($display_errors = false)
	{
		$robokassa = new \Robokassa\Payment\RobokassaPayAPI(
			\get_option('robokassa_payment_MerchantLogin'),
			\get_option('robokassa_payment_shoppass1'),
			\get_option('robokassa_payment_shoppass2')
		);

		$currLabels = $robokassa->getCurrLabels();

		if (!$currLabels) {
			\update_option('robokassa_currency_labels_last_error', time(), false);
			return false;
		}

		$aliases = robokassa_labels_collect_aliases($currLabels);
		$labelsPath = __DIR__ . '/data/currencies.json';
		$json = json_encode($aliases, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

		if (!is_string($json)) {
			if ($display_errors) {
				echo 'Ошибка при обработке списка валют';
			}

			\update_option('robokassa_currency_labels_last_error', time(), false);
			return false;
		}

		if (file_put_contents($labelsPath, $json) === false) {
			if ($display_errors) {
				echo 'Ошибка при сохранении данных в файл';
			}

			\update_option('robokassa_currency_labels_last_error', time(), false);
			return false;
		}

		\update_option('robokassa_currency_labels_updated_at', time(), false);
		\delete_option('robokassa_currency_labels_last_error');

		return true;
	}
}
