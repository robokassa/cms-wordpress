<?php
$robokassa = new \Robokassa\Payment\RobokassaPayAPI(
    \get_option('robokassa_payment_MerchantLogin'),
    \get_option('robokassa_payment_shoppass1'),
    \get_option('robokassa_payment_shoppass2')
);

$currLabels = $robokassa->getCurrLabels();

if ($currLabels) {
    $labelsPath = __DIR__ . '/data/currencies.json';

    $json = json_encode($currLabels, JSON_PRETTY_PRINT);

    if (file_put_contents($labelsPath, $json)) {
    } else {
        echo "Ошибка при сохранении данных в файл";
    }
}
