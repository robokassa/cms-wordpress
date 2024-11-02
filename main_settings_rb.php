<?php

if (!\current_user_can('activate_plugins')) {

    echo '<br /><br />
				<div class="error notice">
	                <p>У Вас не хватает прав на настройку компонента</p>
				</div>
			';
    return;
}

\wp_enqueue_script(
    'robokassa_payment_admin_main_payment',
    \plugin_dir_url(__FILE__) . 'assets/js/admin-payment.js'
);

\wp_enqueue_style(
    'robokassa_payment_admin_style_main',
    \plugin_dir_url(__FILE__) . 'assets/css/main.css'
);
?>

<div class="content_holder">
    <?php

    if (isset($_REQUEST['settings-updated']))
    {
        include 'labelsGenerator.php';

        if (get_option('robokassa_country_code') == 'RU') {
            wp_redirect('admin.php?page=robokassa_payment_credit');
            exit;
        }
    }

    $formProperties = [
        'robokassa_payment_wc_robokassa_enabled',
        'robokassa_payment_MerchantLogin',
        'robokassa_payment_shoppass1',
        'robokassa_payment_shoppass2',
        'robokassa_payment_test_onoff',
        'robokassa_payment_testshoppass1',
        'robokassa_payment_testshoppass2',
        'robokassa_payment_sno',
        'robokassa_payment_tax',
        'robokassa_payment_who_commission',
        'robokassa_payment_size_commission',
        'robokassa_payment_paytype',
        'robokassa_payment_SuccessURL',
        'robokassa_payment_FailURL',
        'robokassa_payment_paymentMethod',
        'robokassa_payment_paymentObject',
        'robokassa_patyment_markup',
        'robokassa_culture',
        'robokassa_iframe',
        'robokassa_country_code',
        'robokassa_out_currency',
        'robokassa_agreement_text',
        'robokassa_agreement_pd_link',
        'robokassa_agreement_oferta_link',
        'robokassa_payment_hold_onoff',
    ];

    require_once __DIR__ . '/labelsClasses.php';

    foreach ((array)robokassa_payment_add_WC_WP_robokassa_class() as $class):
        $method = new $class;
        $formProperties[] = 'RobokassaOrderPageTitle_' . $method->id;
        $formProperties[] = 'RobokassaOrderPageDescription_' . $method->id;
    endforeach;
    ?>

    <div class="main-settings">
        <div align="left"><p class="big_title_rb">Помощь и инструкция по установке</p></div>
        <p><b>1) Введите данные API в разделе "Основные настройки"</b></p>
        <p><b>2) В личном кабинете на сайте Робокассы введите следующие URL адреса:</b></p>
        <table>
            <tr>
                <td>ResultURL:</td>
                <td><code id="ResultURL"><?php echo site_url('/?robokassa=result'); ?></code></td>
                <td>
                    <button class="btn btn-default btn-clipboard btn-main" data-clipboard-target="#ResultURL"
                            onclick="event.preventDefault();">
                        Скопировать
                    </button>
                </td>
            </tr>
            <tr>
                <td>SuccessURL:</td>
                <td><code id="SuccessURL"><?php echo site_url('/?robokassa=success'); ?></td>
                <td>
                    <button class="btn btn-default btn-clipboard btn-main" data-clipboard-target="#SuccessURL"
                            onclick="event.preventDefault();">
                        Скопировать
                    </button>
                </td>
            </tr>
            <tr>
                <td>FailURL:</td>
                <td><code id="FailURL"><?php echo site_url('/?robokassa=fail'); ?></code></td>
                <td>
                    <button class="btn btn-default btn-clipboard btn-main" data-clipboard-target="#FailURL"
                            onclick="event.preventDefault();">
                        Скопировать
                    </button>
                </td>
            </tr>
        </table>

        <p>Метод отсылки данных <code>POST</code></p>
        <p>Алгоритм расчета хеша<code>MD5</code></p>
        <p><b>3) После введите логин и пароли магазина в соответсвующие поля ниже</b></p>
        <p class="big_title_rb">Основные настройки</p>

        <form action="options.php" method="POST">
            <?php wp_nonce_field('update-options'); ?>

            <p class="mid_title_rb">Настройки соединения</p>

            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Оплата через Робокассу</th>
                    <td>
                        <input type="radio" id="enabled_on" name="robokassa_payment_wc_robokassa_enabled" value="yes"
                            <?php echo get_option('robokassa_payment_wc_robokassa_enabled') == 'yes' ? 'checked="checked"' : ''; ?>>
                        <label for="enabled_on">Включить</label>

                        <input type="radio" id="enabled_off" name="robokassa_payment_wc_robokassa_enabled" value="no"
                            <?php echo get_option('robokassa_payment_wc_robokassa_enabled') == 'no' ? 'checked="checked"' : ''; ?>>
                        <label for="enabled_off">Отключить</label>
                    </td>
                </tr>

                <tr valign="top">
                    <th scope="row">Заголовок на странице оформления заказа</th>
                    <td>
                        <input type="text" name="RobokassaOrderPageTitle_robokassa"
                               value="<?php echo get_option('RobokassaOrderPageTitle_robokassa'); ?>"/>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Описание на странице оформления заказа</th>
                    <td>
                        <input type="text" name="RobokassaOrderPageDescription_robokassa"
                               value="<?php echo get_option('RobokassaOrderPageDescription_robokassa'); ?>"/>
                    </td>
                </tr>

                <tr valign="top">
                    <th scope="row">Страна магазина</th>
                    <td>
                        <select id="robokassa_country_code" name="robokassa_country_code">
                            <option value="RU" <?php echo((get_option('robokassa_country_code') == 'RU') ? ' selected' : ''); ?>>
                                Россия
                            </option>
                            <option value="KZ" <?php echo((get_option('robokassa_country_code') == 'KZ') ? ' selected' : ''); ?>>
                                Казахстан
                            </option>
                        </select>
                    </td>
                </tr>

                <tr valign="top">
                    <th scope="row">Валюта заказа</th>
                    <td>

                        <select id="robokassa_out_currency" name="robokassa_out_currency">
                            <?php

                            $woocommerce_currency = get_option('woocommerce_currency');

                            $currencies = array(
                                'RUR' => 'Рубли',
                                'USD' => 'Доллары',
                                'EUR' => 'Евро',
                                'KZT' => 'Тенге',
                                $woocommerce_currency => 'Валюта по умолчанию из настроек WC',
                            );

                            $valid_wc_currencies = array('RUR', 'USD', 'EUR', 'KZT');

                            if ($woocommerce_currency === $woocommerce_currency) {
                                $currencies = array_intersect_key($currencies, array_flip($valid_wc_currencies));
                            }

                            $currencies = ['' => 'Не передавать значение валюты'] + $currencies;

                            foreach ($currencies as $currency_code => $currency_name) {
                                $selected = (get_option('robokassa_out_currency') === $currency_code) ? 'selected' : '';
                                echo '<option value="' . esc_attr($currency_code) . '" ' . $selected . '>';
                                echo esc_html($currency_name);
                                echo '</option>';
                            }
                            ?>
                        </select>
                    </td>
                </tr>




                <tr valign="top">
                    <th scope="row">Идентификатор магазина</th>
                    <td><input type="text" name="robokassa_payment_MerchantLogin" value="<?php
                        echo get_option('robokassa_payment_MerchantLogin'); ?>"/></td>
                </tr>

                <tr valign="top">
                    <th scope="row">Пароль магазина #1</th>
                    <td><input type="password" name="robokassa_payment_shoppass1" value="<?php
                        echo get_option('robokassa_payment_shoppass1'); ?>"/></td>
                </tr>

                <tr valign="top">
                    <th scope="row">Пароль магазина #2</th>
                    <td><input type="password" name="robokassa_payment_shoppass2" value="<?php
                        echo get_option('robokassa_payment_shoppass2'); ?>"/></td>
                </tr>

                <tr valign="top">
                    <th scope="row">Язык интерфейса робокассы</th>
                    <td>
                        <select name="robokassa_culture">
                            <?php foreach (\Robokassa\Payment\Helper::$culture as $culture): ?>
                                <option<?php if (get_option('robokassa_culture') == $culture['code']): ?> selected="selected"<?php endif; ?>
                                        value="<?= $culture['code']; ?>"><?= $culture['title']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Включить iframe</th>
                    <td>
                        <select name="robokassa_iframe">
                            <?php if (get_option('robokassa_iframe') == 1) { ?>
                                <option selected="selected" value="1">Включено</option>
                                <option value="0">Отключено</option>
                            <?php } else { ?>
                                <option value="1">Включено</option>
                                <option selected="selected" value="0">Отключено</option>
                            <?php } ?>
                        </select><br/>
                        <span class="text-description">При включённом iframe, способов оплаты меньше, чем в обычной платежной странице - только карты, Apple и Samsung pay, Qiwi. incurlabel работает, но ограничено.<span>
                    </td>
                </tr>
            </table>

            <? if (function_exists('wcs_order_contains_subscription')) { ?>
                <p class="mid_title_rb">Настройки для Woocommerce Subscriptions</p>

                <a class="spoiler_links button">Показать/скрыть</a>

                <div class="spoiler_body">
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row">Текст согласия с правилами на списания по подписке</th>
                            <td>
                                <input type="text" name="robokassa_agreement_text" value="<?php echo htmlspecialchars(get_option('robokassa_agreement_text') ?: 'Я даю согласие на регулярные списания, на <a href="%s">обработку персональных данных</a> и принимаю условия <a href="%s">публичной оферты</a>.'); ?>"/>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Ссылка на согласие на обработку ПД</th>
                            <td>
                                <input type="text" name="robokassa_agreement_pd_link" value="<?php echo htmlspecialchars(get_option('robokassa_agreement_pd_link')); ?>"/>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Ссылка на оферту</th>
                            <td>
                                <input type="text" name="robokassa_agreement_oferta_link" value="<?php echo htmlspecialchars(get_option('robokassa_agreement_oferta_link')); ?>"/>
                            </td>
                        </tr>
                    </table>
                </div>
            <? } ?>

            <p class="mid_title_rb">Настройки тестового соединения</p>

            <a class="spoiler_links button">Показать/скрыть</a>

            <div class="spoiler_body">
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Тестовый режим</th>
                        <td>
                            <input type="radio" id="test_on" name="robokassa_payment_test_onoff" value="true"
                                <?php echo get_option('robokassa_payment_test_onoff') == 'true' ? 'checked="checked"' : ''; ?>>
                            <label for="test_on">Включить</label>

                            <input type="radio" id="test_off" name="robokassa_payment_test_onoff" value="false"
                                <?php echo get_option('robokassa_payment_test_onoff') == 'false' ? 'checked="checked"' : ''; ?>>
                            <label for="test_off">Отключить</label>
                        </td>
                    </tr>

                    <tr valign="top">
                        <th scope="row">Тестовый пароль магазина #1</th>
                        <td><input type="password" name="robokassa_payment_testshoppass1"
                                   value="<?php echo get_option('robokassa_payment_testshoppass1'); ?>"/>
                        </td>
                    </tr>

                    <tr valign="top">
                        <th scope="row">Тестовый пароль магазина #2</th>
                        <td><input type="password" name="robokassa_payment_testshoppass2"
                                   value="<?php echo get_option('robokassa_payment_testshoppass2'); ?>"/>
                        </td>
                    </tr>
                </table>
            </div>

            <p class="mid_title_rb">Общие настройки</p>

            <a class="spoiler_links button">Показать/скрыть</a>

            <div class="spoiler_body">
                <table class="form-table">
                    <tr valign="top" id="sno">
                        <th scope="row">Система налогообложения</th>
                        <td>
                            <select id="sno_select" name="robokassa_payment_sno" onchange="spoleer();">
                                <option value="fckoff" <?php echo((get_option('robokassa_payment_sno') == 'fckoff') ? ' selected' : ''); ?>>
                                    Не
                                    передавать
                                </option>
                                <option value="osn" <?php echo((get_option('robokassa_payment_sno') == 'osn') ? ' selected' : ''); ?>>
                                    Общая
                                    СН
                                </option>
                                <option value="usn_income" <?php echo((get_option('robokassa_payment_sno') == 'usn_income') ? ' selected'
                                    : ''); ?>>Упрощенная СН (доходы)
                                </option>
                                <option value="usn_income_outcome" <?php echo((get_option('robokassa_payment_sno') == 'usn_income_outcome')
                                    ? ' selected' : ''); ?>>Упрощенная СН (доходы минус расходы)
                                </option>
                                <option value="envd" <?php echo((get_option('robokassa_payment_sno') == 'envd') ? ' selected' : ''); ?>>
                                    Единый
                                    налог на вмененный доход
                                </option>
                                <option value="esn" <?php echo((get_option('robokassa_payment_sno') == 'esn') ? ' selected' : ''); ?>>
                                    Единый
                                    сельскохозяйственный налог
                                </option>
                                <option value="patent" <?php echo((get_option('robokassa_payment_sno') == 'patent') ? ' selected' : ''); ?>>
                                    Патентная СН
                                </option>
                            </select>
                        </td>
                    </tr>

                    <tr valign="top" id="payment_method">
                        <th scope="row">Признак способа расчёта</th>
                        <td>
                            <select id="payment_method_select" name="robokassa_payment_paymentMethod"
                                    onchange="spoleer();">
                                <option value="">Не выбрано</option>
                                <?php foreach (\Robokassa\Payment\Helper::$paymentMethods as $paymentMethod): ?>
                                    <option <?php if (\get_option('robokassa_payment_paymentMethod') === $paymentMethod['code']): ?> selected="selected"<?php endif; ?>
                                            value="<?php echo $paymentMethod['code']; ?>"><?php echo $paymentMethod['title']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr valign="top" id="payment_object">
                        <th scope="row">Признак предмета расчёта</th>
                        <td>
                            <select id="payment_object_select" name="robokassa_payment_paymentObject"
                                    onchange="spoleer();">
                                <option value="">Не выбрано</option>
                                <?php foreach (\Robokassa\Payment\Helper::$paymentObjects as $paymentObject): ?>
                                    <option <?php if (\get_option('robokassa_payment_paymentObject') === $paymentObject['code']): ?>
                                            selected="selected"
                                            <?php endif; ?>value="<?php echo $paymentObject['code']; ?>"><?php echo $paymentObject['title']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>

                    <tr valign="top" id="tax">
                        <th scope="row">Налоговая ставка</th>
                        <td>
                            <select id="tax_select" name="robokassa_payment_tax" onchange="spoleer();">
                                <option value="none" <?php echo((get_option('robokassa_payment_tax') == 'none') ? ' selected' : ''); ?>>
                                    Не передавать
                                </option>
                                <option value="none" <?php echo((get_option('robokassa_payment_tax') == 'none') ? ' selected' : ''); ?>>
                                    Без НДС
                                </option>
                                <option value="vat0" <?php echo((get_option('robokassa_payment_tax') == 'vat0') ? ' selected' : ''); ?>>
                                    НДС по ставке 0%
                                </option>
                                <option value="vat10" <?php echo((get_option('robokassa_payment_tax') == 'vat10') ? ' selected' : ''); ?>>
                                    НДС чека по ставке 10%
                                </option>
                                <option value="vat20" <?php echo((get_option('robokassa_payment_tax') == 'vat20') ? ' selected' : ''); ?>>
                                    НДС чека по ставке 20%
                                </option>
                                <option value="vat110" <?php echo((get_option('robokassa_payment_tax') == 'vat110') ? ' selected' : ''); ?>>
                                    НДС чека по расчетной ставке 10/110
                                </option>
                                <option value="vat118" <?php echo((get_option('robokassa_payment_tax') == 'vat120') ? ' selected' : ''); ?>>
                                    НДС чека по расчетной ставке 20/120
                                </option>
                                <option value="vat8" <?php echo((get_option('robokassa_payment_tax') == 'vat8') ? ' selected' : ''); ?>>
                                    НДС чека по ставке 8% (Казахстан)
                                </option>
                                <option value="vat12" <?php echo((get_option('robokassa_payment_tax') == 'vat12') ? ' selected' : ''); ?>>
                                    НДС чека по ставке 12% (Казахстан)
                                </option>
                            </select>
                        </td>
                    </tr>

                    <!--                    <tr valign="top" id="payment-method-rk">
                        <th scope="row">Выбор способа оплаты</th>
                        <td>
                            <input type="radio" id="robopaytype" name="robokassa_payment_paytype"
                                   value="false" <?php /*echo get_option('robokassa_payment_paytype') == 'false' ? 'checked="checked"' : ''; */ ?>><label
                                    for="robopaytype">В Робокассе</label>
                            <input type="radio" id="shoppaytype" name="robokassa_payment_paytype"
                                   value="true" <?php /*echo get_option('robokassa_payment_paytype') == 'true' ? 'checked="checked"'
                                : ''; */ ?>><label for="shoppaytype">В магазине</label>
                        </td>
                    </tr>-->

                    <tr valign="top">
                        <th scope="row">Отложенные платежи</th>
                        <td>
                            <select name="robokassa_payment_hold_onoff">
                                <?php if (get_option('robokassa_payment_hold_onoff') == 1) { ?>
                                    <option selected="selected" value="1">Включено</option>
                                    <option value="0">Отключено</option>
                                <?php } else { ?>
                                    <option value="1">Включено</option>
                                    <option selected="selected" value="0">Отключено</option>
                                <?php } ?>
                            </select><br/>
                            <span class="text-description">Данная <a href="https://docs.robokassa.ru/holding/">услуга</a> доступна только по предварительному согласованию.<span><br />
                            <span class="text-description">Функционал доступен только при использовании банковских карт.<span><br />
                            <span class="text-description"><a href="https://docs.robokassa.ru/media/guides/hold_woocommerce.pdf">Инструкция по настройке</a></span>
                        </td>
                    </tr>

                    <tr valign="top">
                        <th scope="row">Страница успеха платежа</th>
                        <td>
                            <select id="SuccessURL" name="robokassa_payment_SuccessURL">
                                <option value="wc_success" <?php echo((get_option('robokassa_payment_SuccessURL') == 'wc_success')
                                    ? ' selected' : ''); ?>>Страница "Заказ принят" от WooCommerce
                                </option>
                                <option value="wc_checkout" <?php echo((get_option('robokassa_payment_SuccessURL') == 'wc_checkout')
                                    ? ' selected' : ''); ?>>Страница оформления заказа от WooCommerce
                                </option>
                                <?php
                                if (get_pages()) {
                                    foreach (get_pages() as $page) {
                                        $selected = ($page->ID == get_option('robokassa_payment_SuccessURL')) ? ' selected' : '';
                                        echo '<option value="' . $page->ID . '"' . $selected . '>' . $page->post_title . '</option>';
                                    }
                                }
                                ?>
                            </select><br/>
                            <span class="text-description">Эту страницу увидит покупатель, когда оплатит заказ<span>
                        </td>
                    </tr>

                    <tr valign="top">
                        <th scope="row">Страница отказа</th>
                        <td>
                            <select id="FailURL" name="robokassa_payment_FailURL">
                                <option value="wc_checkout" <?php echo((get_option('robokassa_payment_FailURL') == 'wc_checkout')
                                    ? ' selected' : ''); ?>>Страница оформления заказа от WooCommerce
                                </option>
                                <option value="wc_payment" <?php echo((get_option('robokassa_payment_FailURL') == 'wc_payment') ? ' selected'
                                    : ''); ?>>Страница оплаты заказа от WooCommerce
                                </option>
                                <?php
                                if ($pages = get_pages()) {
                                    foreach ($pages as $page) {
                                        $selected = ($page->ID == get_option('robokassa_payment_FailURL')) ? ' selected' : '';
                                        echo '<option value="' . $page->ID . '"' . $selected . '>' . $page->post_title . '</option>';
                                    }
                                }
                                ?>
                            </select><br/>
                            <span class="text-description">Эту страницу увидит покупатель, если что-то пойдет не так: например, если ему не хватит денег на карте<span>
                        </td>
                    </tr>

                </table>
            </div>

            <input type="hidden" name="action" value="update"/>
            <input type="hidden" name="page_options" value="<?php echo \implode(',', $formProperties); ?>"/>

            <p class="submit">
                <input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>"/>
            </p>

        </form>
    </div>
</div>