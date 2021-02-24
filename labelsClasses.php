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

class payment_robokassa_pay_method_request_Qiwi40PS extends \Robokassa\Payment\WC_WP_robokassa {
    public function __construct() {
        $this->id = 'QiwiWallet';
        $this->method_title = 'QIWI Кошелек (Робокасса)';
        $this->long_name='Оплата через QIWI Кошелек (Робокасса)';
        $this->title = 'QIWI Кошелек';
        $this->description = 'Оплатить через QIWI Кошелек (Робокасса). Комиссия: 0';
        $this->commission = 0;

        parent::__construct();
    }
}

class payment_robokassa_pay_method_request_YandexMerchantPS5R extends \Robokassa\Payment\WC_WP_robokassa {
    public function __construct() {
        $this->id = 'YandexMoney';
        $this->method_title = 'Яндекс.Деньги (Робокасса)';
        $this->long_name='Оплата через Яндекс.Деньги (Робокасса)';
        $this->title = 'Яндекс.Деньги';
        $this->description = 'Оплатить через Яндекс.Деньги (Робокасса). Комиссия: 0';
        $this->commission = 0;

        parent::__construct();
    }
}

class payment_robokassa_pay_method_request_WMR30PM extends \Robokassa\Payment\WC_WP_robokassa {
    public function __construct() {
        $this->id = 'WMR';
        $this->method_title = 'WMR (Робокасса)';
        $this->long_name='Оплата через WMR (Робокасса)';
        $this->title = 'WMR';
        $this->description = 'Оплатить через WMR (Робокасса). Комиссия: 0';
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

class payment_robokassa_pay_method_request_ApplePayPSR extends \Robokassa\Payment\WC_WP_robokassa {
    public function __construct() {
        $this->id = 'ApplePay';
        $this->method_title = 'Apple Pay (Робокасса)';
        $this->long_name='Оплата через Apple Pay (Робокасса)';
        $this->title = 'Apple Pay';
        $this->description = 'Оплатить через Apple Pay (Робокасса). Комиссия: 0';
        $this->commission = 0;

        parent::__construct();
    }
}

class payment_robokassa_pay_method_request_SamsungPayPSR extends \Robokassa\Payment\WC_WP_robokassa {
    public function __construct() {
        $this->id = 'SamsungPay';
        $this->method_title = 'Samsung Pay (Робокасса)';
        $this->long_name='Оплата через Samsung Pay (Робокасса)';
        $this->title = 'Samsung Pay';
        $this->description = 'Оплатить через Samsung Pay (Робокасса). Комиссия: 0';
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
        $methods[] = 'payment_robokassa_pay_method_request_Qiwi40PS';
        $methods[] = 'payment_robokassa_pay_method_request_YandexMerchantPS5R';
        $methods[] = 'payment_robokassa_pay_method_request_WMR30PM';
        $methods[] = 'payment_robokassa_pay_method_request_BankCardPSR';
        $methods[] = 'payment_robokassa_pay_method_request_CardHalvaPSR';
        $methods[] = 'payment_robokassa_pay_method_request_CardHomeCreditPSR';
        $methods[] = 'payment_robokassa_pay_method_request_CardSovestPSR';
        $methods[] = 'payment_robokassa_pay_method_request_ApplePayPSR';
        $methods[] = 'payment_robokassa_pay_method_request_SamsungPayPSR';
    }

    return $methods;
}

