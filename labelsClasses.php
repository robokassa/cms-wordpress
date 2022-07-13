<?php

class payment_robokassa_pay_method_request_all extends \Robokassa\Payment\WC_WP_robokassa {
    public function __construct() {
        $this->id = 'all';
        $this->method_title = 'Робокасса';
        $this->long_name = 'Оплата через Робокасса';
        $this->description = get_option('RobokassaOrderPageDescription', 'Оплатить через Робокасса');
        $this->commission = 0;

        parent::__construct();
    }
}

class payment_robokassa_pay_method_request_BankCardPSR extends \Robokassa\Payment\WC_WP_robokassa {
    public function __construct() {
        $this->id = 'BankCard';
        $this->method_title = 'Банковская карта (Робокасса)';
        $this->long_name='Оплата через Банковская карта (Робокасса)';
        $this->title = 'Банковская карта';
        $this->description = 'Оплатить через Банковская карта (Робокасса). Комиссия: 0';
        $this->commission = 0;

        parent::__construct();
    }
}

class payment_robokassa_pay_method_request_YandexPayPSR extends \Robokassa\Payment\WC_WP_robokassa {
    public function __construct() {
        $this->id = 'YandexPay';
        $this->method_title = 'Яндекс Pay (Робокасса)';
        $this->long_name='Оплата через Яндекс Pay (Робокасса)';
        $this->title = 'Яндекс Pay';
        $this->description = 'Оплатить через Яндекс Pay (Робокасса). Комиссия: 0';
        $this->commission = 0;

        parent::__construct();
    }
}

class payment_robokassa_pay_method_request_CardHalvaPSR extends \Robokassa\Payment\WC_WP_robokassa {
    public function __construct() {
        $this->id = 'BankCardHalva';
        $this->method_title = 'Карта Халва (Робокасса)';
        $this->long_name='Оплата через Карта Халва (Робокасса)';
        $this->title = 'Карта Халва';
        $this->description = 'Оплатить через Карта Халва (Робокасса). Комиссия: 0';
        $this->commission = 0;

        parent::__construct();
    }
}

class payment_robokassa_pay_method_request_CardHomeCreditPSR extends \Robokassa\Payment\WC_WP_robokassa {
    public function __construct() {
        $this->id = 'BankCardHomeCredit';
        $this->method_title = 'Карта Свобода (Робокасса)';
        $this->long_name='Оплата через Карта Свобода (Робокасса)';
        $this->title = 'Карта Свобода';
        $this->description = 'Оплатить через Карта Свобода (Робокасса). Комиссия: 0';
        $this->commission = 0;

        parent::__construct();
    }
}

class payment_robokassa_pay_method_request_CardSovestPSR extends \Robokassa\Payment\WC_WP_robokassa {
    public function __construct() {
        $this->id = 'BankCardSovest';
        $this->method_title = 'Карта Совесть (Робокасса)';
        $this->long_name='Оплата через Карта Совесть (Робокасса)';
        $this->title = 'Карта Совесть';
        $this->description = 'Оплатить через Карта Совесть (Робокасса). Комиссия: 0';
        $this->commission = 0;

        parent::__construct();
    }
}

/**
 * @var array $methods
 *
 * @return array
 */
function robokassa_payment_add_WC_WP_robokassa_class($methods = null) {
    if (get_option('robokassa_payment_wc_robokassa_enabled') == 'no') {
        return $methods;
    }
    if (get_option('robokassa_payment_paytype') == 'false') {
        $methods[] = 'payment_robokassa_pay_method_request_all'; // Класс выбора типа оплаты на стороне Робокассы
    } else {
        $methods[] = 'payment_robokassa_pay_method_request_BankCardPSR';
        $methods[] = 'payment_robokassa_pay_method_request_YandexPayPSR';
        $methods[] = 'payment_robokassa_pay_method_request_CardHalvaPSR';
        $methods[] = 'payment_robokassa_pay_method_request_CardHomeCreditPSR';
        $methods[] = 'payment_robokassa_pay_method_request_CardSovestPSR';
    }

    return $methods;
}