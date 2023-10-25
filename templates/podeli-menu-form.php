<div class="podeli" id="podeli">
    <p class="mid_title_rb">Настройка оплаты по частям через сервис <a
            href="https://robokassa.com/offers/podeli.php">"Подели"</a></p>

    <table class="form-table">
        <tr valign="top">
            <th scope="row">Включить способ оплаты по частям "Подели"</th>
            <td>
                <select name="robokassa_podeli">
                    <?php if (get_option('robokassa_podeli') == 1) { ?>
                        <option selected="selected" value="1">Включено</option>
                        <option value="0">Отключено</option>
                    <?php } else { ?>
                        <option value="1">Включено</option>
                        <option selected="selected" value="0">Отключено</option>
                    <?php } ?>
                </select><br/>
                <span class="text-description">1. Пункт «Включено» добавляет возможность оплаты через «Подели» на вашем сайте с помощью виджета.<span>
                                        <br>2. Оплата проходит, минуя платёжную страницу Robokassa. Покупатель сразу переходит к оплате частями «Подели».
                                        <br>3. Минимальная сумма платежа через «Подели» — 300 рублей, максимальная — 30.000 рублей.
            </td>
        </tr>
        <!--                    <tr valign="top">
                        <th scope="row">Заголовок на странице оформления заказа</th>
                        <td>
                            <input type="text" name="RobokassaOrderPageTitle_Podeli" value="<?php /*echo get_option('RobokassaOrderPageTitle_Podeli'); */ ?>"/>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Описание на странице оформления заказа</th>
                        <td>
                            <input type="text" name="RobokassaOrderPageDescription_Podeli" value="<?php /*echo get_option('RobokassaOrderPageDescription_Podeli'); */ ?>"/>
                        </td>
                    </tr>-->
        <tr valign="top">
            <th scope="row">Включить виджет</th>
            <td>
                <input type="radio" id="podeli_widget_on" name="robokassa_payment_podeli_widget_onoff"
                       value="true"
                    <?php echo (get_option('robokassa_payment_podeli_widget_onoff', 'true') == 'true') ? 'checked="checked"' : ''; ?>>
                <label for="podeli_widget_on">Включен</label>

                <input type="radio" id="podeli_widget_off" name="robokassa_payment_podeli_widget_onoff"
                       value="false"
                    <?php echo get_option('robokassa_payment_podeli_widget_onoff') == 'false' ? 'checked="checked"' : ''; ?>>
                <label for="podeli_widget_on">Выключен</label><br>
                <span class="text-description">После включения виджета покупатели увидят способ оплаты частями в корзине и в карточке товара.<span>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row">Выбрать оформление виджета</th>
            <td>
                <select id="robokassa_podeli_widget_style" name="robokassa_podeli_widget_style"
                        onchange="updateDescription()">
                    <option value="0" <?php echo((get_option("robokassa_podeli_widget_style") == "0") ? "selected" : ""); ?>>
                        Выбор оплаты частями в корзине
                    </option>
                    <option value="1" <?php echo((get_option("robokassa_podeli_widget_style") == "1") ? "selected" : ""); ?>>
                        Переход на оформление заказа
                    </option>
                </select>
                <br>
                <span class="text-description" id="description">
            <?php
            if (get_option("robokassa_podeli_widget_style") == "0") {
                echo 'Доступны 2 варианта оформления виджета:<br>
                1. Упрощенная версия виджета для карточки товара с графиком платежей, но без кнопки «Оплатить»;<br>
                2. Развернутая версия виджета для корзины.';
            } elseif (get_option("robokassa_podeli_widget_style") == "1") {
                echo 'Доступны 2 варианта оформления виджета:<br>
                1. Версия виджета для карточки товара с графиком платежей и кнопкой «Оплатить»;<br>
                2. Версия виджета для корзины.';
            }
            ?>
        </span>
            </td>
        </tr>
    </table>
</div>