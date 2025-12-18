<?php
/**
 * Plugin Name: Robokassa WooCommerce
 * Description: Данный плагин добавляет на Ваш сайт метод оплаты Робокасса для WooCommerce
 * Plugin URI: /wp-admin/admin.php?page=main_settings_rb.php
 * Author: Robokassa
 * Author URI: https://robokassa.com
 * Version: 1.8.4
 */

require_once('payment-widget.php');
require_once('StatusReporter.php');

use Robokassa\Payment\RoboDataBase;
use Robokassa\Payment\RobokassaPayAPI;
use Robokassa\Payment\RobokassaSms;
use Robokassa\Payment\Util;
use Robokassa\Payment\AgentManager;
use Robokassa\Payment\TaxManager;
use Automattic\WooCommerce\Utilities\OrderUtil;
use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;

define('ROBOKASSA_PAYMENT_DEBUG_STATUS', false);

spl_autoload_register(
	function ($className) {
		$file = __DIR__ . '/classes/' . str_replace('\\', '/', $className) . '.php';

		if (file_exists($file))
			require_once $file;
	}
);

add_action('woocommerce_cart_calculate_fees', 'robokassa_chosen_payment_method');
add_action('wp_enqueue_scripts', 'robokassa_enqueue_frontend_assets');

if (!function_exists('robokassa_chosen_payment_method')) {
	/**
	 * Добавляет наценку для выбранных методов Robokassa.
	 *
	 * @param WC_Cart $cart
	 * @return void
	 */
	function robokassa_chosen_payment_method(WC_Cart $cart)
	{
		if (
			(double)get_option('robokassa_patyment_markup') > 0
			&& in_array(
				WC()->session->get('chosen_payment_method'),
				array_map(
					function ($class) {
						$method = new $class;
						return $method->id;
					},
					robokassa_payment_add_WC_WP_robokassa_class()
				)
			)
		) {
			$cart->add_fee(
				'Наценка',
				$cart->get_cart_contents_total() / 100 * (double)get_option('robokassa_patyment_markup'),
				false
			);
		}
	}
}

add_action('woocommerce_review_order_before_payment', 'refresh_payment_methods');
add_action('woocommerce_product_options_general_product_data', 'robokassa_payment_render_product_tax_field');
add_action('woocommerce_product_options_general_product_data', 'robokassa_payment_render_product_agent_fields');
add_action('woocommerce_admin_process_product_object', 'robokassa_payment_save_product_tax_field');
add_action('woocommerce_admin_process_product_object', 'robokassa_payment_save_product_agent_fields');
function refresh_payment_methods()
{
	// jQuery code
	?>
	<script type="text/javascript">
		(function ($) {
			$('form.checkout').on('change', 'input[name^="payment_method"]', function () {
				$('body').trigger('update_checkout');
			});
		})(jQuery);
	</script>
	<?php
}

function robokassa_enqueue_frontend_assets()
{
	if (!function_exists('is_checkout') || !is_checkout()) {
		return;
	}
	$stylePath = plugin_dir_path(__FILE__) . 'assets/css/robokassa-redirect.css';
	$scriptPath = plugin_dir_path(__FILE__) . 'assets/js/robokassa-redirect.js';
	if (file_exists($stylePath)) {
		wp_enqueue_style(
			'robokassa-redirect',
			plugins_url('assets/css/robokassa-redirect.css', __FILE__),
			array(),
			filemtime($stylePath)
		);
	}
	if (!file_exists($scriptPath)) {
		return;
	}
	wp_enqueue_script(
		'robokassa-redirect',
		plugins_url('assets/js/robokassa-redirect.js', __FILE__),
		array(),
		filemtime($scriptPath),
		true
	);
	$config = robokassa_prepare_redirect_config();
	if ($config !== null) {
		wp_localize_script('robokassa-redirect', 'robokassaRedirectConfig', $config);
	}
}

/**
 * Готовит конфигурацию для проверки статуса заказа при iframe-оплате.
 *
 * @return array|null
 */
function robokassa_prepare_redirect_config()
{
	if (get_option('robokassa_iframe') != 1 || !function_exists('is_checkout_pay_page') || !is_checkout_pay_page()) {
		return null;
	}
	$order_id = absint(get_query_var('order-pay'));
	$order_key = isset($_GET['key']) ? sanitize_text_field(wp_unslash($_GET['key'])) : '';
	if ($order_id <= 0 || $order_key === '') {
		return null;
	}
	$order = wc_get_order($order_id);
	if (!$order instanceof \WC_Order || $order->get_order_key() !== $order_key) {
		return null;
	}
	return array(
		'ajaxUrl' => admin_url('admin-ajax.php'),
		'orderId' => $order_id,
		'orderKey' => $order_key,
		'successUrl' => $order->get_checkout_order_received_url(),
		'checkInterval' => 5000,
		'maxAttempts' => 120,
	);
}

/**
 * Возвращает статус заказа для перенаправления после iframe-оплаты.
 *
 * @return void
 */
function robokassa_check_order_status()
{
	$order_id = isset($_POST['orderId']) ? absint($_POST['orderId']) : 0;
	$order_key = isset($_POST['orderKey']) ? sanitize_text_field(wp_unslash($_POST['orderKey'])) : '';

	if ($order_id <= 0 || $order_key === '') {
		wp_send_json_error(array('message' => 'invalid_request'));
	}

	$order = wc_get_order($order_id);

	if (!$order instanceof \WC_Order || $order->get_order_key() !== $order_key) {
		wp_send_json_error(array('message' => 'invalid_order'));
	}

	wp_send_json_success(array(
		'paid' => $order->is_paid(),
		'status' => $order->get_status(),
	));
}

add_action('admin_menu', 'robokassa_payment_initMenu'); // Хук для добавления страниц плагина в админку
add_action('plugins_loaded', 'robokassa_payment_initWC'); // Хук инициализации плагина робокассы
add_action('parse_request', 'robokassa_payment_wp_robokassa_checkPayment'); // Хук парсера запросов
add_action('woocommerce_order_status_completed', 'robokassa_payment_smsWhenCompleted'); // Хук статуса заказа = "Выполнен"

add_action('woocommerce_order_status_changed', 'robokassa_2check_send', 10, 3);
add_action('woocommerce_order_status_changed', 'robokassa_hold_confirm', 10, 4);
add_action('woocommerce_order_status_changed', 'robokassa_hold_cancel', 10, 4);
add_action('robokassa_cancel_payment_event', 'robokassa_hold_cancel_after5', 10, 1);
add_action('wp_ajax_robokassa_check_order_status', 'robokassa_check_order_status');
add_action('wp_ajax_nopriv_robokassa_check_order_status', 'robokassa_check_order_status');

register_activation_hook(__FILE__, 'robokassa_payment_wp_robokassa_activate'); //Хук при активации плагина. Дефолтовые настройки и таблица в БД для СМС.

add_filter('woocommerce_get_privacy_policy_text', 'robokassa_get_privacy_policy_text', 10, 2);

function robokassa_get_privacy_policy_text($text, $type)
{
	if (function_exists('wcs_order_contains_subscription')) {
		$textAlt = sprintf(
			get_option('robokassa_agreement_text'),
			get_option('robokassa_agreement_pd_link'),
			get_option('robokassa_agreement_oferta_link')
		);

		$text = $textAlt ?: $text;
	}

	return $text;
}

/**
 * @param string $str
 */
function robokassa_payment_DEBUG($str)
{

	/** @var string $file */
	$file = __DIR__ . '/data/robokassa_DEBUG.txt';

	$time = time();
	$DEBUGFile = fopen($file, 'a+');
	fwrite($DEBUGFile, date('d.m.Y H:i:s', $time + 10800) . " ($time) : $str\r\n");
	fclose($DEBUGFile);
}

/**
 * Возвращает сервис управления налоговыми ставками.
 *
 * @return TaxManager
 */
function robokassa_payment_get_tax_manager()
{
	global $robokassa_payment_tax_manager;

	if (!$robokassa_payment_tax_manager instanceof TaxManager) {
		$robokassa_payment_tax_manager = new TaxManager();
	}

	return $robokassa_payment_tax_manager;
}

/**
 * Возвращает сервис управления агенскими полями товаров.
 *
 * @return AgentManager
 */
function robokassa_payment_get_agent_manager()
{
	global $robokassa_payment_agent_manager;

	if (!$robokassa_payment_agent_manager instanceof AgentManager) {
		$robokassa_payment_agent_manager = new AgentManager();
	}

	return $robokassa_payment_agent_manager;
}

/**
 * Вычисляет сумму налога для передачи в Робокассу.
 *
 * @param string    $tax
 * @param float|int $amount
 *
 * @return float
 */
function calculate_tax_sum($tax, $amount)
{
	return robokassa_payment_get_tax_manager()->calculateTaxSum($tax, $amount);
}

/**
 * Возвращает налоговую ставку по умолчанию.
 *
 * @return string
 */
function robokassa_payment_get_default_tax()
{
	return robokassa_payment_get_tax_manager()->getDefaultTax();
}

/**
 * Определяет налоговую ставку для позиции заказа.
 *
 * @param \WC_Order_Item $item
 *
 * @return string
 */
function robokassa_payment_get_item_tax($item)
{
	return robokassa_payment_get_tax_manager()->getItemTax($item);
}

/**
 * Отрисовывает поле выбора налоговой ставки в карточке товара.
 *
 * @return void
 */
function robokassa_payment_render_product_tax_field()
{
	robokassa_payment_get_tax_manager()->renderProductTaxField();
}

/**
 * Отрисовывает поля агента в карточке товара.
 *
 * @return void
 */
function robokassa_payment_render_product_agent_fields()
{
	robokassa_payment_get_agent_manager()->renderProductAgentFields();
}

/**
 * Сохраняет выбранную налоговую ставку товара.
 *
 * @param \WC_Product $product
 *
 * @return void
 */
function robokassa_payment_save_product_tax_field($product)
{
	robokassa_payment_get_tax_manager()->saveProductTaxField($product);
}

/**
 * Сохраняет поля агента в карточке товара.
 *
 * @param \WC_Product $product
 *
 * @return void
 */
function robokassa_payment_save_product_agent_fields($product)
{
	robokassa_payment_get_agent_manager()->saveProductAgentFields($product);
}

/**
 * @param mixed $order_id
 * @param string $debug
 *
 * @return void
 */
function robokassa_payment_smsWhenCompleted($order_id, $debug = '')
{
	//Отправка СМС-2 если необходимо
	$mrhLogin = get_option('robokassa_payment_MerchantLogin');

	if (get_option('robokassa_payment_test_onoff') == 'true') {
		$pass1 = get_option('robokassa_payment_testshoppass1');
		$pass2 = get_option('robokassa_payment_testshoppass2');
	} else {
		$pass1 = get_option('robokassa_payment_shoppass1');
		$pass2 = get_option('robokassa_payment_shoppass2');
	}

	$debug .= "pass1 = $pass1 \r\n";
	$debug .= "pass2 = $pass2 \r\n";

	if (get_option('robokassa_payment_sms2_enabled') == 'on') {
		$debug .= "Условие СМС-2 верно! \r\n";

		$order = wc_get_order($order_id);

		$phone = $order->billing_phone;
		$debug .= "phone = $phone \r\n";

		$message = get_option('robokassa_payment_sms2_text');
		$debug .= "message = $message \r\n";

		$translit = (get_option('robokassa_payment_sms_translit') == 'on');
		$debug .= "translit = $translit \r\n";
		$debug .= "order_id = $order_id \r\n";

		global $wpdb;
		$roboDataBase = new RoboDataBase($wpdb);
		$robokassa = new RobokassaPayAPI($mrhLogin, get_option('robokassa_payment_shoppass1'), get_option('robokassa_payment_shoppass2'));

		$sms = new RobokassaSms($roboDataBase, $robokassa, $phone, $message, $translit, $order_id, 2);
		$sms->send();
	}
}

/**
 * @param string $debug
 *
 * @return void
 */
function robokassa_payment_wp_robokassa_activate($debug)
{
	add_option('robokassa_payment_wc_robokassa_enabled', 'no');
	add_option('robokassa_payment_test_onoff', 'false');
	add_option('robokassa_payment_type_commission', 'true');
	add_option('robokassa_payment_tax', 'none');
	add_option('robokassa_payment_tax_source', 'global');
	add_option('robokassa_payment_sno', 'fckoff');
	add_option('robokassa_payment_who_commission', 'shop');
	add_option('robokassa_payment_paytype', 'false');
	add_option('robokassa_payment_SuccessURL', 'wc_success');
	add_option('robokassa_payment_FailURL', 'wc_checkout');
}

/**
 * @return void
 */
function robokassa_payment_initMenu()
{
	add_submenu_page('woocommerce', 'Настройки Робокассы', 'Настройки Робокассы', 'edit_pages', 'robokassa_payment_main_settings_rb', 'robokassa_payment_main_settings');
	add_submenu_page('main_settings_rb.php', 'Основные настройки', 'Основные настройки', 'edit_pages', 'robokassa_payment_main_rb', 'robokassa_payment_main_settings');
	add_submenu_page('main_settings_rb.php', 'Настройки СМС', 'Настройки СМС', 'edit_pages', 'robokassa_payment_sms_rb', 'robokassa_payment_sms_settings');
	add_submenu_page('main_settings_rb.php', 'Генерировать YML', 'Генерировать YML', 'edit_pages', 'robokassa_payment_YMLGenerator', 'robokassa_payment_yml_generator');
	add_submenu_page('main_settings_rb.php', 'Регистрация', 'Регистрация', 'edit_pages', 'robokassa_payment_registration', 'robokassa_payment_reg');
	add_submenu_page('main_settings_rb.php', 'Виджет и бейдж Robokassa', 'Виджет и бейдж Robokassa', 'edit_pages', 'robokassa_payment_credit', 'robokassa_payment_credit');
}

/**
 * @param string $name
 * @param mixed $order_id
 *
 * @return string
 */
function robokassa_payment_get_success_fail_url($name, $order_id)
{
	$order = new WC_Order($order_id);

	switch ($name) {
		case 'wc_success':
			return $order->get_checkout_order_received_url();
		case 'wc_checkout':
			return $order->get_view_order_url();
		case 'wc_payment':
			return $order->get_checkout_payment_url();
		default:
			return get_page_link(get_option($name));
	}
}

/**
 * @return void
 */
function robokassa_payment_wp_robokassa_checkPayment()
{

	if (isset($_REQUEST['robokassa'])) {

		/** @var string $returner */
		$returner = '';

		$order_status = get_option('robokassa_payment_order_status_after_payment');
		if (empty($order_status)) {
			$order_status = 'wc-processing';
		}

		if ($_REQUEST['robokassa'] === 'result') {

			/** @var string $crc_confirm */
			$crc_confirm = strtoupper(
				md5(
					implode(
						':',
						[
							$_REQUEST['OutSum'],
							$_REQUEST['InvId'],
							(
							(get_option('robokassa_payment_test_onoff') == 'true')
								? get_option('robokassa_payment_testshoppass2')
								: get_option('robokassa_payment_shoppass2')
							),
							'shp_label=official_wordpress',
							'Shp_merchant_id=' . get_option('robokassa_payment_MerchantLogin'),
							'Shp_order_id=' . $_REQUEST['InvId'],
							'Shp_result_url=' . (Util::siteUrl('/?robokassa=result'))
						]
					)
				)
			);

			if ($crc_confirm == $_REQUEST['SignatureValue']) {

				$order = new WC_Order($_REQUEST['InvId']);
				$order->add_order_note('Заказ успешно оплачен!');
				$order->update_status(str_replace('wc-', '', $order_status));

				global $woocommerce;
				$woocommerce->cart->empty_cart();

				if (function_exists('wcs_order_contains_subscription')) {
					$subscriptions = wcs_get_subscriptions_for_order($_REQUEST['InvId']) ?: wcs_get_subscriptions_for_renewal_order($_REQUEST['InvId']);

					if ($subscriptions == true) {
						foreach ($subscriptions as $subscription) {
							$subscription->update_status('active');
						}
					}
				}

				$returner = 'OK' . $_REQUEST['InvId'];

				if (get_option('robokassa_payment_sms1_enabled') == 'on') {

					try {
						global $wpdb;

						(new RobokassaSms(
							(new RoboDataBase($wpdb)),
							(new RobokassaPayAPI(
								get_option('robokassa_payment_MerchantLogin'),
								get_option('robokassa_payment_shoppass1'),
								get_option('robokassa_payment_shoppass2')
							)
							),
							$order->billing_phone,
							get_option('robokassa_payment_sms1_text'),
							(get_option('robokassa_payment_sms_translit') == 'on'),
							$_REQUEST['InvId'],
							1
						))->send();
					} catch (Exception $e) {
					}
				}
			} elseif ((int)get_option('robokassa_payment_hold_onoff') === 1 &&
				strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {

				$input_data = file_get_contents('php://input');


				$token_parts = explode('.', $input_data);


				if (count($token_parts) === 3) {

					$json_data = json_decode(base64_decode($token_parts[1]), true);

					// Проверяем наличие ключевого поля "state" со значением "HOLD"
					if (isset($json_data['data']['state']) && $json_data['data']['state'] === 'HOLD') {

						$order = new WC_Order($json_data['data']['invId']);
						$date_in_five_days = date('Y-m-d H:i:s', strtotime('+5 days'));
						$order->add_order_note("Robokassa: Платеж успешно подтвержден. Он ожидает подтверждения до {$date_in_five_days}, после чего автоматически отменится");
						$order->update_status('on-hold');


						wp_schedule_single_event(strtotime('+5 days'), 'robokassa_cancel_payment_event', array($order->get_id()));
					}
					if (isset($json_data['data']['state']) && $json_data['data']['state'] === 'OK') {

						$order = new WC_Order($json_data['data']['invId']);
						$order->add_order_note("Robokassa: Платеж успешно подтвержден");
						$order->update_status('processing');

					}
					http_response_code(200);
				} else {
					http_response_code(400);
				}
			} else {
				$returner = 'BAD SIGN';

				try {
					$order = new WC_Order($_REQUEST['InvId']);
					$order->add_order_note('Bad CRC '. $crc_confirm .' . '. $_REQUEST['SignatureValue']);
					$order->update_status('failed');
				} catch (Exception $e) {}
			}
		}

		if ($_REQUEST['robokassa'] == 'success') {
			header('Location:' . robokassa_payment_get_success_fail_url(get_option('robokassa_payment_SuccessURL'), $_REQUEST['InvId']));
			die;
		}

		if ($_REQUEST['robokassa'] == 'fail') {
			header('Location:' . robokassa_payment_get_success_fail_url(get_option('robokassa_payment_FailURL'), $_REQUEST['InvId']));
			die;
		}
		echo $returner;
		die;
	}
}

// Подготовка строки перед кодированием в base64
function formatSignReplace($string)
{
	return strtr(
		$string,
		[
			'+' => '-',
			'/' => '_',
		]
	);
}

// Подготовка строки после кодирования в base64
function formatSignFinish($string)
{
	return preg_replace('/^(.*?)(=*)$/', '$1', $string);
}

/**
 *
 * Проверка режимы работы
 *
 * @return array
 */
function getRobokassaPasses()
{
	if (get_option('robokassa_payment_test_onoff') == 'true') {
		return [
			'pass1' => get_option('robokassa_payment_testshoppass1'),
			'pass2' => get_option('robokassa_payment_testshoppass2'),
		];
	}

	return [
		'pass1' => get_option('robokassa_payment_shoppass1'),
		'pass2' => get_option('robokassa_payment_shoppass2'),
	];
}

/**
 * Подготовка товарной номенклатуры для формирования чека
 *
 * @param mixed $order_id
 *
 * @return array
 */
function createRobokassaReceipt($order_id)
{
	global $woocommerce;
	$order = new WC_Order($order_id);

	$sno = get_option('robokassa_payment_sno');
	$default_tax = robokassa_payment_get_default_tax();
	$country = get_option('robokassa_country_code');

	$receipt = array(
		'sno' => $sno,
	);

	$total_order = $order->get_total();
	$total_receipt = 0;

	/**
	 * @var \WC_Order_Item_Product $item
	 */
	foreach ($order->get_items() as $item) {
		$product = $item->get_product();

		$current = array();
		$current['name'] = $product->get_title();
		$current['quantity'] = $item->get_quantity();
		$current['sum'] = wc_format_decimal($item->get_total(), get_option('woocommerce_price_num_decimals'));
		$current['cost'] = wc_format_decimal($item->get_total(), get_option('woocommerce_price_num_decimals')) / $item->get_quantity();

		$total_receipt += $current['sum'];
		$item_tax = robokassa_payment_get_item_tax($item);

		if ($country == 'RU') {
			$current['payment_object'] = get_option('robokassa_payment_paymentObject');
			$current['payment_method'] = get_option('robokassa_payment_paymentMethod');
		}

		if (($sno == 'osn') || $country == 'RU') {
			$current['tax'] = $item_tax;
		} else {
			$current['tax'] = 'none';
		}

		$agentData = robokassa_payment_get_agent_manager()->getItemAgentData($item);

		if (!empty($agentData)) {
			if (isset($agentData['agent_info'])) {
				$current['agent_info'] = $agentData['agent_info'];
			}

			if (isset($agentData['supplier_info'])) {
				$current['supplier_info'] = $agentData['supplier_info'];
			}
		}

		$receipt['items'][] = $current;
	}

	foreach ($order->get_items('fee') as $fee_item) {
		$additional_item_name = $fee_item->get_name();
		$additional_item_total = (float)$fee_item->get_total();

		$additional_item_data = array(
			'name' => $additional_item_name,
			'quantity' => $fee_item->get_quantity(),
			'sum' => wc_format_decimal($additional_item_total, get_option('woocommerce_price_num_decimals')),
			'cost' => wc_format_decimal($additional_item_total, get_option('woocommerce_price_num_decimals')) / $fee_item->get_quantity(),
			'payment_object' => get_option('robokassa_payment_paymentObject'),
			'payment_method' => get_option('robokassa_payment_paymentMethod'),
		);

		if (($sno == 'osn') || $country == 'RU') {
			$additional_item_data['tax'] = $default_tax;
		} else {
			$additional_item_data['tax'] = 'none';
		}

		$receipt['items'][] = $additional_item_data;
		$total_receipt += $additional_item_total;
	}

	if ((double)$order->get_shipping_total() > 0) {
		$current = array();
		$current['name'] = 'Доставка';
		$current['quantity'] = 1;
		$current['cost'] = (double)sprintf('%01.2f', $order->get_shipping_total());
		$current['sum'] = (double)sprintf('%01.2f', $order->get_shipping_total());

		if ($country == 'RU') {
			$current['payment_object'] = get_option('robokassa_payment_paymentObject_shipping') ?: get_option('robokassa_payment_paymentObject');
			$current['payment_method'] = get_option('robokassa_payment_paymentMethod');
		}

		if (($sno == 'osn') || ($country != 'KZ')) {
			$current['tax'] = $default_tax;
		} else {
			$current['tax'] = 'none';
		}

		$receipt['items'][] = $current;
		$total_receipt += $current['cost'];
	}

	if ($total_receipt != $total_order) {
		robokassa_payment_DEBUG('Robokassa: общая сумма чека (' . $total_receipt . ') НЕ совпадает с общей суммой заказа (' . $total_order . ')');
	}

	return apply_filters('wc_robokassa_receipt', $receipt);
}

/**
 * Формирование формы, перенаправляющей пользователя на сайт робокассы
 *
 * Включает в себя подготовку данных и рендеринг самой формы
 *
 * @param mixed $order_id
 * @param       $label
 *
 * @return void
 */
function processRobokassaPayment($order_id, $label)
{
	$mrhLogin = get_option('robokassa_payment_MerchantLogin');
	$passes = getRobokassaPasses();
	$order = wc_get_order($order_id);
	$receipt = createRobokassaReceipt($order_id);

	$rb = new RobokassaPayAPI($mrhLogin, $passes['pass1'], $passes['pass2']);

	$order_total = $order->get_total();
	$sum = number_format($order_total, 2, '.', '');

	$invDesc = "Заказ номер $order_id";

	$recurring = false;

	if (function_exists('wcs_order_contains_subscription')) {
		$order_subscription = wcs_order_contains_subscription($order_id);

		if ($order_subscription) {
			$recurring = true;
		}
	}

	echo $rb->createForm(
		$sum,
		$order_id,
		$invDesc,
		get_option('robokassa_payment_test_onoff'),
		$label,
		$receipt,
		$order->get_billing_email(),
		$recurring
	);
}

function robokassa_payment_createFormWC($order_id, $label)
{
	static $rendered = array();

	if ($order_id instanceof \WC_Order) {
		$order_key = (string) $order_id->get_id();
	} else {
		$order_key = (string) $order_id;
	}

	$unique_key = $label . '|' . $order_key;

	if (isset($rendered[$unique_key])) {
		return;
	}

	$rendered[$unique_key] = true;

	processRobokassaPayment($order_id, $label);
}

function robokassa_payment_initWC()
{
	if (!defined('ABSPATH')) {
		exit;
	}

	if (!class_exists(Robokassa\Payment\WC_WP_robokassa::class))
		return;

	require 'labelsClasses.php';

	add_filter('woocommerce_payment_gateways', 'robokassa_payment_add_WC_WP_robokassa_class');
}


/**
 * @return void
 */
function robokassa_payment_main_settings()
{
	$_GET['li'] = 'main';
	include 'menu_rb.php';
	include 'main_settings_rb.php';
}

/**
 * @return void
 */
function robokassa_payment_sms_settings()
{
	$_GET['li'] = 'sms';
	include 'menu_rb.php';
	include 'sms_settings_rb.php';
}

/**
 * @return void
 */
function robokassa_payment_reg()
{
	$_GET['li'] = 'registration';
	include 'menu_rb.php';
	include 'main_settings_registration.php';
}

/**
 * @return void
 */
function robokassa_payment_credit()
{
	if (get_option('robokassa_country_code', 'RU') === 'KZ') {
		wp_safe_redirect(admin_url('admin.php?page=robokassa_payment_main_rb'));
		exit;
	}

	$_GET['li'] = 'credit';
	include 'menu_rb.php';
	include 'main_settings_credit.php';
}

/**
 * @return void
 */
function robokassa_payment_oferta()
{
	$_GET['li'] = 'offer';
	include 'menu_rb.php';
	include 'main_settings_offer.php';
}

/**
 * Возвращает префикс таблиц в базе данных
 *
 * @return string
 *
 * @throws Exception
 */
function robokassa_payment_getDbPrefix()
{
	global $wpdb;

	if ($wpdb instanceof wpdb) {
		return $wpdb->prefix;
	}

	throw new Exception('Объект типа "wpdb" не найден в глобальном пространстве имен по имени "$wpdb"');
}

if (!function_exists('getallheaders')) {

	/**
	 * Возвращает заголовки http-запроса
	 *
	 * Не во всех окружениях эта функция есть, а для работы модуля она необходима
	 *
	 * @return array
	 */
	function getallheaders()
	{
		static $headers = null;

		if (null === $headers) {
			$headers = array();

			foreach ($_SERVER as $name => $value) {
				if (substr($name, 0, 5) == 'HTTP_') {
					$headers[str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($name, 5)))))] = $value;
				}
			}
		}

		return $headers;
	}
}

/**
 * @return void
 */
function robokassa_2check_send($order_id, $old_status, $new_status)
{

	$payment_method = get_option('robokassa_payment_paymentMethod');
	$sno = get_option('robokassa_payment_sno');
	$default_tax = robokassa_payment_get_default_tax();
	$country = get_option('robokassa_country_code');
	$payment_object = get_option('robokassa_payment_paymentObject');
	$second_check_payment_object = get_option('robokassa_payment_second_check_paymentObject');

	if (empty($second_check_payment_object)) {
		$second_check_payment_object = $payment_object;
	}

	if ($payment_method == 'advance' || $payment_method == 'full_prepayment' || $payment_method == 'prepayment') {
		if ($sno == 'fckoff') {
			robokassa_payment_DEBUG("Robokassa: SNO is 'fckoff', exiting function");
			return;
		}

		$trigger_status = str_replace('wc-', '', get_option('robokassa_payment_order_status_for_second_check', 'completed'));

		if ($new_status != $trigger_status) {
			robokassa_payment_DEBUG("Robokassa: New status ($new_status) does not match trigger status ($trigger_status), exiting function");
			return;
		}

		$order = new WC_Order($order_id);

		if (empty($order)) {
			robokassa_payment_DEBUG("Robokassa: Order not found for order_id: $order_id, exiting function");
			return;
		}

		/** @var array $fields */
		$fields = [
			'merchantId' => get_option('robokassa_payment_MerchantLogin'),
			'id' => $order->get_id() + 1,
			'originId' => $order->get_id(),
			'operation' => 'sell',
			'sno' => $sno,
			'url' => urlencode('http://' . $_SERVER['HTTP_HOST']),
			'total' => $order->get_total(),
			'items' => [],
			'client' => [
				'email' => $order->get_billing_email(),
				'phone' => $order->get_billing_phone(),
			],
			'payments' => [
				[
					'type' => 2,
					'sum' => $order->get_total()
				]
			],
			'vats' => []
		];

		$shipping_total = $order->get_shipping_total();

		if ($shipping_total > 0) {
			$shipping_tax = ((isset($fields['sno']) && $fields['sno'] == 'osn') || ($country != 'KZ')) ? $default_tax : 'none';

			$products_items = [
				'name' => 'Доставка',
				'quantity' => 1,
				'sum' => wc_format_decimal($shipping_total, get_option('woocommerce_price_num_decimals')),
				'cost' => wc_format_decimal($shipping_total, get_option('woocommerce_price_num_decimals')),
				'tax' => $shipping_tax,
				'payment_method' => 'full_payment',
				'payment_object' => get_option('robokassa_payment_paymentObject_shipping') ?: $payment_object,
			];

			$fields['items'][] = $products_items;

			if ($shipping_tax !== 'none') {
				$fields['vats'][] = ['type' => $shipping_tax, 'sum' => calculate_tax_sum($shipping_tax, $shipping_total)];
			}
		}

		if (is_plugin_active('woocommerce-checkout-add-ons/woocommerce-checkout-add-ons.php')) {
			$additional_items = $order->get_items('fee');

			foreach ($additional_items as $additional_item) {
				$additional_item_name = $additional_item->get_name();
				$additional_item_total = floatval($additional_item->get_total());

				$additional_tax = ((isset($fields['sno']) && $fields['sno'] == 'osn') || $country == 'RU') ? $default_tax : 'none';

				$products_items = array(
					'name' => $additional_item_name,
					'quantity' => $additional_item->get_quantity(),
					'sum' => wc_format_decimal($additional_item_total, get_option('woocommerce_price_num_decimals')),
					'cost' => wc_format_decimal($additional_item_total, get_option('woocommerce_price_num_decimals')) / $additional_item->get_quantity(),
					'payment_object' => $second_check_payment_object,
					'payment_method' => 'full_payment',
					'tax' => $additional_tax,
				);

				$fields['items'][] = $products_items;

				if ($additional_tax !== 'none') {
					$fields['vats'][] = ['type' => $additional_tax, 'sum' => calculate_tax_sum($additional_tax, $additional_item_total)];
				}
			}
		}

		foreach ($order->get_items() as $item) {
			$item_total = (float)$item->get_total();
			$item_tax_code = robokassa_payment_get_item_tax($item);
			$item_tax_to_send = ((isset($fields['sno']) && $fields['sno'] == 'osn') || $country == 'RU') ? $item_tax_code : 'none';

			$products_items = [
				'name' => $item->get_name(),
				'quantity' => $item->get_quantity(),
				'sum' => wc_format_decimal($item_total, get_option('woocommerce_price_num_decimals')),
				'tax' => $item_tax_to_send,
				'payment_method' => 'full_payment',
				'payment_object' => $second_check_payment_object,
			];

			$product = $item->get_product();

			if ($product instanceof WC_Product) {
				$sku = $product->get_sku();

				if (!empty($sku)) {
					$products_items['nomenclature_code'] = mb_convert_encoding($sku, 'UTF-8');
				}
			}

			$fields['items'][] = $products_items;

			if ($item_tax_to_send !== 'none') {
				$fields['vats'][] = ['type' => $item_tax_to_send, 'sum' => calculate_tax_sum($item_tax_to_send, $item_total)];
			}
		}

		foreach ($order->get_items('fee') as $fee_item) {
			$fee_total = (float)$fee_item->get_total();
			$fee_tax = ((isset($fields['sno']) && $fields['sno'] == 'osn') || $country == 'RU') ? $default_tax : 'none';

			$products_items = [
				'name' => $fee_item->get_name(),
				'quantity' => $fee_item->get_quantity(),
				'sum' => wc_format_decimal($fee_total, get_option('woocommerce_price_num_decimals')),
				'tax' => $fee_tax,
				'payment_method' => 'full_payment',
				'payment_object' => $second_check_payment_object,
			];

			$fields['items'][] = $products_items;

			if ($fee_tax !== 'none') {
				$fields['vats'][] = ['type' => $fee_tax, 'sum' => calculate_tax_sum($fee_tax, $fee_total)];
			}
		}

		robokassa_payment_DEBUG("Robokassa: Second check data for order_id: $order_id -> " . print_r($fields, true));

		/** @var string $startupHash */
		$startupHash = formatSignFinish(
			base64_encode(
				formatSignReplace(
					json_encode($fields)
				)
			)
		);


		if (get_option('robokassa_payment_test_onoff') == 'true') {
			$pass1 = get_option('robokassa_payment_testshoppass1');
			$pass2 = get_option('robokassa_payment_testshoppass2');
		} else {
			$pass1 = get_option('robokassa_payment_shoppass1');
			$pass2 = get_option('robokassa_payment_shoppass2');
		}


		/** @var string $sign */
		$sign = formatSignFinish(
			base64_encode(
				md5(
					$startupHash .
					($pass1)
				)
			)
		);

		$curl = curl_init('https://ws.roboxchange.com/RoboFiscal/Receipt/Attach');
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($curl, CURLOPT_POSTFIELDS, $startupHash . '.' . $sign);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER, array(
				'Content-Type: application/json',
				'Content-Length: ' . strlen($startupHash . '.' . $sign))
		);
		$result = curl_exec($curl);

		if ($result === false) {
			robokassa_payment_DEBUG("Robokassa: cURL error: " . curl_error($curl));
		} else {
			robokassa_payment_DEBUG("Robokassa: cURL result: " . $result);
		}

		curl_close($curl);
	} else {
		robokassa_payment_DEBUG("Robokassa: Payment method is not advance, full_prepayment, or prepayment, no action taken");
	}
}


function robokassa_hold_confirm($order_id, $old_status, $new_status, $order) {
	$option_value = get_option('robokassa_payment_hold_onoff');
	if (($option_value == 1)
			&& $old_status === 'on-hold' && $new_status === 'processing') {
		$order = wc_get_order($order_id);
		$shipping_total = $order->get_shipping_total();

		$receipt_items = array();
		$default_tax = robokassa_payment_get_default_tax();
		$country = get_option('robokassa_country_code');
		$sno = get_option('robokassa_payment_sno');
		$total_receipt = 0;

		foreach ($order->get_items() as $item) {
			$product = $item->get_product();

			$current = array();
			$current['name'] = $product instanceof WC_Product ? $product->get_title() : $item->get_name();
			$current['quantity'] = $item->get_quantity();
			$current['sum'] = wc_format_decimal($item->get_total(), get_option('woocommerce_price_num_decimals'));
			$current['cost'] = wc_format_decimal($item->get_total(), get_option('woocommerce_price_num_decimals')) / $item->get_quantity();

			$total_receipt += $current['sum'];

			if ($country == 'RU') {
				$current['payment_object'] = get_option('robokassa_payment_paymentObject');
				$current['payment_method'] = get_option('robokassa_payment_paymentMethod');
			}

			$item_tax = robokassa_payment_get_item_tax($item);
			if (($sno == 'osn') || $country == 'RU') {
				$current['tax'] = $item_tax;
			} else {
				$current['tax'] = 'none';
			}

			$receipt_items[] = $current;
		}

		foreach ($order->get_items('fee') as $fee_item) {
			$additional_item_name = $fee_item->get_name();
			$additional_item_total = (float)$fee_item->get_total();

			$additional_item_data = array(
				'name' => $additional_item_name,
				'quantity' => $fee_item->get_quantity(),
				'cost' => wc_format_decimal($additional_item_total, get_option('woocommerce_price_num_decimals')),
				'sum' => wc_format_decimal($additional_item_total, get_option('woocommerce_price_num_decimals')),
				'payment_object' => get_option('robokassa_payment_paymentObject'),
				'payment_method' => get_option('robokassa_payment_paymentMethod'),
			);

			if (($sno == 'osn') || $country == 'RU') {
				$additional_item_data['tax'] = $default_tax;
			} else {
				$additional_item_data['tax'] = 'none';
			}

			$receipt_items[] = $additional_item_data;
			$total_receipt += $additional_item_total;
		}

		if ($shipping_total > 0) {
			$shipping_item = array(
				'name' => 'Доставка',
				'quantity' => 1,
				'cost' => wc_format_decimal($shipping_total, get_option('woocommerce_price_num_decimals')),
				'sum' => wc_format_decimal($shipping_total, get_option('woocommerce_price_num_decimals')),
				'payment_method' => 'full_payment',
				'payment_object' => get_option('robokassa_payment_paymentObject_shipping') ?: get_option('robokassa_payment_paymentObject'),
			);

			if (($sno == 'osn') || ($country != 'KZ')) {
				$shipping_item['tax'] = $default_tax;
			} else {
				$shipping_item['tax'] = 'none';
			}

			$receipt_items[] = $shipping_item;
		}

		$request_data = array(
			'MerchantLogin' => get_option('robokassa_payment_MerchantLogin'),
			'InvoiceID' => $order_id,
			'OutSum' => $order->get_total(),
			'Receipt' => json_encode(array('items' => $receipt_items)),
		);

		$merchant_login = get_option('robokassa_payment_MerchantLogin');
		$password1 = get_option('robokassa_payment_shoppass1');

		$signature_value = md5("{$merchant_login}:{$request_data['OutSum']}:{$request_data['InvoiceID']}:{$request_data['Receipt']}:{$password1}");
		$request_data['SignatureValue'] = $signature_value;

		$response = wp_remote_post('https://auth.robokassa.ru/Merchant/Payment/Confirm', array(
			'body' => $request_data,
		));
	}
}

function robokassa_hold_cancel($order_id, $old_status, $new_status, $order)
{
	$option_value = get_option('robokassa_payment_hold_onoff');
	if (($option_value == 1)
		&& $old_status === 'on-hold' && $new_status === 'cancelled') {

		$request_data = array(
			'MerchantLogin' => get_option('robokassa_payment_MerchantLogin'),
			'InvoiceID' => $order_id,
			'OutSum' => $order->get_total(),
		);

		$merchant_login = get_option('robokassa_payment_MerchantLogin');
		$password1 = get_option('robokassa_payment_shoppass1');

		$signature_value = md5("{$merchant_login}::{$request_data['InvoiceID']}:{$password1}");
		$request_data['SignatureValue'] = $signature_value;

		$response = wp_remote_post('https://auth.robokassa.ru/Merchant/Payment/Cancel', array(
			'body' => $request_data,
		));

		if (is_wp_error($response)) {
			$order->add_order_note('Error sending payment request: ' . $response->get_error_message());
		} else {

			// Добавляем заметку в заказ
			$order->add_order_note('Robokassa: холдирование было отменено вами, либо автоматически после 5 дней ожидания');
		}
	}
}

function robokassa_hold_cancel_after5($order_id)
{
	$order = wc_get_order($order_id);
	if ($order) {
		if ($order->get_status() === 'on-hold') {
			$request_data = array(
				'MerchantLogin' => get_option('robokassa_payment_MerchantLogin'),
				'InvoiceID' => $order_id,
				'OutSum' => $order->get_total(),
			);

			$merchant_login = get_option('robokassa_payment_MerchantLogin');
			$password1 = get_option('robokassa_payment_shoppass1');

			$signature_value = md5("{$merchant_login}::{$request_data['InvoiceID']}:{$password1}");
			$request_data['SignatureValue'] = $signature_value;

			$response = wp_remote_post('https://auth.robokassa.ru/Merchant/Payment/Cancel', array(
				'body' => $request_data,
			));

			if (is_wp_error($response)) {
				$order->add_order_note('Error sending payment request: ' . $response->get_error_message());
			}

			$order->update_status('cancelled');
		}
	}
}

/**
 * Woocommerce blocks support
 */
function declare_cart_checkout_blocks_compatibility() {
	if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
	}
}
add_action('before_woocommerce_init', 'declare_cart_checkout_blocks_compatibility');
add_action('woocommerce_blocks_loaded', 'robokassa_woocommerce_block_support');

function robokassa_woocommerce_block_support()
{
	if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType'))
	{
		require_once dirname(__FILE__) . '/checkout-block.php';

		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			function (PaymentMethodRegistry $payment_method_registry) {
				foreach (robokassa_get_block_gateway_ids() as $gateway_id) {
					$payment_method_registry->register(new WC_Robokassa_Blocks($gateway_id));
				}
			},
			5
		);
	}
}

/**
 * Возвращает список доступных алиасов способов оплаты.
 *
 * @return array
 */
function robokassa_get_available_currency_aliases()
{
	static $aliases = null;

	if (!is_array($aliases)) {
		$data = robokassa_read_currency_data();
		$aliases = [];

		foreach ($data as $key => $details) {
			$alias = robokassa_normalize_alias_key($key, $details);

			if ($alias === '') {
				continue;
			}

			$aliases[$alias] = robokassa_prepare_alias_details($alias, $details);
		}
	}

	return $aliases;
}

/**
 * Загружает данные о валютах Robokassa из файла.
 *
 * @return array
 */
function robokassa_read_currency_data()
{
	$path = __DIR__ . '/data/currencies.json';

	if (!file_exists($path)) {
		return [];
	}

	$contents = file_get_contents($path);

	if (!is_string($contents) || $contents === '') {
		return [];
	}

	$data = json_decode($contents, true);

	return is_array($data) ? $data : [];
}

/**
 * Определяет корректный алиас по исходным данным.
 *
 * @param mixed $key
 * @param mixed $details
 *
 * @return string
 */
function robokassa_normalize_alias_key($key, $details)
{
	if (is_array($details) && isset($details['Alias'])) {
		$value = $details['Alias'];
	} else {
		$value = $key;
	}

	return strtoupper(trim((string)$value));
}

/**
 * Подготавливает данные по ограничениям суммы для алиаса.
 *
 * @param string $alias
 * @param mixed $details
 *
 * @return array
 */
function robokassa_prepare_alias_details($alias, $details)
{
	$details = is_array($details) ? $details : [];

	$result = [
		'Alias' => $alias,
	];

	if (isset($details['MinValue']) && $details['MinValue'] !== '') {
		$result['MinValue'] = (string)$details['MinValue'];
	}

	if (isset($details['MaxValue']) && $details['MaxValue'] !== '') {
		$result['MaxValue'] = (string)$details['MaxValue'];
	}

	return $result;
}

/**
 * Проверяет наличие алиаса в списке доступных способов оплаты.
 *
 * @param string $alias
 *
 * @return bool
 */
function robokassa_is_currency_alias_available($alias)
{
	$alias = strtoupper(trim((string)$alias));

	if ($alias === '') {
		return false;
	}

	$aliases = robokassa_get_available_currency_aliases();

	return isset($aliases[$alias]);
}

/**
 * Возвращает данные для указанного алиаса Robokassa.
 *
 * @param string $alias
 *
 * @return array
 */
function robokassa_get_currency_alias_details($alias)
{
	$alias = strtoupper(trim((string)$alias));

	if ($alias === '') {
		return [];
	}

	$aliases = robokassa_get_available_currency_aliases();

	return isset($aliases[$alias]) ? $aliases[$alias] : [];
}

/**
 * Преобразует строковое значение суммы в числовой формат.
 *
 * @param mixed $value
 *
 * @return float
 */
function robokassa_normalize_amount_value($value)
{
	if (!is_string($value) && !is_numeric($value)) {
		return 0.0;
	}

	$normalized = preg_replace('/[^0-9.,]/', '', (string)$value);
	$normalized = str_replace(' ', '', (string)$normalized);
	$normalized = str_replace(',', '.', $normalized);

	return (float)$normalized;
}

/**
 * Проверяет соответствие суммы ограничениям, заданным для алиаса.
 *
 * @param string $alias
 * @param float  $amount
 *
 * @return bool
 */
function robokassa_is_amount_allowed_for_alias($alias, $amount)
{
	$details = robokassa_get_currency_alias_details($alias);

	if (!is_array($details) || empty($details)) {
		return false;
	}

	if (!is_numeric($amount)) {
		return false;
	}

	$amount = (float)$amount;

	if ($amount <= 0) {
		return true;
	}

	if (isset($details['MaxValue']) && $details['MaxValue'] !== '') {
		$max_value = robokassa_normalize_amount_value($details['MaxValue']);

		if ($max_value > 0 && $amount > $max_value) {
			return false;
		}
	}

	if (isset($details['MinValue']) && $details['MinValue'] !== '') {
		$min_value = robokassa_normalize_amount_value($details['MinValue']);

		if ($min_value > 0 && $amount < $min_value) {
			return false;
		}
	}

	return true;
}

/**
 * Возвращает идентификаторы методов оплаты для блоков оформления заказа.
 *
 * @return array
 */
function robokassa_get_block_gateway_ids()
{
	if (!function_exists('robokassa_payment_add_WC_WP_robokassa_class')) {
		require_once __DIR__ . '/labelsClasses.php';
	}

	$gateway_ids = [];

	foreach ((array)robokassa_payment_add_WC_WP_robokassa_class() as $class_name) {
		if (!class_exists($class_name)) {
			continue;
		}

		$method = new $class_name();

		if (empty($method->id)) {
			continue;
		}

		$config = robokassa_get_optional_method_config_by_gateway($method->id);

		if (!empty($config) && !robokassa_is_optional_method_active($config)) {
			continue;
		}

		$gateway_ids[] = $method->id;
	}

	return array_values(array_unique($gateway_ids));
}
