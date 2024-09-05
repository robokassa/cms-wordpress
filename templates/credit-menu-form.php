<?php
$json_file_path = dirname(__FILE__) . '/../data/currencies.json';
$json_data = file_get_contents($json_file_path);
$json_decoded = json_decode($json_data, true);

$show_credit = false;
$show_installment = false;
$show_all_methods = false;

$installmentLabels = ['OTP3_300PSR', 'OTP4_300PSR', 'OTP6_300PSR'];

function checkLabels($array, &$show_credit, &$show_installment, $installmentLabels)
{
    foreach ($array as $key => $value) {
        if (is_array($value)) {
            if (isset($value['@attributes']['Label'])) {
                $label = $value['@attributes']['Label'];
                if ($label === 'OTPCredit_300PSR') {
                    $show_credit = true;
                } elseif (in_array($label, $installmentLabels)) {
                    $show_installment = true;
                }
            }
            checkLabels($value, $show_credit, $show_installment, $installmentLabels);
        }
    }
}

checkLabels($json_decoded, $show_credit, $show_installment, $installmentLabels);

if ($show_credit && $show_installment) {
    $show_credit = false;
    $show_installment = false;
    $show_all_methods = true;
}

$selected_method = 'false';
if ($show_credit) {
    $selected_method = 'credit';
} elseif ($show_installment) {
    $selected_method = 'installment';
} elseif ($show_all_methods) {
    $selected_method = 'all_methods';
}

update_option('robokassa_payment_credit_selected_method', $selected_method);

?>

<div class="credit" id="credit">
    <p class="mid_title_rb">Настройка оплаты в <a
                href="https://robokassa.com/content/rassrochka-i-kredit-na-platyezhnoy-stranitse-robokassa.html">Рассрочку и кредит</a></p>

    <table class="form-table">
        <tr valign="top">
            <th scope="row">Включить способ оплаты в рассрочку и кредит</th>
            <td>
                <select name="robokassa_credit">
                    <?php if (get_option('robokassa_credit') == 1) { ?>
                        <option selected="selected" value="1">Включено</option>
                        <option value="0">Отключено</option>
                    <?php } else { ?>
                        <option value="1">Включено</option>
                        <option selected="selected" value="0">Отключено</option>
                    <?php } ?>
                </select><br/>
                <span class="text-description">1. Пункт «Включено» добавляет возможность оплаты в рассрочку или кредит на вашем сайте с помощью виджета.<span>
                                        <br>2. Оплата проходит, минуя платёжную страницу Robokassa. Покупатель сразу переходит к оплате в рассрочку или кредит.
                                        <br>3. Минимальная сумма платежа в рассрочку или кредит — 1500 рублей, максимальная — 500.000 рублей.
            </td>
        </tr>

        <?php if ($selected_method): ?>
            <div class="credit-payment" id="credit-payment">
                <tr valign="top">
                    <th scope="row">Включить виджет</th>
                    <td>
                        <input type="radio" id="credit_widget_on" name="robokassa_payment_credit_widget_onoff"
                               value="true"
                            <?php echo (get_option('robokassa_payment_credit_widget_onoff', 'true') == 'true') ? 'checked="checked"' : ''; ?>>
                        <label for="credit_widget_on">Включен</label>

                        <input type="radio" id="credit_widget_off" name="robokassa_payment_credit_widget_onoff"
                               value="false"
                            <?php echo get_option('robokassa_payment_credit_widget_onoff') == 'false' ? 'checked="checked"' : ''; ?>>
                        <label for="credit_widget_on">Выключен</label><br>
                        <span class="text-description">После включения виджета покупатели увидят возможность оплатить в кредит или в рассрочку в карточке товара<span>
                    </td>
                </tr>
            </div>
        <?php endif; ?>
    </table>
</div>