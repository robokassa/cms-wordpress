<?php

namespace Robokassa\Payment;

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
		    : $this->title
	    ;

        $this->init_form_fields();
        $this->init_settings();

        $this->method_description = $this->long_name.'<br>Больше настроек в <a href="'.admin_url('/admin.php?page=robokassa_payment_main_settings_rb').'">панели плагина</a>';

        add_action('woocommerce_api_wc_'.$this->id, array($this, 'check_ipn'));
        add_action('woocommerce_receipt_'.$this->id, array($this, 'receipt_page'));
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
