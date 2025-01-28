<?php
/**
 * Plugin Name: Robokassa WooCommerce
 * Description: Данный плагин добавляет на Ваш сайт метод оплаты Робокасса для WooCommerce
 * Plugin URI: /wp-admin/admin.php?page=main_settings_rb.php
 * Author: Robokassa
 * Author URI: https://robokassa.com
 * Version: 1.6.6
 */

require_once('payment-widget.php');

use Robokassa\Payment\RoboDataBase;
use Robokassa\Payment\RobokassaPayAPI;
use Robokassa\Payment\RobokassaSms;
use Automattic\WooCommerce\Utilities\OrderUtil;
use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style(
        'robokassa_payment_admin_style_menu',
        plugin_dir_url(__FILE__) . 'assets/css/menu.css'
    );

    wp_enqueue_style(
        'robokassa_payment_admin_style_main',
        plugin_dir_url(__FILE__) . 'assets/css/main.css'
    );

    wp_enqueue_style(
        'robokassa_payment_podeli',
        plugin_dir_url(__FILE__) . 'assets/css/payment_styles.css'
    );
    wp_enqueue_script(
        'robokassa_payment_admin_config',
        plugin_dir_url(__FILE__) . 'assets/js/payment_widget.js'
    );
});

define('ROBOKASSA_PAYMENT_DEBUG_STATUS', false);

spl_autoload_register(
    function ($className) {
        $file = __DIR__ . '/classes/' . str_replace('\\', '/', $className) . '.php';

        if (file_exists($file))
            require_once $file;
    }
);

add_action('woocommerce_cart_calculate_fees', 'robokassa_chosen_payment_method');

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

add_action('woocommerce_review_order_before_payment', 'refresh_payment_methods');
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

add_action('admin_menu', 'robokassa_payment_initMenu'); // Хук для добавления страниц плагина в админку
add_action('plugins_loaded', 'robokassa_payment_initWC'); // Хук инициализации плагина робокассы
add_action('parse_request', 'robokassa_payment_wp_robokassa_checkPayment'); // Хук парсера запросов
add_action('woocommerce_order_status_completed', 'robokassa_payment_smsWhenCompleted'); // Хук статуса заказа = "Выполнен"

add_action('woocommerce_order_status_changed', 'robokassa_2check_send', 10, 3);
add_action('woocommerce_order_status_changed', 'robokassa_hold_confirm', 10, 4);
add_action('woocommerce_order_status_changed', 'robokassa_hold_cancel', 10, 4);
add_action('robokassa_cancel_payment_event', 'robokassa_hold_cancel_after5', 10, 1);

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

        $roboDataBase = new RoboDataBase(mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME));
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
    add_submenu_page('main_settings_rb.php', 'Оплата по частям', 'Оплата по частям', 'edit_pages', 'robokassa_payment_credit', 'robokassa_payment_credit');
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
                            'Shp_result_url=' . (site_url('/?robokassa=result'))
                        ]
                    )
                )
            );

            if ($crc_confirm == $_REQUEST['SignatureValue']) {

                $order = new WC_Order($_REQUEST['InvId']);
                $order->add_order_note('Заказ успешно оплачен!');
                $order->payment_complete();

                global $woocommerce;
                $woocommerce->cart->empty_cart();

                //определяем есть ли в заказе подписка
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

                        (new RobokassaSms(
                            (new RoboDataBase(mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME))),
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
            } elseif ((get_option('robokassa_payment_hold_onoff') == 'true') &&
                strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {

                $input_data = file_get_contents('php://input');

                // Разбиваем JWT на три части
                $token_parts = explode('.', $input_data);

                // Проверяем, что есть три части
                if (count($token_parts) === 3) {
                    // Декодируем вторую часть (полезные данные)
                    $json_data = json_decode(base64_decode($token_parts[1]), true);

                    // Проверяем наличие ключевого поля "state" со значением "HOLD"
                    if (isset($json_data['data']['state']) && $json_data['data']['state'] === 'HOLD') {
                        // Изменяем статус заказа
                        $order = new WC_Order($json_data['data']['invId']);
                        $date_in_five_days = date('Y-m-d H:i:s', strtotime('+5 days'));
                        $order->add_order_note("Robokassa: Платеж успешно подтвержден. Он ожидает подтверждения до {$date_in_five_days}, после чего автоматически отменится");
                        $order->update_status('on-hold');

                        // Добавляем событие, которое делает unhold через 5 дней
                        wp_schedule_single_event(strtotime('+5 days'), 'robokassa_cancel_payment_event', array($order->get_id()));
                    }
                    if (isset($json_data['data']['state']) && $json_data['data']['state'] === 'OK') {
                        // Изменяем статус заказа
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
    if ($sno != 'fckoff' && get_option('robokassa_country_code') == 'RU') {
        $receipt['sno'] = $sno;
    }
    $receipt['sno'] = $sno;

    $tax = get_option('robokassa_payment_tax');
    if ($tax == "vat118") $tax = "vat120";

    $cart = $woocommerce->cart->get_cart();

    $receipt = array();

    $total_order = $order->get_total();
    $total_receipt = 0;

    foreach ($cart as $item) {
        $product = wc_get_product($item['product_id']);

        $current = [];
        $current['name'] = $product->get_title();
        $current['quantity'] = $item['quantity'];
        $current['sum'] = $item['line_total'];
        $current['cost'] = $item['line_total'] / $item['quantity'];

        $total_receipt += $current['sum'];

        if (get_option('robokassa_country_code') == 'RU') {
            $current['payment_object'] = get_option('robokassa_payment_paymentObject');
            $current['payment_method'] = get_option('robokassa_payment_paymentMethod');
        }

        if ((isset($receipt['sno']) && $receipt['sno'] == 'osn') || get_option('robokassa_country_code') == 'RU') {
            $current['tax'] = $tax;
        } else {
            $current['tax'] = 'none';
        }

        $receipt['items'][] = $current;
    }

    // Активность плагина WooCommerce Checkout Add-Ons
    if (is_plugin_active('woocommerce-checkout-add-ons/woocommerce-checkout-add-ons.php')) {
        $additional_items = $order->get_items('fee');

        foreach ($additional_items as $additional_item) {
            $additional_item_name = $additional_item->get_name();
            $additional_item_total = floatval($additional_item->get_total());

            $additional_item_data = array(
                'name' => $additional_item_name,
                'quantity' => 1,
                'cost' => $additional_item_total,
                'sum' => $additional_item_total,
                'payment_object' => get_option('robokassa_payment_paymentObject'),
                'payment_method' => get_option('robokassa_payment_paymentMethod'),
                'tax' => get_option('robokassa_payment_tax'),
            );

            $receipt['items'][] = $additional_item_data;
            $total_receipt += $additional_item_total;
        }
    }

    if (empty($receipt)) {
        foreach ($order->get_items() as $item) {

            $product = $item->get_product();

            $current['name'] = $product->get_title();
            $current['quantity'] = $item->get_quantity();
            $current['sum'] = $item['line_total'];
            $current['cost'] = $item['line_total'] / $item->get_quantity();;

            $current['payment_object'] = get_option('robokassa_payment_paymentObject');
            $current['payment_method'] = get_option('robokassa_payment_paymentMethod');

            if (isset($receipt['sno']) && ($receipt['sno'] == 'osn')) {
                $current['tax'] = $tax;
            } else {
                $current['tax'] = 'none';
            }

            $receipt['items'][] = $current;
            $total_receipt += $current['sum'];
        }
    }

    if ((double)$order->get_shipping_total() > 0) {

        $current['name'] = 'Доставка';
        $current['quantity'] = 1;
        $current['cost'] = (double)sprintf("%01.2f", $order->get_shipping_total());
        $current['sum'] = (double)sprintf("%01.2f", $order->get_shipping_total());

        if (get_option('robokassa_country_code') == 'RU') {
            $current['payment_object'] = get_option('robokassa_payment_paymentObject');
            $current['payment_method'] = get_option('robokassa_payment_paymentMethod');
        }

        if (isset($receipt['sno']) && ($receipt['sno'] == 'osn') || (get_option('robokassa_country_code') != 'KZ')) {
            $current['tax'] = $tax;
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
    processRobokassaPayment($order_id, $label);
}

/**
 * Начало оформления заказа
 *
 * @return void
 */
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
    $tax = get_option('robokassa_payment_tax');


    if ($payment_method == 'advance' || $payment_method == 'full_prepayment' || $payment_method == 'prepayment') {
        if ($sno == 'fckoff') {
            robokassa_payment_DEBUG("Robokassa: SNO is 'fckoff', exiting function");
            return;
        }

        $trigger_status = 'completed'; //get_option('robokassa_2check_status');

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

        $items = $order->get_items();
        $shipping_total = $order->get_shipping_total();

        if ($shipping_total > 0) {
            $products_items = [
                'name' => 'Доставка',
                'quantity' => 1,
                'cost' => $shipping_total,
                'sum' => $shipping_total * 1,
                'tax' => $tax,
                'payment_method' => 'full_payment',
                'payment_object' => get_option('robokassa_payment_paymentObject'),
            ];

            $fields['items'][] = $products_items;

            switch ($tax) {
                case "vat0":
                    $fields['vats'][] = ['type' => $tax, 'sum' => 0];
                    break;
                case "none":
                    $fields['vats'][] = ['type' => $tax, 'sum' => 0];
                    break;
                case "vat10":
                    $fields['vats'][] = ['type' => $tax, 'sum' => ($shipping_total / 100) * 10];
                    break;
                case "vat20":
                    $fields['vats'][] = ['type' => $tax, 'sum' => ($shipping_total / 100) * 20];
                    break;
                default:
                    $fields['vats'][] = ['type' => 'novat', 'sum' => 0];
                    break;
            }

        }

        if (is_plugin_active('woocommerce-checkout-add-ons/woocommerce-checkout-add-ons.php')) {
            $additional_items = $order->get_items('fee');

            foreach ($additional_items as $additional_item) {
                $additional_item_name = $additional_item->get_name();
                $additional_item_total = floatval($additional_item->get_total());

                $products_items = array(
                    'name' => $additional_item_name,
                    'quantity' => 1,
                    'cost' => $additional_item_total,
                    'sum' => $additional_item_total,
                    'payment_object' => get_option('robokassa_payment_paymentObject'),
                    'payment_method' => 'full_payment',
                    'tax' => $tax,
                );

                $fields['items'][] = $products_items;

                switch ($tax) {
                    case "vat0":
                        $fields['vats'][] = ['type' => $tax, 'sum' => 0];
                        break;
                    case "none":
                        $fields['vats'][] = ['type' => $tax, 'sum' => 0];
                        break;
                    case "vat10":
                        $fields['vats'][] = ['type' => $tax, 'sum' => ($shipping_total / 100) * 10];
                        break;
                    case "vat20":
                        $fields['vats'][] = ['type' => $tax, 'sum' => ($shipping_total / 100) * 20];
                        break;
                    default:
                        $fields['vats'][] = ['type' => 'novat', 'sum' => 0];
                        break;
                }
            }
        }

        foreach ($items as $item) {
            $products_items = [
                'name' => $item['name'],
                'quantity' => $item['quantity'],
                'sum' => $item['line_total'],
                'tax' => $tax,
                'payment_method' => 'full_payment',
                'payment_object' => get_option('robokassa_payment_paymentObject'),
            ];

            $product = wc_get_product($item['product_id']);
            $sku = $product->get_sku();

            if (!empty($sku)) {
                $products_items['nomenclature_code'] = mb_convert_encoding($sku, 'UTF-8');
            }

            $fields['items'][] = $products_items;

            switch ($tax) {
                case "vat0":
                    $fields['vats'][] = ['type' => $tax, 'sum' => 0];
                    break;
                case "none":
                    $fields['vats'][] = ['type' => $tax, 'sum' => 0];
                    break;
                case "vat10":
                    $fields['vats'][] = ['type' => $tax, 'sum' => ($item['line_total'] / 100) * 10];
                    break;
                case "vat20":
                    $fields['vats'][] = ['type' => $tax, 'sum' => ($item['line_total'] / 100) * 20];
                    break;
                default:
                    $fields['vats'][] = ['type' => 'novat', 'sum' => 0];
                    break;
            }

        }

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

function robokassa_hold_confirm($order_id, $old_status, $new_status, $order)
{
    // Проверяем, что статус был изменен с "on-hold" на "processing" (обработка)
    if ((get_option('robokassa_payment_hold_onoff') == 'true')
        && $old_status === 'on-hold' && $new_status === 'processing') {

        $order = wc_get_order($order_id);
        $order_items = $order->get_items();
        $shipping_total = $order->get_shipping_total();

        $receipt_items = array();
        foreach ($order_items as $item) {
            $item_name = $item->get_name();
            $item_quantity = $item->get_quantity();
            $item_sum = $item->get_total();
            $receipt_items[] = array(
                'name' => $item_name,
                'quantity' => $item_quantity,
                'sum' => $item_sum,
                'tax' => get_option('robokassa_payment_tax'),
                'payment_method' => get_option('robokassa_payment_paymentMethod'),
                'payment_object' => get_option('robokassa_payment_paymentObject'),
            );
        }

        if ($shipping_total > 0) {
            $receipt_items[] = array(
                'name' => 'Доставка',
                'quantity' => 1,
                'cost' => $shipping_total,
                'sum' => $shipping_total * 1,
                'tax' => get_option('robokassa_payment_tax'),
                'payment_method' => 'full_payment',
                'payment_object' => get_option('robokassa_payment_paymentObject'),
            );
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

        /*        if (is_wp_error($response)) {
                    robokassa_payment_DEBUG('Error sending payment request: ' . $response->get_error_message());
                    $order->add_order_note('Error sending payment request: ' . $response->get_error_message());
                } else {
                    $body = wp_remote_retrieve_body($response);
                    $order->add_order_note('Robokassa: ошибка проведения платежа' . json_encode($request_data) . $body);
                }*/
    }
}

function robokassa_hold_cancel($order_id, $old_status, $new_status, $order)
{
    // Проверяем, что статус был изменен с "on-hold" на "Canceled"
    if ((get_option('robokassa_payment_hold_onoff') == 'true') &&
        $old_status === 'on-hold' && $new_status === 'cancelled') {

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
            $order->add_order_note('Robokassa: холдирование было отменено вами, либо автоматически после 5 дней ожидания');
        }
    }
}

function robokassa_hold_cancel_after5($order_id)
{
    $order = wc_get_order($order_id);
    if ($order) {
        // Проверяем текущий статус заказа
        if ($order->get_status() === 'on-hold') {
            // Отменяем заказ и добавляем соответствующее уведомление
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
                $container = Automattic\WooCommerce\Blocks\Package::container();
                $container->register(
                    WC_Robokassa_Blocks::class,
                    function () {
                        return new WC_Robokassa_Blocks();
                    }
                );
                $payment_method_registry->register($container->get(WC_Robokassa_Blocks::class));
            },
            5
        );
    }
}