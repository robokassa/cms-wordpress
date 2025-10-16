<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class WC_Robokassa_Blocks extends AbstractPaymentMethodType
{
	/**
	 * @var string
	 */
	protected $name;

	/**
	 * @var string
	 */
	private $gateway_id;

	/**
	 * @var \WC_Payment_Gateway|null
	 */
	private $gateway_instance;

	/**
	 * @param string $gateway_id
	 */
	public function __construct($gateway_id)
	{
		$this->gateway_id = $gateway_id;
		$this->name = $gateway_id;
	}

	/**
	 * Инициализация настроек метода оплаты.
	 *
	 * @return void
	 */
	public function initialize()
	{
		$this->settings = get_option('woocommerce_' . $this->gateway_id . '_settings', []);
	}

	/**
	 * Регистрирует скрипт интеграции блока оплаты.
	 *
	 * @return string[]
	 */
	public function get_payment_method_script_handles()
	{
		if (!wp_script_is('robokassa-blocks-integration', 'registered')) {
			wp_register_script(
				'robokassa-blocks-integration',
				plugin_dir_url(__FILE__) . 'blocks.js',
				[
					'wc-blocks-registry',
					'wc-settings',
					'wp-element',
					'wp-html-entities',
					'wp-i18n',
				],
				null,
				true
			);
		}

		return ['robokassa-blocks-integration'];
	}

	/**
	 * Возвращает данные для блока метода оплаты.
	 *
	 * @return array
	 */
	public function get_payment_method_data()
	{
		return [
			'title' => get_option('RobokassaOrderPageTitle_' . $this->gateway_id, null),
			'description' => get_option('RobokassaOrderPageDescription_' . $this->gateway_id, null),
			'supports' => $this->get_gateway_supports(),
		];
	}

	/**
	 * Проверяет активность метода оплаты в блочном чекауте.
	 *
	 * @return bool
	 */
	public function is_active()
	{
		$gateway = $this->get_gateway_instance();

		if (!$gateway) {
			return false;
		}

		if (property_exists($gateway, 'enabled') && 'yes' !== $gateway->enabled) {
			return false;
		}

		if ('yes' !== ($this->settings['enabled'] ?? 'yes')) {
			return false;
		}

		if (method_exists($gateway, 'is_available')) {
			return (bool)$gateway->is_available();
		}

		return true;
	}

	/**
	 * Возвращает поддерживаемые возможности метода оплаты.
	 *
	 * @return array
	 */
	private function get_gateway_supports()
	{
		$gateway = $this->get_gateway_instance();

		if (!$gateway || !is_array($gateway->supports)) {
			return [];
		}

		return array_values($gateway->supports);
	}

	/**
	 * Возвращает экземпляр класса платёжного шлюза.
	 *
	 * @return \WC_Payment_Gateway|null
	 */
	private function get_gateway_instance()
	{
		if ($this->gateway_instance instanceof \WC_Payment_Gateway) {
			return $this->gateway_instance;
		}

		if (function_exists('WC')) {
			$gateways = WC()->payment_gateways();

			if ($gateways && method_exists($gateways, 'payment_gateways')) {
				foreach ((array)$gateways->payment_gateways() as $gateway) {
					if ($gateway instanceof \WC_Payment_Gateway && $gateway->id === $this->gateway_id) {
						$this->gateway_instance = $gateway;
						return $this->gateway_instance;
					}
				}
			}
		}

		if (!function_exists('robokassa_payment_add_WC_WP_robokassa_class')) {
			require_once __DIR__ . '/labelsClasses.php';
		}

		foreach ((array)robokassa_payment_add_WC_WP_robokassa_class() as $class_name) {
			if (!class_exists($class_name)) {
				continue;
			}

			$instance = new $class_name();

			if (!empty($instance->id) && $instance->id === $this->gateway_id) {
				$this->gateway_instance = $instance;
				break;
			}
		}

		return $this->gateway_instance instanceof \WC_Payment_Gateway
			? $this->gateway_instance
			: null;
	}
}