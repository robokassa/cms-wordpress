<?php

if(!\current_user_can('activate_plugins'))
    return;

\wp_enqueue_script(
    'robokassa_payment_clipboard',
    \plugin_dir_url(__FILE__) . 'assets/js/clipboard.min.js',
    array(),
    '1.6.0'
);

\wp_enqueue_script(
    'robokassa_payment_admin_config',
    \plugin_dir_url(__FILE__) . 'assets/js/admin-config.js'
);

\wp_enqueue_style(
    'robokassa_payment_admin_style_menu',
    \plugin_dir_url(__FILE__) . 'assets/css/menu.css'
);

?>

<div class="menu_rb" align="center" style="margin-top: 50px;">
    <h1>Настройки плагина Робокасса для WooCommerce</h1>

    <br>

    <ul>
        <li class="main_rb">
            <a href="?page=robokassa_payment_main_rb" <?php echo ($_GET['li'] == 'main') ? 'class="active"' : ''; ?>>Основные настройки</a>
        </li>
        <li class="credit" id="robokassa_payment_credit">
            <a href="?page=robokassa_payment_credit" <?php echo ($_GET['li'] == 'credit') ? 'class="active"' : ''; ?>>Оплата по частям и в кредит</a>
        </li>
        <li class="sms_rb" id="robokassa_payment_sms_rb">
            <a href="?page=robokassa_payment_sms_rb" <?php echo ($_GET['li'] == 'sms') ? 'class="active"' : ''; ?>>Настройки оповещений</a>
        </li>
        <li class="registration" id="robokassa_payment_registration">
            <a href="?page=robokassa_payment_registration" <?php echo ($_GET['li'] == 'registration') ? 'class="active"' : ''; ?>>Регистрация в сервисе Robokassa</a>
        </li>
    </ul>
</div>