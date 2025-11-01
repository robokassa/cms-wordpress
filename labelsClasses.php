<?php

class payment_robokassa_pay_method_request_main extends \Robokassa\Payment\WC_WP_robokassa {
	public function __construct() {
		$this->id = 'robokassa';
		$this->method_title = 'Robokassa';
		$this->long_name = 'Оплата через Robokassa';
		$this->description = get_option('RobokassaOrderPageDescription', 'Оплатить через Robokassa');

		parent::__construct();
	}
}

class payment_robokassa_pay_method_request_podeli extends \Robokassa\Payment\WC_WP_robokassa {
	public function __construct() {
		$this->id = 'robokassa_podeli';
		$this->method_title = 'Robokassa';
		$this->long_name = 'Оплата через Robokassa';
		$this->title = 'Robokassa Х Подели';

		parent::__construct();
	}
}

class payment_robokassa_pay_method_request_credit extends \Robokassa\Payment\WC_WP_robokassa {
	public function __construct() {
		$this->id = 'robokassa_credit';
		$this->method_title = 'Robokassa';
		$this->long_name = 'Оплата через Robokassa';
		$this->title = 'Рассрочка или кредит';

		parent::__construct();
	}
}

class payment_robokassa_pay_method_request_mokka extends \Robokassa\Payment\WC_WP_robokassa {
	public function __construct() {
		$this->id = 'robokassa_mokka';
		$this->method_title = 'Robokassa';
		$this->long_name = 'Оплата через Robokassa';
		$this->title = 'Robokassa X Mokka';

		parent::__construct();
	}
}

class payment_robokassa_pay_method_request_split extends \Robokassa\Payment\WC_WP_robokassa {
	public function __construct() {
		$this->id = 'robokassa_split';
		$this->method_title = 'Robokassa';
		$this->long_name = 'Оплата через Robokassa';
		$this->title = 'Robokassa X Яндекс Сплит';

		parent::__construct();
	}
}

/**
 * Возвращает описание дополнительных способов оплаты Robokassa.
 *
 * @return array
 */
function robokassa_get_optional_payment_methods_config()
{
	return [
		[
			'class' => 'payment_robokassa_pay_method_request_credit',
			'gateway_id' => 'robokassa_credit',
			'option' => 'robokassa_payment_method_credit_enabled',
			'alias' => 'OTP',
			'title' => 'Рассрочка или кредит',
		],
		[
			'class' => 'payment_robokassa_pay_method_request_podeli',
			'gateway_id' => 'robokassa_podeli',
			'option' => 'robokassa_payment_method_podeli_enabled',
			'alias' => 'Podeli',
			'title' => 'Robokassa Х Подели',
		],
		[
			'class' => 'payment_robokassa_pay_method_request_mokka',
			'gateway_id' => 'robokassa_mokka',
			'option' => 'robokassa_payment_method_mokka_enabled',
			'alias' => 'Mokka',
			'title' => 'Robokassa X Mokka',
		],
		[
			'class' => 'payment_robokassa_pay_method_request_split',
			'gateway_id' => 'robokassa_split',
			'option' => 'robokassa_payment_method_split_enabled',
			'alias' => 'YandexPaySplit',
			'title' => 'Robokassa X Яндекс Сплит',
		],
	];
}

/**
 * Возвращает конфигурацию дополнительного метода по идентификатору шлюза.
 *
 * @param string $gateway_id
 *
 * @return array
 */
function robokassa_get_optional_method_config_by_gateway($gateway_id)
{
	foreach (robokassa_get_optional_payment_methods_config() as $config) {
		if (($config['gateway_id'] ?? '') === $gateway_id) {
			return $config;
		}
	}

	return [];
}

/**
 * Проверяет, включён ли дополнительный способ оплаты в настройках.
 *
 * @param array $config
 *
 * @return bool
 */
function robokassa_is_optional_method_enabled(array $config)
{
	$option_name = $config['option'] ?? '';

	if ($option_name === '') {
		return false;
	}

	$option_value = get_option($option_name, 'yes');

	return $option_value !== 'no';
}

/**
 * Проверяет, доступен ли дополнительный способ оплаты для магазина.
 *
 * @param array $config
 *
 * @return bool
 */
function robokassa_is_optional_method_available(array $config)
{
	$alias = $config['alias'] ?? '';

	if ($alias === '') {
		return false;
	}

	if (function_exists('robokassa_is_currency_alias_available')) {
		return robokassa_is_currency_alias_available($alias);
	}

	return true;
}

/**
 * Определяет, следует ли регистрировать дополнительный способ оплаты.
 *
 * @param array $config
 *
 * @return bool
 */
function robokassa_is_optional_method_active(array $config)
{
	if (get_option('robokassa_country_code', 'RU') === 'KZ') {
		return false;
	}

	return robokassa_is_optional_method_available($config) && robokassa_is_optional_method_enabled($config);
}

function robokassa_payment_add_WC_WP_robokassa_class($methods = null) {
	$methods[] = 'payment_robokassa_pay_method_request_main';

	foreach (robokassa_get_optional_payment_methods_config() as $config) {
		if (!isset($config['class'])) {
			continue;
		}

		if (!robokassa_is_optional_method_active($config)) {
			continue;
		}

		$methods[] = $config['class'];
	}

	return $methods;
}