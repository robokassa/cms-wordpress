<?php

namespace Robokassa\Payment;

/**
 * Проверка активности плагина WooCommerce
 */

if ( ! function_exists( 'is_plugin_active' ) ) {
    require_once ABSPATH . '/wp-admin/includes/plugin.php';
}
if ( ! is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
    add_action(
        'admin_notices',
        function() {
            echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'Robokassa WooCommerce requires WooCommerce to be installed and active. You can download %s here.', 'woocommerce-payments' ), '<a href="https://wordpress.org/plugins/woocommerce/" target="_blank">WooCommerce</a>' ) . '</strong></p></div>';
        }
    );

    return;
}

/**
 * Класс выбора типа оплаты на стороне Робокассы
 */
class WC_WP_robokassa extends \WC_Payment_Gateway {

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


        $this->title = \mb_strlen(get_option('RobokassaOrderPageTitle_' . $this->id, null)) > 0
            ? get_option('RobokassaOrderPageTitle_' . $this->id, null)
            : $this->title
        ;

        $this->description = \mb_strlen(get_option('RobokassaOrderPageDescription_' . $this->id, null)) > 0
            ? get_option('RobokassaOrderPageDescription_' . $this->id, null)
            : $this->description
        ;

        $this->supports = [
            'products',
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
        ];

        $this->init_form_fields();
        $this->init_settings();

        $this->method_description = $this->long_name.'<br>Больше настроек в <a href="'.admin_url('/admin.php?page=robokassa_payment_main_settings_rb').'">панели плагина</a>';

        add_action('woocommerce_api_wc_'.$this->id, array($this, 'check_ipn'));
        add_action('woocommerce_receipt_'.$this->id, array($this, 'receipt_page'));

        if (class_exists('WC_Subscriptions_Order')) {
            add_action('woocommerce_scheduled_subscription_payment_' . $this->id, [$this, 'scheduled_subscription_payment'], 10, 2);
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
        echo '<p>Спасибо за ваш заказ, пожалуйста, нажмите ниже на кнопку, чтобы заплатить.</p>';

        robokassa_payment_createFormWC($order, $this->id, $this->commission);
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
        global $woocommerce;
        $cart = $woocommerce->cart->get_cart();
        $taxes = $woocommerce->cart->get_cart_contents_tax();

        $order_id  = $renewal_order->get_id();
        $subscribe = reset(wcs_get_subscriptions_for_renewal_order($renewal_order));
        $parent    = $subscribe->get_parent();

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
        $tax = get_option('robokassa_payment_tax');

        $receipt = array();

        if ($sno != 'fckoff') {
            $receipt['sno'] = $sno;
        }

        foreach ($renewal_order->get_items() as $item)
        {
            $product = $item->get_product();;

            $current['name'] = $product->get_title();
            $current['quantity'] = (float)$item['quantity'];

            $tax_per_item = ($taxes / $woocommerce->cart->get_cart_contents_count()) * $current['quantity'];

            $current['cost'] = ($item['line_total'] + $tax_per_item) / $current['quantity'];

            $current['payment_object'] = \get_option('robokassa_payment_paymentObject');
            $current['payment_method'] = \get_option('robokassa_payment_paymentMethod');

            if (isset($receipt['sno']) && ($receipt['sno'] == 'osn')) {
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
                $renewal_order->get_shipping_total()
            );
            $current['payment_object'] = \get_option('robokassa_payment_paymentObject');
            $current['payment_method'] = \get_option('robokassa_payment_paymentMethod');

            if (isset($receipt['sno']) && ($receipt['sno'] == 'osn')) {
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

}
