<?php

if(!\current_user_can('activate_plugins'))
{

    echo '<br /><br />
				<div class="error notice">
	                <p>У Вас не хватает прав на настройку компонента</p>
				</div>
			';
    return;
}

\wp_enqueue_script(
    'robokassa_payment_admin_sms_settings',
    \plugin_dir_url(__FILE__) . 'assets/js/admin-sms-settings.js'
);
?>
<div class="content_holder sms-settings">
    <p class="big_title_rb">Настройки СМС</p>

    <form method="post" action="options.php">
        <?php wp_nonce_field('update-options'); ?>

        <table class="form-table">
            <p>
            <h4>
                В теле сообщения вы можете использовать следующие метки:
                <br>
                {address} = адрес доставки заказа
                <br>
                {fio} = ФИО покупателя
                <br>
                {order_number} = номер заказа
            </h4>
            </p>

            <tr valign="top">
                <th scope="row">Транслитерация СМС сообщений</th>
                <td>
                    <input type="checkbox" id="sms_translit" name="robokassa_payment_sms_translit" <?php echo get_option('robokassa_payment_sms_translit') == 'on' ? 'checked="checked"' : ''; ?> onchange="robokassa_payment_refresher();"><label for="sms_translit">Включить/Выключить</label>
                </td>
            </tr>

            <tr valign="top">
                <th scope="row">Включить оповещение по СМС при успешной оплате</th>
                <td>
                    <input type="checkbox" id="sms1_enabled" name="robokassa_payment_sms1_enabled" <?php echo get_option('robokassa_payment_sms1_enabled') == 'on' ? 'checked="checked"' : ''; ?> onchange="robokassa_payment_refresher();"><label for="sms1_enabled">Включить/Выключить</label>
                </td>
            </tr>

            <tr valign="top">
                <th scope="row">Текст сообщения при успешной оплате</th>
                <td>
                    <textarea name="robokassa_payment_sms1_text" id="sms1_text" class="custom-settings" onkeyup="robokassa_payment_refresher();"><?php echo get_option('robokassa_payment_sms1_text') ?></textarea>

                    <p class="description" id="sms1_translit"></p>
                    <p class="description float">
                        <span id="counterX1" class="text"></span>
                        написано, <span id="counterY1"></span> осталось (<span id="counterZ1"></span> смс )
                    </p>
                </td>
            </tr>

            <tr valign="top">
                <th scope="row">Включить оповещение по СМС при завершении заказа</th>
                <td>
                    <input type="checkbox" id="sms2_enabled"
                           name="robokassa_payment_sms2_enabled" <?php echo get_option('robokassa_payment_sms2_enabled') == 'on'
                        ? 'checked="checked"' : ''; ?> onchange="robokassa_payment_refresher();"><label
                            for="sms2_enabled">Включить/Выключить</label>
                </td>
            </tr>

            <tr valign="top">
                <th scope="row">Текст сообщения при завершении заказа</th>
                <td>
                    <textarea name="robokassa_payment_sms2_text" id="sms2_text" class="custom-settings" onkeyup="robokassa_payment_refresher();"><?php echo get_option('robokassa_payment_sms2_text') ?></textarea>

                    <p class="description" id="sms2_translit"></p>
                    <p class="description float">
                        <span id="counterX2" class="text"></span>
                        написано, <span id="counterY2"></span> осталось (<span id="counterZ2"></span> смс )
                    </p>
                </td>
            </tr>
        </table>

        <input type="hidden" name="action" value="update"/>
        <input type="hidden" name="page_options" value="robokassa_payment_sms_translit,robokassa_payment_sms1_enabled,robokassa_payment_sms1_text,robokassa_payment_sms2_enabled,robokassa_payment_sms2_text"/>

        <p class="submit">
            <input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>"/>
        </p>
    </form>

    <?php
    $sql = "SELECT order_id FROM `wp_woocommerce_order_items` ORDER BY `wp_woocommerce_order_items`.`order_item_id` DESC LIMIT 1";
    $dataBase = new \Robokassa\Payment\RoboDataBase(mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME));

    $result = $dataBase->query($sql);

    if($result instanceof \mysqli_result)
    {

        $next_order_number = mysqli_fetch_array($dataBase->query($sql));
        $next_order_number = $next_order_number['order_id'];
    }
    else
    {
        $next_order_number = 0;
    }

    \wp_add_inline_script(
        'robokassa_payment_admin_sms_settings_next_order',
        'var next_order = robokassa_payment_countDigits('.++$next_order_number.');'
    );
    ?>
</div>