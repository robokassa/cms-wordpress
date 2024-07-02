<?php
/**
 * Plugin Name: Robokassa WooCommerce
 * Description: Данный плагин добавляет на Ваш сайт метод оплаты Робокасса для WooCommerce
 * Plugin URI: /wp-admin/admin.php?page=main_settings_rb.php
 * Author: Robokassa
 * Author URI: https://robokassa.com
 * Version: 1.6.1
 */

require_once('payment-widget.php');

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

    \wp_enqueue_style(
        'robokassa_payment_podeli',
        \plugin_dir_url(__FILE__) . 'assets/css/payment_styles.css'
    );
    \wp_enqueue_script(
        'robokassa_payment_admin_config',
        \plugin_dir_url(__FILE__) . 'assets/js/payment_widget.js'
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

    /*    $roboDataBase = new RoboDataBase(mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME));

        $roboDataBase->query("CREATE TABLE IF NOT EXISTS `{$dbPrefix}sms_stats` (`sms_id` int(10) unsigned NOT NULL AUTO_INCREMENT, `order_id` int(11) NOT NULL, `type` int(1) NOT NULL, `status` int(11) NOT NULL DEFAULT '0', `number` varchar(11) NOT NULL, `text` text NOT NULL, `send_time` datetime DEFAULT NULL, `response` text, `reply` text, PRIMARY KEY (`sms_id`), KEY `order_id` (`order_id`), KEY `status` (`status`)) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;");
        $roboDataBase->query("CREATE TABLE IF NOT EXISTS `{$dbPrefix}robomarket_orders` (`post_id` int(11) NOT NULL COMMENT 'Id поста, он же id заказа', `other_id` int(11) NOT NULL COMMENT 'Id на стороне робомаркета', PRIMARY KEY (`post_id`,`other_id`), UNIQUE KEY `other_id` (`other_id`), UNIQUE KEY `post_id` (`post_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8;");*/

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
function robokassa_payment_getCurrLabels()
{
}

/**
 * @return void
 */
function robokassa_payment_initMenu()
{
    add_submenu_page('woocommerce', 'Настройки Робокассы', 'Настройки Робокассы', 'edit_pages', 'robokassa_payment_main_settings_rb', 'robokassa_payment_main_settings');
    add_submenu_page('main_settings_rb.php', 'Основные настройки', 'Основные настройки', 'edit_pages', 'robokassa_payment_main_rb', 'robokassa_payment_main_settings');
    add_submenu_page('main_settings_rb.php', 'Настройки СМС', 'Настройки СМС', 'edit_pages', 'robokassa_payment_sms_rb', 'robokassa_payment_sms_settings');
    add_submenu_page('main_settings_rb.php', 'РобоМаркет', 'РобоМаркет', 'edit_pages', 'robokassa_payment_robomarket_rb', 'robokassa_payment_robomarket_settings');
    add_submenu_page('main_settings_rb.php', 'Генерировать YML', 'Генерировать YML', 'edit_pages', 'robokassa_payment_YMLGenerator', 'robokassa_payment_yml_generator');
    add_submenu_page('main_settings_rb.php', 'Регистрация', 'Регистрация', 'edit_pages', 'robokassa_payment_registration', 'robokassa_payment_reg');
    add_submenu_page('main_settings_rb.php', 'Скачать оферту', 'Скачать оферту', 'edit_pages', 'robokassa_payment_offer', 'robokassa_payment_oferta');
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

                //определяем есть ли в заказе подписка
                if (function_exists('wcs_order_contains_subscription')) {
                    $subscriptions = wcs_get_subscriptions_for_order($_REQUEST['InvId']) ?: wcs_get_subscriptions_for_renewal_order($_REQUEST['InvId']);

                    if ($subscriptions == true) {
                        foreach ($subscriptions as $subscription) {
                            $subscription->update_status('active');
                        };
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

        if ($_REQUEST['robokassa'] == 'registration') {

            $postData = file_get_contents('php://input');
            $data = json_decode($postData, true);

            $filename = 'registration_data.json';
            $save = json_encode($data);
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . "/wp-content/plugins/robokassa/data/{$filename}", $save);

            echo json_encode($data);
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

            $order->set_address(array(), 'billing');

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
 *
 * Проверка режимы работы
 *
 * @return void
 */
function getRobokassaPasses()
{
    if (get_option('robokassa_payment_test_onoff') == 'true') {
        return [
            'pass1' => get_option('robokassa_payment_testshoppass1'),
            'pass2' => get_option('robokassa_payment_testshoppass2'),
        ];
    } else {
        return [
            'pass1' => get_option('robokassa_payment_shoppass1'),
            'pass2' => get_option('robokassa_payment_shoppass2'),
        ];
    }
}

/**
 * Подготовка товарной номенклатуры для формирования чека
 *
 * @param mixed $order_id
 *
 * @return void
 */
function createRobokassaReceipt($order_id)
{
    global $woocommerce;
    $order = new WC_Order($order_id);

    $sno = get_option('robokassa_payment_sno');
    if ($sno != 'fckoff' && get_option('robokassa_country_code') == 'RU') {
        $receipt['sno'] = $sno;
    }

    $tax = get_option('robokassa_payment_tax');
    if ($tax == "vat118") $tax = "vat120";

    $cart = $woocommerce->cart->get_cart();

    $receipt = array();

    $total_order = $order->get_total(); // Сумма OutSum
    $total_receipt = 0; // Сумма всех $current['sum']

    foreach ($cart as $item) {
        $product = wc_get_product($item['product_id']);
        $quantity = (float)$item['quantity'];

        // Проверяем, если функция включена, то разбиваем на дополнительные объекты
        if (get_option('robokassa_marking') == 1) {
            for ($i = 1; $i <= $quantity; $i++) {
                $current = [];
                $current['name'] = $product->get_title();
                $current['quantity'] = 1;
                $current['sum'] = $item['line_total'];
                $current['cost'] = $item['line_total'] / $quantity;

                $total_receipt += $current['cost'];

                if (get_option('robokassa_country_code') == 'RU') {
                    $current['payment_object'] = get_option('robokassa_payment_paymentObject');
                    $current['payment_method'] = get_option('robokassa_payment_paymentMethod');
                }

                if (isset($receipt['sno']) && $receipt['sno'] == 'osn' || get_option('robokassa_country_code') == 'KZ') {
                    $current['tax'] = $tax;
                } else {
                    $current['tax'] = 'none';
                }

                $receipt['items'][] = $current;
            }
        } else {
            $current = [];
            $current['name'] = $product->get_title();
            $current['quantity'] = $quantity;
            $current['sum'] = $item['line_total'];
            $current['cost'] = $item['line_total'] / $quantity;

            $total_receipt += $current['sum'];

            if (get_option('robokassa_country_code') == 'RU') {
                $current['payment_object'] = get_option('robokassa_payment_paymentObject');
                $current['payment_method'] = get_option('robokassa_payment_paymentMethod');
            }

            if (isset($receipt['sno']) && $receipt['sno'] == 'osn' || get_option('robokassa_country_code') == 'KZ') {
                $current['tax'] = $tax;
            } else {
                $current['tax'] = 'none';
            }

            $receipt['items'][] = $current;
        }
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
                'payment_object' => \get_option('robokassa_payment_paymentObject'),
                'payment_method' => \get_option('robokassa_payment_paymentMethod'),
                'tax' => \get_option('robokassa_payment_tax'),
            );

            $receipt['items'][] = $additional_item_data;
            $total_receipt += $additional_item_total;
        }
    }

    if (empty($receipt)) {

        foreach ($order->get_items() as $item) {

            $product = $item->get_product();

            $current['name'] = $product->get_title();
            $current['quantity'] = (float)$item->get_quantity();

            $current['sum'] = $item['line_total'];
            $current['cost'] = $item['line_total'] / $quantity;

            $current['payment_object'] = \get_option('robokassa_payment_paymentObject');
            $current['payment_method'] = \get_option('robokassa_payment_paymentMethod');

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
        $current['cost'] = (double)\sprintf(
            "%01.2f",
            $order->get_shipping_total()
        );

        if (get_option('robokassa_country_code') == 'RU') {
            $current['payment_object'] = \get_option('robokassa_payment_paymentObject');
            $current['payment_method'] = \get_option('robokassa_payment_paymentMethod');
        }

        if (isset($receipt['sno']) && ($receipt['sno'] == 'osn') || (get_option('robokassa_country_code') == 'KZ')) {
            $current['tax'] = $tax;
        } else {
            $current['tax'] = 'none';
        }

        $receipt['items'][] = $current;
        $total_receipt += $current['cost'];
    }

    if ($total_receipt != $total_order) {
        error_log('Robokassa: общая сумма чека (' . $total_receipt . ') НЕ совпадает с общей суммой заказа (' . $total_order . ')');
    }

    return $receipt;
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

    if (class_exists('WC_Subscriptions_Order')) {
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
            error_log("Robokassa: SNO is 'fckoff', exiting function");
            return;
        }

        $trigger_status = 'completed'; //get_option('robokassa_2check_status');

        if ($new_status != $trigger_status) {
            error_log("Robokassa: New status ($new_status) does not match trigger status ($trigger_status), exiting function");
            return;
        }

        $order = new WC_Order($order_id);

        if (!$order) {
            error_log("Robokassa: Order not found for order_id: $order_id, exiting function");
            return;
        }

        /*        if ($order->get_payment_method_title() != get_option('RobokassaOrderPageTitle_all')) {
                    error_log("Payment method title does not match: " . $order->get_payment_method_title() . get_option('RobokassaOrderPageTitle_all') . ", exiting function");
                    return;
                }*/

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
                    'payment_object' => \get_option('robokassa_payment_paymentObject'),
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

        if ($result === false) {
            error_log("Robokassa: cURL error: " . curl_error($curl));
        } else {
            error_log("Robokassa: cURL result: " . $result);
        }

        curl_close($curl);
    } else {
        error_log("Robokassa: Payment method is not advance, full_prepayment, or prepayment, no action taken");
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
                'payment_method' => \get_option('robokassa_payment_paymentMethod'),
                'payment_object' => \get_option('robokassa_payment_paymentObject'),
                'tax' => get_option('robokassa_payment_tax'),
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
                    error_log('Error sending payment request: ' . $response->get_error_message());
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
    // Проверяем, что заказ существует
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
