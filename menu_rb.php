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
?>


<div class="menu_rb" align="center" style="margin-top: 50px;">
    <h1>Настройки плагина Робокасса для WooCommerce</h1>

    <br>

    <ul>
        <li>
            <a href="?page=robokassa_payment_main_rb" <?php echo ($_GET['li'] == 'main') ? 'class="active"' : ''; ?>>Основные настройки</a>
        </li>
        <li>
            <a href="?page=robokassa_payment_sms_rb" <?php echo ($_GET['li'] == 'sms') ? 'class="active"' : ''; ?>>Настройки оповещений</a>
        </li>
        <li>
            <a href="?page=robokassa_payment_robomarket_rb" <?php echo ($_GET['li'] == 'robomarket') ? 'class="active"' : ''; ?>>Настройки РобоМаркет</a>
        </li>
    </ul>
</div>