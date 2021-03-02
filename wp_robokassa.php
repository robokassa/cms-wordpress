<?php

/*
  Plugin Name: Робокасса WooCommerce

  Description: Данный плагин добавляет на Ваш сайт метод оплаты Робокасса для WooCommerce
  Plugin URI: /wp-admin/admin.php?page=main_settings_rb.php
  Author: Робокасса
  Version: 1.3.11
*/

use Robokassa\Payment\RoboDataBase;
use Robokassa\Payment\RobokassaPayAPI;
use Robokassa\Payment\RobokassaSms;


add_action('wp_enqueue_scripts', function () {
    \wp_enqueue_style(
        'robokassa_payment_admin_style_menu',
        \plugin_dir_url(__FILE__) . 'assets/css/menu.css'
    );

    \wp_enqueue_style(
        'robokassa_payment_admin_style_main',
        \plugin_dir_url(__FILE__) . 'assets/css/main.css'
    );
});

define('ROBOKASSA_PAYMENT_DEBUG_STATUS', false);

\spl_autoload_register(
    function ($className) {
        $file = __DIR__ . '/classes/' . \str_replace('\\', '/', $className) . '.php';

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
add_action('parse_request', 'robokassa_payment_robomarketRequest'); // Хук парсера запросов RoboMarket
add_action('woocommerce_order_status_completed', 'robokassa_payment_smsWhenCompleted'); // Хук статуса заказа = "Выполнен"
add_filter('cron_schedules', 'robokassa_payment_labelsCron'); // Добавляем CRON-период в 30 минут
add_action('robokassaCRON1', 'robokassa_payment_getCurrLabels'); // Хук для CRONа. Обновление доступных способов оплаты.

add_action('woocommerce_order_status_changed', 'robokassa_2check_send', 10, 3);

if (!wp_next_scheduled('robokassaCRON1')) {
    wp_schedule_event(time(), 'halfHour', 'robokassaCRON1');
}

register_activation_hook(__FILE__, 'robokassa_payment_wp_robokassa_activate'); //Хук при активации плагина. Дефолтовые настройки и таблица в БД для СМС.

/**
 * @param string $str
 */
function robokassa_payment_DEBUG($str)
{

    /** @var string $file */
    $file = __DIR__ . '/data/robokassa_DEBUG.txt';

    $time = \time();
    $DEBUGFile = \fopen($file, 'a+');
    fwrite($DEBUGFile, \date('d.m.Y H:i:s', $time + 10800) . " ($time) : $str\r\n");
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
    robokassa_payment_DEBUG("mrh_login = $mrhLogin \r\n");

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
    $time = time();

    $dbPrefix = \robokassa_payment_getDbPrefix();

    $roboDataBase = new RoboDataBase(mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME));

    $roboDataBase->query("CREATE TABLE IF NOT EXISTS `{$dbPrefix}sms_stats` (`sms_id` int(10) unsigned NOT NULL AUTO_INCREMENT, `order_id` int(11) NOT NULL, `type` int(1) NOT NULL, `status` int(11) NOT NULL DEFAULT '0', `number` varchar(11) NOT NULL, `text` text NOT NULL, `send_time` datetime DEFAULT NULL, `response` text, `reply` text, PRIMARY KEY (`sms_id`), KEY `order_id` (`order_id`), KEY `status` (`status`)) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;");
    $roboDataBase->query("CREATE TABLE IF NOT EXISTS `{$dbPrefix}robomarket_orders` (`post_id` int(11) NOT NULL COMMENT 'Id поста, он же id заказа', `other_id` int(11) NOT NULL COMMENT 'Id на стороне робомаркета', PRIMARY KEY (`post_id`,`other_id`), UNIQUE KEY `other_id` (`other_id`), UNIQUE KEY `post_id` (`post_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

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
 * @param $schedules
 *
 * @return mixed
 */
function robokassa_payment_labelsCron($schedules)
{
    $schedules['halfHour'] = array(
        'interval' => 30 * MINUTE_IN_SECONDS, // каждые 30 минут
        'display' => __('Half hour'),
    );

    return $schedules;
}

/**
 * @param string $returned
 *
 * @return void
 */
function robokassa_payment_cronLog($returned = 'success')
{
    $file = __DIR__ . '/data/CRONLog/log.txt';

    if (ROBOKASSA_PAYMENT_DEBUG_STATUS) {
        $cronTestFile = fopen($_SERVER['DOCUMENT_ROOT'] . $file, 'a+');

        fwrite($cronTestFile, date('d.m.Y H:i:s') . " Worked succesfull! \r\n");
        fwrite($cronTestFile, "Returned => $returned \r\n\r\n");
        fclose($cronTestFile);
    }
}

/**
 * @return void
 */
function robokassa_payment_getCurrLabels()
{
}

/**
 * @return void
 */
function robokassa_payment_initMenu()
{
    add_submenu_page('woocommerce', 'Настройки Робокассы', 'Настройки Робокассы', 8, 'robokassa_payment_main_settings_rb', 'robokassa_payment_main_settings');
    add_submenu_page('main_settings_rb.php', 'Основные настройки', 'Основные настройки', 8, 'robokassa_payment_main_rb', 'robokassa_payment_main_settings');
    add_submenu_page('main_settings_rb.php', 'Настройки СМС', 'Настройки СМС', 8, 'robokassa_payment_sms_rb', 'robokassa_payment_sms_settings');
    add_submenu_page('main_settings_rb.php', 'РобоМаркет', 'РобоМаркет', 8, 'robokassa_payment_robomarket_rb', 'robokassa_payment_robomarket_settings');
    add_submenu_page('main_settings_rb.php', 'Генерировать YML', 'Генерировать YML', 8, 'robokassa_payment_YMLGenerator', 'robokassa_payment_yml_generator');
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
            $crc_confirm = \strtoupper(
                \md5(
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
                            'shp_label=official_wordpress'
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
            } else {

                $order = new WC_Order($_REQUEST['InvId']);
                $order->add_order_note('Bad CRC');
                $order->update_status('failed');

                $returner = 'BAD SIGN';
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
    return \strtr(
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
    return \preg_replace('/^(.*?)(=*)$/', '$1', $string);
}

/**
 * Хеширование пароля
 *
 * @param string $document
 * @param string $secret
 *
 * @return string
 */
function robokassa_payment_getRobomarketHeaderHash($document, $secret)
{
    return strtoupper(md5($document . $secret));
}

/**
 * Обработка запросов, приходящих из робомаркета
 *
 * @return void
 */
function robokassa_payment_robomarketRequest()
{
    if (isset($_REQUEST['robomarket'])) {
        $requestBody = file_get_contents('php://input');

        $robomarketSecret = get_option('robokassa_payment_robomarket_secret');
        $headerRequest = robokassa_payment_getRobomarketHeaderHash($requestBody, $robomarketSecret);

        $headers = getallheaders();

        $roboSignature = isset($headers['Robosignature']) ? $headers['Robosignature'] : null;

        if ($roboSignature !== $headerRequest) {
            robokassa_payment_DEBUG($requestBody);
            robokassa_payment_DEBUG($robomarketSecret);
            robokassa_payment_DEBUG("Header hash wrong!!! Calc Hash: $headerRequest Got Hash: $roboSignature");

            die('Header hash wrong!!!');
        }

        header('Content-type: application/json');

        // Запрос на резервацию товара в Робомаркете, сбор всех данных, поступивших из запроса,
        // создание заказа, добавление в него всех выбранных продуктов, отправка запроса в Робокассу,
        // в конце - ответ от Робокассы.

        $mainResponse = '';

        $request = json_decode($requestBody, true);

        if (isset($request['Robomarket']['ReservationRequest'])) {
            $reservationRequest = $request['Robomarket']['ReservationRequest'];

            $totalCost = $reservationRequest['TotalCost'];

            if ($totalCost !== 0) {
                $items = $reservationRequest['Items'];

                if (!empty($items) && is_array($items)) {
                    $customer = $reservationRequest['Customer'];

                    $lastItem = end($items);

                    $delivery = $lastItem['Delivery'];

                    $deliveryCity = 'Не указано';
                    $deliveryAddress = 'Не указано';
                    $deliveryAddress1 = 'Не указано';

                    if (isset($delivery['City'])) {
                        $deliveryCity = $delivery['City'];
                    }

                    if (isset($delivery['Address'])) {
                        $deliveryAddress = $delivery['Address'];
                    }

                    if (isset($delivery['City']) && isset($delivery['Address'])) {
                        $deliveryAddress1 = $delivery['City'] . ' ' . $delivery['Address'];
                    }

                    $orderId = $reservationRequest['OrderId'];

                    $order = wc_create_order();

                    if ($order instanceof WC_Order) {
                        foreach ($items as $item) {
                            $invId = $item['OfferId'];

                            $product = wc_get_product($invId);

                            $quantity = $item['Quantity'];

                            if ($product->get_stock_quantity() > $quantity || $product->get_stock_status() == 'instock') {
                                $order->add_product($product, $quantity);
                            } else {
                                $mainResponse = json_encode(array(
                                    'Robomarket' => array(
                                        'ReservationFailure' => array(
                                            'OrderId' => $reservationRequest['product_id'],
                                            'Error' => array(
                                                'ErrorCode' => 'NotEnoughGoodsInStock',
                                            ),
                                        ),
                                    ),
                                ));
                                $order->add_order_note('[RoboMarket]Резервация не удалось');
                                $order->update_status('failed');
                            }
                        }

                        list($customerFirstName, $customerLastName) = explode(' ', $customer['Name']);

                        $order->set_address(array(
                            'first_name' => $customerFirstName,
                            'last_name' => $customerLastName,
                            'email' => $customer['Email'],
                            'phone' => $customer['Phone'],
                            'address_1' => $deliveryAddress1,
                            'address_2' => $deliveryAddress,
                            'city' => $deliveryCity,
                        ), 'billing');

                        $order->calculate_totals();

                        $reservationTime = strtotime($reservationRequest['MinPaymentDue'] . ' +1 hour');

                        if ($mainResponse == '') {
                            $order->add_order_note('[RoboMarket]Заказ зарезервирован');
                            $order->save();

                            robokassa_payment_saveRobomarketOrder($order, $orderId);

                            $mainResponse = json_encode(array(
                                'Robomarket' => array(
                                    'ReservationSuccess' => array(
                                        'PaymentDue' => date('c', $reservationTime),
                                        'OrderId' => $orderId,
                                        'InvoiceId' => $order->get_id(),
                                    ),
                                ),
                            ));
                        }
                    }
                }
            }
        }

        // Поиск заказа по id, запрос на оплату заказа и изменение его статуса,
        // в конце - ответ от Робокассы, подтверждающий оплату.

        if (isset($request['Robomarket']['PurchaseRequest'])) {
            $purchaseRequest = $request['Robomarket']['PurchaseRequest'];

            $orderId = $purchaseRequest['OrderId'];

            $order = robokassa_payment_loadRobomarketOrder($orderId);

            if (!empty($order)) {
                if ('completed' !== $order->get_status()) {
                    /** @var WC_Order_Item_Product $item */
                    foreach ($order->get_items() as $item) {
                        $product = $item->get_product();
                        $product->set_stock_quantity($product->get_stock_quantity() - $item->get_quantity());
                        $product->save();
                    }

                    $order->add_order_note('[RoboMarket]Заказ оплачен');
                    $order->update_status('completed');
                    $order->payment_complete();

                    $mainResponse = json_encode(array(
                        'Robomarket' => array(
                            'PurchaseResponse' => array(
                                'OrderId' => $orderId,
                                'Error' => array(
                                    'ErrorCode' => 'Ok',
                                ),
                            ),
                        ),
                    ));
                }
            }
        }

        // Запрос на отмену уже имеющегося заказа и изменение его статуса,
        // в конце - Робокасса присылает ответ о том, что заказ отменен.

        if (isset($request['Robomarket']['CancellationRequest'])) {
            $cancellationRequest = $request['Robomarket']['CancellationRequest'];

            $invId = $cancellationRequest['InvoiceId'];
            $orderId = $cancellationRequest['OrderId'];

            $order = new WC_Order($invId);
            $order->add_order_note('[RoboMarket]Заказ отменен');
            $order->update_status('failed');

            $mainResponse = json_encode(array(
                'Robomarket' => array(
                    'CancellationResponse' => array(
                        'OrderId' => $orderId,
                        'Error' => array(
                            'ErrorCode' => 'Ok',
                        ),
                    ),
                ),
            ));
        }

        // Запрос, посылаемый при просроченной оплате, если по итогом запроса
        // приходит подтверждение, происходит переход на запрос об оплате.

        if (isset($request['Robomarket']['YaReservationRequest'])) {
            $yaReservationRequest = $request['Robomarket']['YaReservationRequest'];

            $items = $yaReservationRequest['Items'];

            $order = wc_get_order();

            foreach ($items as $item) {
                $product = wc_get_product($item['OfferId']);

                $quantity = $item['Quantity'];

                if ($product->get_stock_quantity() > $quantity || $product->get_stock_status() == 'instock') {
                    $order->add_product($product, $quantity);
                } else {
                    $mainResponse = json_encode(array(
                        'Robomarket' => array(
                            'ReservationFailure' => array(
                                'OrderId' => $yaReservationRequest['product_id'],
                                'Error' => array(
                                    'ErrorCode' => 'NotEnoughGoodsInStock',
                                ),
                            ),
                        ),
                    ));
                    $order->add_order_note('[RoboMarket]Резервация не удалось');
                    $order->update_status('failed');
                }
            }

            $order->set_address(array(// Здесь наверное что-то должно быть, но Егорушка малолетний долбоклюй
            ), 'billing');

            $order->calculate_totals();

            if ($mainResponse == '') {
                $order->add_order_note('[RoboMarket]Заказ зарезервирован');
                $mainResponse = json_encode(array(
                    'Robomarket' => array(
                        'ReservationSuccess' => array(
                            'OrderId' => $request['OrderId'],
                        ),
                    ),
                ));
            }
        }

        $headerResponse = robokassa_payment_getRobomarketHeaderHash($mainResponse, $robomarketSecret);

        header('RoboSignature: ' . $headerResponse);

        robokassa_payment_DEBUG('RoboMarket request: ' . $requestBody);
        robokassa_payment_DEBUG('RoboMarket request hash: ' . $headerRequest);
        robokassa_payment_DEBUG('Main hash: ' . $roboSignature);
        robokassa_payment_DEBUG('RoboMarket response: ' . $mainResponse);
        robokassa_payment_DEBUG('Robomarket secret: ' . $robomarketSecret);
        robokassa_payment_DEBUG('RoboMarket response hash: ' . $headerResponse);
        robokassa_payment_DEBUG('Request Headers = {');

        foreach (getallheaders() as $key => $value) {
            robokassa_payment_DEBUG("\t$key => $value");
        }

        robokassa_payment_DEBUG('}');

        echo $mainResponse;

        die();
    }
}

/**
 * Формирование формы, перенаправляющей пользователя на сайт робокассы
 *
 * Включает в себя подготовку данных и рендеринг самой формы
 *
 * @param mixed $order_id - вукомерс настолько гейский, что любое значение валидным считает
 * @param       $label
 * @param int $commission
 *
 * @return void
 */
function robokassa_payment_createFormWC($order_id, $label, $commission = 0)
{

    $mrhLogin = get_option('robokassa_payment_MerchantLogin');
    $markup = (double)get_option('robokassa_patyment_markup');
    $useMarkup = $markup > 0;

    if (get_option('robokassa_payment_test_onoff') == 'true') {
        $pass1 = get_option('robokassa_payment_testshoppass1');
        $pass2 = get_option('robokassa_payment_testshoppass2');
    } else {
        $pass1 = get_option('robokassa_payment_shoppass1');
        $pass2 = get_option('robokassa_payment_shoppass2');
    }

    $rb = new RobokassaPayAPI($mrhLogin, $pass1, $pass2);

    $order = wc_get_order($order_id);

    $sno = get_option('robokassa_payment_sno');
    $tax = get_option('robokassa_payment_tax');

    if ($tax == "vat18") $tax = "vat20";
    if ($tax == "vat118") $tax = "vat120";

    $receipt = array();

    if ($sno != 'fckoff' && get_option('robokassa_country_code') == 'RU') {
        $receipt['sno'] = $sno;
    }

    global $woocommerce;
    $cart = $woocommerce->cart->get_cart();

    foreach ($cart as $item) {

        $product = wc_get_product($item['product_id']);

        $current['name'] = $product->get_title();
        $current['quantity'] = (float)$item['quantity'];

        $current['sum'] = $useMarkup ? ($item['line_total'] + ($item['line_total'] / 100 * $markup)) : $item['line_total'];

        if (get_option('robokassa_country_code') == 'KZ') {
        } else {
            $current['payment_object'] = \get_option('robokassa_payment_paymentObject');
            $current['payment_method'] = \get_option('robokassa_payment_paymentMethod');
        }

        if (isset($receipt['sno']) && ($receipt['sno'] == 'osn') || (get_option('robokassa_country_code') == 'KZ')) {
            $current['tax'] = $tax;
        } else {
            $current['tax'] = 'none';
        }


        $receipt['items'][] = $current;
    }
  
    if (!count($receipt['items'])) {

        foreach ($order->get_items() as $item)
        {

            $product = $item->get_product();

            $current['name'] = $product->get_title();
            $current['quantity'] = (float) $item->get_quantity();

            $current['sum'] = $useMarkup ? ($item->get_total() * $item->get_quantity() + ($item->get_total() * $item->get_quantity() / 100 * $markup)) : $item->get_total() * $item->get_quantity();

            $current['payment_object'] = \get_option('robokassa_payment_paymentObject');
            $current['payment_method'] = \get_option('robokassa_payment_paymentMethod');

            if (isset($receipt['sno']) && ($receipt['sno'] == 'osn')) {
                $current['tax'] = $tax;
            } else {
                $current['tax'] = 'none';
            }

            $receipt['items'][] = $current;
        }

    }

    if ((double)$order->get_shipping_total() > 0) {

        $current['name'] = 'Доставка';
        $current['quantity'] = 1;
        $current['sum'] = (double)\sprintf(
            "%01.2f",
            (
            $useMarkup
                ? ((double)$order->get_shipping_total() + (double)($order->get_shipping_total() / 100 * $markup))
                : $order->get_shipping_total()
            )
        );

        if (get_option('robokassa_country_code') == 'KZ') {
        } else {
            $current['payment_object'] = \get_option('robokassa_payment_paymentObject');
            $current['payment_method'] = \get_option('robokassa_payment_paymentMethod');
        }

        if (isset($receipt['sno']) && ($receipt['sno'] == 'osn') || (get_option('robokassa_country_code') == 'KZ')) {
            $current['tax'] = $tax;
        } else {
            $current['tax'] = 'none';
        }

        $receipt['items'][] = $current;
    }

    $order_total = (float)($markup ? ($order->get_total() + ($order->get_total() / 100 * $markup)) : $order->get_total());

    if (get_option('robokassa_payment_paytype') == 'true') {

        if (get_option('robokassa_payment_who_commission') == 'shop') {

            robokassa_payment_DEBUG("who_commisson = shop");

            $commission = $commission / 100;
            robokassa_payment_DEBUG("commission = $commission");

            $incSum = number_format($order_total * (1 + (0 * $commission)), 2, '.', '');
            robokassa_payment_DEBUG("incSum = $incSum");

            $commission = $rb->getCommission($label, $incSum) / 100;
            robokassa_payment_DEBUG("commission = $commission");

            $sum = $rb->getCommissionSum($label, $incSum);
            robokassa_payment_DEBUG("sum = $sum");
        } elseif (get_option('robokassa_payment_who_commission') == 'both') {

            $aCommission = get_option('robokassa_payment_size_commission') / 100;
            robokassa_payment_DEBUG("who_commisson = both");
            robokassa_payment_DEBUG("aCommission = $aCommission");

            $commission = $commission / 100;
            robokassa_payment_DEBUG("commission = $commission");

            $incSum = number_format($order_total * (1 + ($aCommission * $commission)), 2, '.', '');
            robokassa_payment_DEBUG("incSum = $incSum");

            $commission = $rb->getCommission($label, $incSum) / 100;
            robokassa_payment_DEBUG("commission = $commission");

            $sum = $rb->getCommissionSum($label, $incSum);
            robokassa_payment_DEBUG("sum = $sum");
        } else {
            robokassa_payment_DEBUG("who_commission = client");
            $sum = number_format($order_total, 2, '.', '');
            robokassa_payment_DEBUG("sum = $sum");
        }
    } else {
        robokassa_payment_DEBUG("paytype = false");
        $sum = number_format($order_total, 2, '.', '');
        robokassa_payment_DEBUG("sum = $sum");
    }

    $invDesc = implode(', ', array_map(function (WC_Order_Item_Product $item) {
        return $item->get_name();
    }, $order->get_items()));

    if (iconv_strlen($invDesc) > 100) {
        $invDesc = "Заказ номер $order_id";
    }

    $receiptForForm = (get_option('robokassa_payment_type_commission') == 'false') ? $receipt : array();

    echo $rb->createForm(
        $sum,
        $order_id,
        $invDesc,
        get_option('robokassa_payment_test_onoff'),
        $label,
        $receiptForForm,
        $order->get_billing_email()
    );
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
 * Создает связь между заказом в woocommerce и заказом в робомаркете
 *
 * @param WC_Order $order
 * @param int $otherId
 *
 * @return void
 *
 * @throws Exception
 */
function robokassa_payment_saveRobomarketOrder(WC_Order $order, $otherId)
{
    $roboDataBase = new RoboDataBase(mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME));

    $dbPrefix = robokassa_payment_getDbPrefix();
    $postId = $order->get_id();

    if (0 == mysqli_num_rows($roboDataBase->query("SELECT * FROM `{$dbPrefix}robomarket_orders` WHERE `post_id` = '$postId' AND `other_id` = $otherId"))) {
        if (false === $roboDataBase->query("INSERT INTO `{$dbPrefix}robomarket_orders` (`post_id`, `other_id`) VALUES ('$postId', '$otherId')")) {
            throw new Exception('Не удалось сохранить информацию о заказе, полученную из робомаркета');
        }
    }
}

/**
 * Возвращает объект заказа по id в робомаркете
 *
 * @param int $otherId
 *
 * @return WC_Order | null
 */
function robokassa_payment_loadRobomarketOrder($otherId)
{
    $roboDataBase = new RoboDataBase(mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME));

    $dbPrefix = robokassa_payment_getDbPrefix();

    $result = $roboDataBase->query("SELECT * FROM `{$dbPrefix}robomarket_orders` WHERE `other_id` = $otherId");

    if (!empty($result)) {
        $robomarketOrder = $result->fetch_assoc();

        $order = wc_get_order($robomarketOrder['post_id']);

        if ($order instanceof WC_Order) {
            return $order;
        }
    }

    return null;
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
function robokassa_payment_robomarket_settings()
{
    $_GET['li'] = 'robomarket';
    include 'menu_rb.php';
    include 'robomarket_settings.php';
}

/**
 * @return void
 */
function robokassa_payment_yml_generator()
{
    $_GET['li'] = 'robomarket';
    include 'menu_rb.php';
    include 'YMLGenerator.php';
    robokassa_payment_generateYML();
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
    $sno = get_option('robokassa_payment_sno');
    $tax = get_option('robokassa_payment_tax');

    if ($sno == 'fckoff') {
        return;
    }

    $trigger_status = 'completed'; //get_option('robokassa_2check_status');

    if ($new_status != $trigger_status) {
        return;
    }

    $order = new WC_Order($order_id);

    if (!$order) {
        return;
    }

    if ($order->get_payment_method_title() != get_option('RobokassaOrderPageTitle_all')) {
        return;
    }

    /** @var array $fields */
    $fields = [
        'merchantId' => get_option('robokassa_payment_MerchantLogin'),
        'id' => $order->get_id() + 1,
        'originId' => $order->get_id(),
        'operation' => 'sell',
        'sno' => $sno,
        'url' => \urlencode('http://' . $_SERVER['HTTP_HOST']),
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
            'sum' => $shipping_total,
            'tax' => $tax,
            'payment_method' => 'full_prepayment',
            'payment_object' => get_option('robokassa_payment_paymentObject'),
        ];

        $fields['items'][] = $products_items;

        switch ($tax) {
            case "vat0":
                $fields['vats'][] = ['type' => $tax, 'sum' => 0];
            case "none":
                $fields['vats'][] = ['type' => $tax, 'sum' => 0];
                break;

            default:
                $fields['vats'][] = ['type' => 'novat', 'sum' => 0];
                break;

            case "vat10":
                $fields['vats'][] = ['type' => $tax, 'sum' => ($shipping_total / 100) * 10];
            case "vat18":
                $fields['vats'][] = ['type' => $tax, 'sum' => ($shipping_total / 100) * 18];
            case "vat20":
                $fields['vats'][] = ['type' => $tax, 'sum' => ($shipping_total / 100) * 20];
                break;
        }
    }

    foreach ($items as $item) {
        $products_items = [
            'name' => $item['name'],
            'quantity' => $item['quantity'],
            'sum' => $item['line_total'],
            'tax' => $tax,
            'payment_method' => 'full_prepayment',
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
            case "none":
                $fields['vats'][] = ['type' => $tax, 'sum' => 0];
                break;

            default:
                $fields['vats'][] = ['type' => 'novat', 'sum' => 0];
                break;

            case "vat10":
                $fields['vats'][] = ['type' => $tax, 'sum' => ($item['line_total'] / 100) * 18];
            case "vat18":
                $fields['vats'][] = ['type' => $tax, 'sum' => ($item['line_total'] / 100) * 18];
            case "vat20":
                $fields['vats'][] = ['type' => $tax, 'sum' => ($item['line_total'] / 100) * 20];
                break;
        }

    }

    /** @var string $startupHash */
    $startupHash = formatSignFinish(
        \base64_encode(
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
        \base64_encode(
            \md5(
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
    curl_close($curl);
}
