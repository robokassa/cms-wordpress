<?php

namespace Robokassa\Payment;

/**
 * Проверка активности плагина WooCommerce
 */

if (!function_exists('is_plugin_active')) {
	require_once ABSPATH . '/wp-admin/includes/plugin.php';
}
if (!is_plugin_active('woocommerce/woocommerce.php')) {
	add_action(
		'admin_notices',
		function () {
			echo '<div class="error"><p><strong>Robokassa WooCommerce требует установленный и активный плагин <a href="https://wordpress.org/plugins/woocommerce/" target="_blank">WooCommerce</a>.</strong></p></div>';
		}
	);

	return;
}

/**
 * Класс выбора типа оплаты на стороне Робокассы
 */
class WC_WP_robokassa extends \WC_Payment_Gateway {
	/**
	 * @var array
	 */
	protected static $registered_subscription_hooks = [];

	/**
	 * @var string
	 */
	public $long_name;

	/**
	 * @var int | float
	 */
	public $commission;

	/**
	 * WC_WP_robokassa constructor.
	 */
	public function __construct() {


		$this->title = !empty(get_option('RobokassaOrderPageTitle_' . $this->id, null))
			? get_option('RobokassaOrderPageTitle_' . $this->id, null)
			: $this->title;

		$this->description = !empty(get_option('RobokassaOrderPageDescription_' . $this->id, null))
			? get_option('RobokassaOrderPageDescription_' . $this->id, null)
			: $this->description;

		if (function_exists('robokassa_append_payment_graph_to_description')) {
			$this->description = robokassa_append_payment_graph_to_description($this->description, $this->id);
		}

		$this->supports = ['products'];

		if (get_option('robokassa_country_code', 'RU') === 'RU') {
			$this->supports[] = 'refunds';
		}

		if ($this->id === 'robokassa') {
			$this->supports = array_merge(
				$this->supports,
				[
					'subscriptions',
					'subscription_cancellation',
					'subscription_suspension',
					'subscription_reactivation',
					// 'subscription_amount_changes',
					'subscription_date_changes',
					// 'subscription_payment_method_change',
					// 'subscription_payment_method_change_customer',
					// 'subscription_payment_method_change_admin',
					// 'multiple_subscriptions'
				]
			);
		}

		$this->init_form_fields();
		$this->init_settings();

		$this->method_description = $this->long_name.'<br>Больше настроек в <a href="'.admin_url('/admin.php?page=robokassa_payment_main_settings_rb').'">панели плагина</a>';

		add_action('woocommerce_api_wc_'.$this->id, array($this, 'check_ipn'));
		add_action('woocommerce_receipt_'.$this->id, array($this, 'receipt_page'));

		$this->register_subscription_hooks();
	}

	/**
	 * Регистрирует подписочные хуки один раз для каждого gateway id.
	 *
	 * @return void
	 */
	protected function register_subscription_hooks()
	{
		if (!class_exists('WC_Subscriptions_Order')) {
			return;
		}

		$gateway_ids = [$this->id];

		foreach (array_unique(array_filter($gateway_ids)) as $gateway_id) {
			if (isset(self::$registered_subscription_hooks[$gateway_id])) {
				continue;
			}

			add_action(
				'woocommerce_scheduled_subscription_payment_' . $gateway_id,
				[$this, 'scheduled_subscription_payment'],
				10,
				2
			);

			self::$registered_subscription_hooks[$gateway_id] = true;
		}
	}

	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title' => 'Включить/Выключить',
				'type' => 'checkbox',
				'label' => $this->long_name,
				'default' => 'yes',
			),
		);
	}

	public function receipt_page($order) {
		robokassa_payment_createFormWC($order, $this->id);
	}

	/**
	 * Проверяет доступность способа оплаты на текущем шаге оформления.
	 *
	 * @return bool
	 */
	public function is_available()
	{
		if (!parent::is_available()) {
			return false;
		}

		if ($this->is_subscription_context() && $this->id !== 'robokassa') {
			return false;
		}

		if (!function_exists('robokassa_get_optional_method_config_by_gateway')) {
			return true;
		}

		$config = robokassa_get_optional_method_config_by_gateway($this->id);

		if (empty($config) || !isset($config['alias'])) {
			return true;
		}

		if (!robokassa_is_optional_method_active($config)) {
			return false;
		}

		$amount = $this->get_current_payment_amount();

		if ($amount === null) {
			return true;
		}

		return robokassa_is_amount_allowed_for_alias($config['alias'], $amount);
	}

	/**
	 * Проверяет, относится ли текущий checkout к подписке.
	 *
	 * @return bool
	 */
	protected function is_subscription_context()
	{
		if (function_exists('wcs_cart_contains_subscription') && wcs_cart_contains_subscription()) {
			return true;
		}

		if (!function_exists('wcs_order_contains_subscription')) {
			return false;
		}

		$order_id = absint(get_query_var('order-pay'));

		if ($order_id > 0 && wcs_order_contains_subscription($order_id)) {
			return true;
		}

		return false;
	}

	/**
	 * Возвращает сумму текущего платежа для проверки ограничений Robokassa.
	 *
	 * @return float|null
	 */
	protected function get_current_payment_amount()
	{
		$order_amount = $this->get_order_pay_amount();

		if ($order_amount !== null) {
			return $order_amount;
		}

		if (!function_exists('WC')) {
			return null;
		}

		$cart = WC()->cart;

		if (!is_object($cart)) {
			return null;
		}

		$total = $cart->get_total('edit');

		if ($total === '' || $total === null) {
			return null;
		}

		if (function_exists('robokassa_normalize_amount_value')) {
			return robokassa_normalize_amount_value($total);
		}

		return (float)$total;
	}

	/**
	 * Возвращает сумму заказа на странице оплаты заказа.
	 *
	 * @return float|null
	 */
	protected function get_order_pay_amount()
	{
		if (!function_exists('is_checkout_pay_page') || !is_checkout_pay_page()) {
			return null;
		}

		$order_id = absint(get_query_var('order-pay'));

		if ($order_id <= 0) {
			return null;
		}

		$order = wc_get_order($order_id);

		if (!$order instanceof \WC_Order) {
			return null;
		}

		$order_key = isset($_GET['key']) ? sanitize_text_field(wp_unslash($_GET['key'])) : '';

		if ($order_key !== '' && $order->get_order_key() !== $order_key) {
			return null;
		}

		$total = $order->get_total();

		if (function_exists('robokassa_normalize_amount_value')) {
			return robokassa_normalize_amount_value($total);
		}

		return (float)$total;
	}

	/**
	 * Scheduled_subscription_payment function.
	 *
	 * @param $amount_to_charge float The amount to charge.
	 * @param $renewal_order WC_Order A WC_Order object created to record the renewal payment.
	 */
	public function scheduled_subscription_payment( $amount_to_charge, $renewal_order ) {
		$this->process_subscription_payment( $amount_to_charge, $renewal_order, true, false );
	}


	/**
	 * Выполняем процесс оплаты подписки
	 *
	 * @param float  $amount
	 * @param mixed  $renewal_order
	 * @param bool   $retry Should we retry the process?
	 * @param object $previous_error
	 */
	public function process_subscription_payment( $amount, $renewal_order, $retry = true, $previous_error = false ) {

		$taxes = $renewal_order->get_cart_tax();
		$order_id = $renewal_order->get_id();


		$subscriptions = wcs_get_subscriptions_for_renewal_order($renewal_order);
		$subscribe = reset($subscriptions);

		$parent = $subscribe->get_parent();

		$mrhLogin  = get_option('robokassa_payment_MerchantLogin');
		$testMode  = false;

		if (get_option('robokassa_payment_test_onoff') == 'true') {
			$pass1    = get_option('robokassa_payment_testshoppass1');
			$pass2    = get_option('robokassa_payment_testshoppass2');
			$testMode = true;
		} else {
			$pass1 = get_option('robokassa_payment_shoppass1');
			$pass2 = get_option('robokassa_payment_shoppass2');
		}

		$sno = get_option('robokassa_payment_sno');
		$tax = \function_exists('robokassa_payment_get_default_tax')
			? \robokassa_payment_get_default_tax()
			: get_option('robokassa_payment_tax');
		$country = get_option('robokassa_country_code');

		$receipt = array();

		if ($country !== 'KZ' && $sno != 'fckoff') {
			$receipt['sno'] = $sno;
		}

		foreach ($renewal_order->get_items() as $item)
		{
			$product = $item->get_product();;

			$current['name'] = $product->get_title();
			$current['quantity'] = (float)$item['quantity'];

			$tax_per_item = ($taxes / $renewal_order->get_item_count()) * $current['quantity'];

			$current['cost'] = ($item['line_total'] + $tax_per_item) / $current['quantity'];

			$current['payment_object'] = \get_option('robokassa_payment_paymentObject');
			$current['payment_method'] = \get_option('robokassa_payment_paymentMethod');

			if ($country === 'KZ' || (isset($receipt['sno']) && ($receipt['sno'] == 'osn'))) {
				$current['tax'] = $tax;
			} else {
				$current['tax'] = 'none';
			}

			$receipt['items'][] = $current;
		}

		if((double) $renewal_order->get_shipping_total() > 0)
		{

			$current['name'] = 'Доставка';
			$current['quantity'] = 1;
			$current['cost'] = (double)\sprintf(
				"%01.2f",
				( $renewal_order->get_shipping_total() + $renewal_order->get_shipping_tax() )
			);
			$current['payment_object'] = \get_option('robokassa_payment_paymentObject_shipping') ?: get_option('robokassa_payment_paymentObject');
			$current['payment_method'] = \get_option('robokassa_payment_paymentMethod');

			if ($country === 'KZ' || (isset($receipt['sno']) && ($receipt['sno'] == 'osn'))) {
				$current['tax'] = $tax;
			} else {
				$current['tax'] = 'none';
			}

			$receipt['items'][] = $current;
		}

		$robokassa = new RobokassaPayAPI($mrhLogin, $pass1, $pass2);
		$data = $robokassa->getRecurringPaymentData($order_id, $parent->get_id(), $amount, $receipt, 'Оплата подписки');

		if ($testMode) {
			$data['IsTest'] = 1;
		}


		$ret = wp_remote_post('https://auth.robokassa.ru/Merchant/Recurring', array(
			'header' => 'Content-Type: application/x-www-form-urlencoded',
			'method' => 'POST',
			'body' => http_build_query($data)
		));
	}

	/**
	 * По идее - выполняем процесс оплаты и получаем результат
	 *
	 * @param int $order_id
	 *
	 * @return array
	 */
	public function process_payment($order_id)
	{

		/** @var bool|WC_Order|WC_Refund $order */
		$order = \wc_get_order($order_id);

		return array(
			'result' => 'success',
			'redirect' => $order->get_checkout_payment_url(true)
		);
	}

	/**
	 * Создаёт полный или частичный возврат через штатный интерфейс WooCommerce.
	 *
	 * @param int        $order_id
	 * @param float|null $amount
	 * @param string     $reason
	 * @return bool|\WP_Error
	 */
	public function process_refund($order_id, $amount = null, $reason = '')
	{
		$order = wc_get_order($order_id);

		if (!$order instanceof \WC_Order) {
			return new \WP_Error('robokassa_refund_order_not_found', 'Заказ для возврата не найден.');
		}

		if (get_option('robokassa_country_code', 'RU') !== 'RU') {
			return new \WP_Error('robokassa_refund_country_unsupported', 'API возвратов доступно только для Robokassa Россия.');
		}

		if (get_option('robokassa_payment_test_onoff') === 'true') {
			return new \WP_Error('robokassa_refund_test_unsupported', 'API возвратов Robokassa не поддерживает тестовые платежи.');
		}

		$amount = $amount === null ? (float)$order->get_total() : (float)$amount;
		if ($amount <= 0 || $amount > (float)$order->get_total()) {
			return new \WP_Error('robokassa_refund_invalid_amount', 'Указана некорректная сумма возврата Robokassa.');
		}

		$api = $this->getRefundApi();
		$wooRefund = $this->getMatchingWooRefund($order, $amount, $reason);
		if ($wooRefund && (string)$wooRefund->get_meta('_robokassa_refund_request_id', true) !== '') {
			// Повторный вызов WooCommerce для уже отправленного возврата.
			return true;
		}
		$operationKey = (string)$order->get_meta('_robokassa_operation_key', true);

		if ($operationKey === '') {
			$operationKey = $api->getOperationKey($order_id);
			if (is_wp_error($operationKey)) {
				return $operationKey;
			}
			$order->update_meta_data('_robokassa_operation_key', $operationKey);
			$order->save();
		}

		$invoiceItems = $this->getRefundInvoiceItems($order, $amount, $wooRefund);
		$fingerprint = hash('sha256', implode('|', array(
			(int)$order_id,
			wc_format_decimal($amount, 2),
			(string)$reason,
			wp_json_encode($invoiceItems),
		)));
		$uncertain = $order->get_meta('_robokassa_refund_uncertain', true);
		if (is_array($uncertain)
			&& ($uncertain['fingerprint'] ?? '') === $fingerprint
			&& (int)($uncertain['expires_at'] ?? 0) > time()
		) {
			return new \WP_Error(
				'robokassa_refund_submission_uncertain',
				'Предыдущая отправка этого возврата завершилась без однозначного ответа. Проверьте возврат в личном кабинете Robokassa перед повтором.'
			);
		}

		$lockKey = 'robokassa_refund_lock_' . substr($fingerprint, 0, 32);
		if (!add_option($lockKey, time(), '', false)) {
			$lockCreated = (int)get_option($lockKey, 0);
			if ($lockCreated > time() - 5 * MINUTE_IN_SECONDS) {
				return new \WP_Error('robokassa_refund_locked', 'Этот возврат уже отправляется в Robokassa.');
			}
			delete_option($lockKey);
			if (!add_option($lockKey, time(), '', false)) {
				return new \WP_Error('robokassa_refund_locked', 'Не удалось установить блокировку возврата Robokassa.');
			}
		}
		$refundSum = $amount;
		if (method_exists($order, 'get_total_refunded')) {
			$previouslyRefunded = max(0, (float)$order->get_total_refunded() - $amount);
			$remainingBeforeRequest = max(0, (float)$order->get_total() - $previouslyRefunded);
			if (abs($remainingBeforeRequest - $amount) <= 0.005) {
				// Для полного возврата документация требует не передавать RefundSum.
				$refundSum = null;
			}
		}
		$result = $api->create($operationKey, $refundSum, $invoiceItems);
		delete_option($lockKey);

		if (is_wp_error($result)) {
			$errorData = $result->get_error_data();
			$status = is_array($errorData) && isset($errorData['status']) ? (int)$errorData['status'] : 0;
			$definitelyRejected = in_array($result->get_error_code(), array(
				'robokassa_refund_password3_missing',
				'robokassa_refund_credentials_missing',
			), true);
			$responseCouldBeAccepted = $status === 0 || $status >= 500 || ($status >= 200 && $status < 300);
			if (!$definitelyRejected && $responseCouldBeAccepted) {
				$order->update_meta_data('_robokassa_refund_uncertain', array(
					'fingerprint' => $fingerprint,
					'expires_at' => time() + HOUR_IN_SECONDS,
					'error' => $result->get_error_message(),
				));
				$order->add_order_note('Robokassa: результат отправки возврата неизвестен. Перед повтором проверьте личный кабинет, чтобы не вернуть деньги дважды.');
				$order->save();
			}
			return $result;
		}

		if (empty($result['success']) || empty($result['requestId'])) {
			$message = !empty($result['message']) ? (string)$result['message'] : 'Robokassa отклонила заявку на возврат.';
			return new \WP_Error('robokassa_refund_rejected', $message);
		}

		$requestId = sanitize_text_field($result['requestId']);
		$requests = $order->get_meta('_robokassa_refund_requests', true);
		$requests = is_array($requests) ? $requests : array();
		$requests[$requestId] = array(
			'amount' => $amount,
			'reason' => sanitize_text_field($reason),
			'refund_id' => $wooRefund ? (int)$wooRefund->get_id() : 0,
			'is_full' => $refundSum === null,
			'status' => 'processing',
			'attempts' => 0,
			'created_at' => current_time('mysql'),
		);
		$order->update_meta_data('_robokassa_refund_requests', $requests);
		if (method_exists($order, 'delete_meta_data')) {
			$order->delete_meta_data('_robokassa_refund_uncertain');
		}
		if ($wooRefund) {
			$wooRefund->update_meta_data('_robokassa_refund_request_id', $requestId);
			$wooRefund->update_meta_data('_robokassa_refund_status', 'processing');
			$wooRefund->save();
		}
		$order->add_order_note(sprintf(
			'Robokassa: заявка на возврат %s принята. ID заявки: %s%s',
			wc_format_decimal($amount, 2),
			$requestId,
			empty($invoiceItems) ? ' (без формирования фискального чека)' : ''
		));
		$order->save();

		if (function_exists('robokassa_schedule_refund_status_check')) {
			robokassa_schedule_refund_status_check($order_id, $requestId, MINUTE_IN_SECONDS);
		}

		return true;
	}

	/**
	 * Показывает автоматический возврат только когда Refund API настроено.
	 *
	 * @param \WC_Order $order
	 * @return bool
	 */
	public function can_refund_order($order)
	{
		return parent::can_refund_order($order)
			&& get_option('robokassa_country_code', 'RU') === 'RU'
			&& get_option('robokassa_payment_test_onoff') !== 'true'
			&& (string)get_option('robokassa_payment_shoppass3') !== '';
	}

	/** @return RefundApi */
	protected function getRefundApi()
	{
		return new RefundApi(
			get_option('robokassa_payment_MerchantLogin'),
			get_option('robokassa_payment_shoppass2'),
			get_option('robokassa_payment_shoppass3')
		);
	}

	/**
	 * Формирует чек только когда WooCommerce уже создал позиции возврата и их
	 * сумма точно совпадает с суммой запроса. Иначе API выполняет денежный возврат
	 * без чека — это безопаснее, чем фискализировать угаданный состав товаров.
	 *
	 * @param \WC_Order $order
	 * @param float     $amount
	 * @return array
	 */
	protected function getRefundInvoiceItems($order, $amount, $refund = null)
	{
		if (!$refund) {
			$refund = $this->getMatchingWooRefund($order, $amount, '');
		}
		if (!$refund) {
			return array();
		}

		$items = array();
		$total = 0.0;
		foreach ($refund->get_items('line_item') as $item) {
				$quantity = abs((float)$item->get_quantity());
				$lineTotal = abs((float)$item->get_total());
				if (method_exists($item, 'get_total_tax')) {
					$lineTotal += abs((float)$item->get_total_tax());
				}
				if ($quantity <= 0 || $lineTotal <= 0) {
					continue;
				}

				$original = null;
				if (method_exists($order, 'get_item') && method_exists($item, 'get_meta')) {
					$original = $order->get_item(absint($item->get_meta('_refunded_item_id', true)));
				}
				$tax = ($original && function_exists('robokassa_payment_get_item_tax'))
					? robokassa_payment_get_item_tax($original)
					: get_option('robokassa_payment_tax', 'none');
				$paymentObject = ($original && function_exists('robokassa_payment_get_item_payment_object'))
					? robokassa_payment_get_item_payment_object($original)
					: get_option('robokassa_payment_paymentObject', 'commodity');

				$items[] = array(
					'Name' => method_exists($item, 'get_name') ? (string)$item->get_name() : 'Товар',
					'Quantity' => $quantity,
					'Cost' => round($lineTotal / $quantity, 2),
					'Tax' => $tax ?: 'none',
					'PaymentMethod' => 'full_payment',
					'PaymentObject' => $paymentObject ?: 'commodity',
				);
			$total += $lineTotal;
		}

		foreach (array('shipping', 'fee') as $type) {
			foreach ($refund->get_items($type) as $item) {
				$lineTotal = abs((float)$item->get_total());
				if (method_exists($item, 'get_total_tax')) {
					$lineTotal += abs((float)$item->get_total_tax());
				}
				if ($lineTotal <= 0) {
					continue;
				}
				$items[] = array(
					'Name' => method_exists($item, 'get_name') ? (string)$item->get_name() : ($type === 'shipping' ? 'Доставка' : 'Доплата'),
					'Quantity' => 1,
					'Cost' => round($lineTotal, 2),
					'Tax' => get_option('robokassa_payment_tax', 'none') ?: 'none',
					'PaymentMethod' => 'full_payment',
					'PaymentObject' => $type === 'shipping'
						? (get_option('robokassa_payment_paymentObject_shipping') ?: 'service')
						: (get_option('robokassa_payment_paymentObject') ?: 'commodity'),
				);
				$total += $lineTotal;
			}
		}

		return !empty($items) && abs($total - $amount) <= 0.01 ? $items : array();
	}

	/**
	 * Находит связанный WooCommerce refund; WooCommerce сохраняет его до вызова gateway API.
	 *
	 * @param \WC_Order $order
	 * @param float     $amount
	 * @param string    $reason
	 * @return object|null
	 */
	protected function getMatchingWooRefund($order, $amount, $reason)
	{
		if (method_exists($order, 'get_refunds')) {
			$refunds = $order->get_refunds();
		} elseif (function_exists('wc_get_order_refunds')) {
			// Совместимость со старыми версиями WooCommerce.
			$refunds = wc_get_order_refunds($order->get_id());
		} else {
			$refunds = array();
		}

		foreach ($refunds as $refund) {
			if (!method_exists($refund, 'get_amount') || abs((float)$refund->get_amount() - $amount) > 0.005) {
				continue;
			}
			if ($reason !== '' && method_exists($refund, 'get_reason') && (string)$refund->get_reason() !== (string)$reason) {
				continue;
			}

			return $refund;
		}

		return null;
	}

}
