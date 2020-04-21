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

class payment_robokassa_pay_method_request_Qiwi50RIBRM extends \Robokassa\Payment\WC_WP_robokassa {
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

class payment_robokassa_pay_method_request_YandexMerchantRIBR extends \Robokassa\Payment\WC_WP_robokassa {
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

class payment_robokassa_pay_method_request_WMR30RM extends \Robokassa\Payment\WC_WP_robokassa {
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

class payment_robokassa_pay_method_request_ElecsnetWalletRIBR extends \Robokassa\Payment\WC_WP_robokassa {
    public function __construct() {
        $this->id = 'ElecsnetWallet';
        $this->method_title = 'Кошелек Элекснет (Робокасса)';
        $this->long_name='Оплата через Кошелек Элекснет (Робокасса)';
        $this->title = 'Кошелек Элекснет';
        $this->description = 'Оплатить через Кошелек Элекснет (Робокасса). Комиссия: 0';
        $this->commission = 0;

        parent::__construct();
    }
}

class payment_robokassa_pay_method_request_W1RIBR extends \Robokassa\Payment\WC_WP_robokassa {
    public function __construct() {
        $this->id = 'W1';
        $this->method_title = 'RUR Единый кошелек (Робокасса)';
        $this->long_name='Оплата через RUR Единый кошелек (Робокасса)';
        $this->title = 'RUR Единый кошелек';
        $this->description = 'Оплатить через RUR Единый кошелек (Робокасса). Комиссия: 0';
        $this->commission = 0;

        parent::__construct();
    }
}

class payment_robokassa_pay_method_request_AlfaBankRIBR extends \Robokassa\Payment\WC_WP_robokassa {
    public function __construct() {
        $this->id = 'AlfaBank';
        $this->method_title = 'Альфа-Клик (Робокасса)';
        $this->long_name='Оплата через Альфа-Клик (Робокасса)';
        $this->title = 'Альфа-Клик';
        $this->description = 'Оплатить через Альфа-Клик (Робокасса). Комиссия: 0';
        $this->commission = 0;

        parent::__construct();
    }
}

class payment_robokassa_pay_method_request_VTB24RIBR extends \Robokassa\Payment\WC_WP_robokassa {
    public function __construct() {
        $this->id = 'VTB24';
        $this->method_title = 'ВТБ (Робокасса)';
        $this->long_name='Оплата через ВТБ (Робокасса)';
        $this->title = 'ВТБ';
        $this->description = 'Оплатить через ВТБ (Робокасса). Комиссия: 0';
        $this->commission = 0;

        parent::__construct();
    }
}

class payment_robokassa_pay_method_request_W1RIBPSBR extends \Robokassa\Payment\WC_WP_robokassa {
    public function __construct() {
        $this->id = 'W1';
        $this->method_title = 'RUR Единый кошелек (Робокасса)';
        $this->long_name='Оплата через RUR Единый кошелек (Робокасса)';
        $this->title = 'RUR Единый кошелек';
        $this->description = 'Оплатить через RUR Единый кошелек (Робокасса). Комиссия: 0';
        $this->commission = 0;

        parent::__construct();
    }
}

class payment_robokassa_pay_method_request_MINBankRIBR extends \Robokassa\Payment\WC_WP_robokassa {
    public function __construct() {
        $this->id = 'BankMIN';
        $this->method_title = 'Московский Индустриальный Банк (Робокасса)';
        $this->long_name='Оплата через Московский Индустриальный Банк (Робокасса)';
        $this->title = 'Московский Индустриальный Банк';
        $this->description = 'Оплатить через Московский Индустриальный Банк (Робокасса). Комиссия: 0';
        $this->commission = 0;

        parent::__construct();
    }
}

class payment_robokassa_pay_method_request_BSSIntezaRIBR extends \Robokassa\Payment\WC_WP_robokassa {
    public function __construct() {
        $this->id = 'BankInteza';
        $this->method_title = 'Банк Интеза (Робокасса)';
        $this->long_name='Оплата через Банк Интеза (Робокасса)';
        $this->title = 'Банк Интеза';
        $this->description = 'Оплатить через Банк Интеза (Робокасса). Комиссия: 0';
        $this->commission = 0;

        parent::__construct();
    }
}

class payment_robokassa_pay_method_request_BSSAvtovazbankR extends \Robokassa\Payment\WC_WP_robokassa {
    public function __construct() {
        $this->id = 'BankAVB';
        $this->method_title = 'Банк АВБ (Робокасса)';
        $this->long_name='Оплата через Банк АВБ (Робокасса)';
        $this->title = 'Банк АВБ';
        $this->description = 'Оплатить через Банк АВБ (Робокасса). Комиссия: 0';
        $this->commission = 0;

        parent::__construct();
    }
}

class payment_robokassa_pay_method_request_FacturaBinBank extends \Robokassa\Payment\WC_WP_robokassa {
    public function __construct() {
        $this->id = 'BankBin';
        $this->method_title = 'БИНБАНК (Робокасса)';
        $this->long_name='Оплата через БИНБАНК (Робокасса)';
        $this->title = 'БИНБАНК';
        $this->description = 'Оплатить через БИНБАНК (Робокасса). Комиссия: 0';
        $this->commission = 0;

        parent::__construct();
    }
}

class payment_robokassa_pay_method_request_BSSFederalBankForInnovationAndDevelopmentR extends \Robokassa\Payment\WC_WP_robokassa {
    public function __construct() {
        $this->id = 'BankFBID';
        $this->method_title = 'ФБ Инноваций и Развития (Робокасса)';
        $this->long_name='Оплата через ФБ Инноваций и Развития (Робокасса)';
        $this->title = 'ФБ Инноваций и Развития';
        $this->description = 'Оплатить через ФБ Инноваций и Развития (Робокасса). Комиссия: 0';
        $this->commission = 0;

        parent::__construct();
    }
}

class payment_robokassa_pay_method_request_FacturaSovCom extends \Robokassa\Payment\WC_WP_robokassa {
    public function __construct() {
        $this->id = 'BankSovCom';
        $this->method_title = 'Совкомбанк (Робокасса)';
        $this->long_name='Оплата через Совкомбанк (Робокасса)';
        $this->title = 'Совкомбанк';
        $this->description = 'Оплатить через Совкомбанк (Робокасса). Комиссия: 0';
        $this->commission = 0;

        parent::__construct();
    }
}

class payment_robokassa_pay_method_request_BSSNationalBankTRUSTR extends \Robokassa\Payment\WC_WP_robokassa {
    public function __construct() {
        $this->id = 'BankTrust';
        $this->method_title = 'Национальный банк ТРАСТ (Робокасса)';
        $this->long_name='Оплата через Национальный банк ТРАСТ (Робокасса)';
        $this->title = 'Национальный банк ТРАСТ';
        $this->description = 'Оплатить через Национальный банк ТРАСТ (Робокасса). Комиссия: 0';
        $this->commission = 0;

        parent::__construct();
    }
}

class payment_robokassa_pay_method_request_BANKOCEAN3R extends \Robokassa\Payment\WC_WP_robokassa {
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

class payment_robokassa_pay_method_request_CardHalvaRIBR extends \Robokassa\Payment\WC_WP_robokassa {
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

class payment_robokassa_pay_method_request_ApplePayRIBR extends \Robokassa\Payment\WC_WP_robokassa {
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

class payment_robokassa_pay_method_request_SamsungPayRIBR extends \Robokassa\Payment\WC_WP_robokassa {
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

class payment_robokassa_pay_method_request_RapidaRIBEurosetR extends \Robokassa\Payment\WC_WP_robokassa {
    public function __construct() {
        $this->id = 'StoreEuroset';
        $this->method_title = 'Евросеть (Робокасса)';
        $this->long_name='Оплата через Евросеть (Робокасса)';
        $this->title = 'Евросеть';
        $this->description = 'Оплатить через Евросеть (Робокасса). Комиссия: 0';
        $this->commission = 0;

        parent::__construct();
    }
}

class payment_robokassa_pay_method_request_RapidaRIBSvyaznoyR extends \Robokassa\Payment\WC_WP_robokassa {
    public function __construct() {
        $this->id = 'StoreSvyaznoy';
        $this->method_title = 'Связной (Робокасса)';
        $this->long_name='Оплата через Связной (Робокасса)';
        $this->title = 'Связной';
        $this->description = 'Оплатить через Связной (Робокасса). Комиссия: 0';
        $this->commission = 0;

        parent::__construct();
    }
}

class payment_robokassa_pay_method_request_Biocoin extends \Robokassa\Payment\WC_WP_robokassa {
    public function __construct() {
        $this->id = 'BioCoin';
        $this->method_title = 'BioCoin (Робокасса)';
        $this->long_name='Оплата через BioCoin (Робокасса)';
        $this->title = 'BioCoin';
        $this->description = 'Оплатить через BioCoin (Робокасса). Комиссия: 0';
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
        $methods[] = 'payment_robokassa_pay_method_request_Qiwi50RIBRM';
        $methods[] = 'payment_robokassa_pay_method_request_YandexMerchantRIBR';
        $methods[] = 'payment_robokassa_pay_method_request_WMR30RM';
        $methods[] = 'payment_robokassa_pay_method_request_ElecsnetWalletRIBR';
        $methods[] = 'payment_robokassa_pay_method_request_W1RIBR';
        $methods[] = 'payment_robokassa_pay_method_request_AlfaBankRIBR';
        $methods[] = 'payment_robokassa_pay_method_request_VTB24RIBR';
        $methods[] = 'payment_robokassa_pay_method_request_W1RIBPSBR';
        $methods[] = 'payment_robokassa_pay_method_request_MINBankRIBR';
        $methods[] = 'payment_robokassa_pay_method_request_BSSIntezaRIBR';
        $methods[] = 'payment_robokassa_pay_method_request_BSSAvtovazbankR';
        $methods[] = 'payment_robokassa_pay_method_request_FacturaBinBank';
        $methods[] = 'payment_robokassa_pay_method_request_BSSFederalBankForInnovationAndDevelopmentR';
        $methods[] = 'payment_robokassa_pay_method_request_FacturaSovCom';
        $methods[] = 'payment_robokassa_pay_method_request_BSSNationalBankTRUSTR';
        $methods[] = 'payment_robokassa_pay_method_request_BANKOCEAN3R';
        $methods[] = 'payment_robokassa_pay_method_request_CardHalvaRIBR';
        $methods[] = 'payment_robokassa_pay_method_request_ApplePayRIBR';
        $methods[] = 'payment_robokassa_pay_method_request_SamsungPayRIBR';
        $methods[] = 'payment_robokassa_pay_method_request_RapidaRIBEurosetR';
        $methods[] = 'payment_robokassa_pay_method_request_RapidaRIBSvyaznoyR';
        $methods[] = 'payment_robokassa_pay_method_request_Biocoin';
    }

    return $methods;
}

