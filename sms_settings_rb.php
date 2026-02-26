<?php

use Robokassa\Payment\RoboDataBase;

if (!\current_user_can('activate_plugins')) {
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

\wp_enqueue_style(
	'robokassa_payment_admin_style_menu',
	\plugin_dir_url(__FILE__) . 'assets/css/admin-style.css'
);

?>

<div class="robokassa-admin-wrapper">
	<div class="robokassa-admin-container">
		<div class="robokassa-card sms-settings">
			<h2 class="robokassa-card__title">Настройки SMS</h2>
			<p class="robokassa-card__description">Управляйте текстами и условиями отправки SMS-сообщений для
				покупателей вашего магазина.</p>
			<div class="robokassa-warning">
				<p>В теле сообщения доступны следующие метки:</p>
				<ul class="robokassa-help-list">
					<li><code class="robokassa-code">{address}</code> — адрес доставки заказа.</li>
					<li><code class="robokassa-code">{fio}</code> — ФИО покупателя.</li>
					<li><code class="robokassa-code">{order_number}</code> — номер заказа.</li>
				</ul>
			</div>

			<form method="post" action="options.php">
				<?php wp_nonce_field('update-options'); ?>

				<table class="robokassa-form-table form-table">
					<tr valign="top">
						<th scope="row">Транслитерация SMS сообщений</th>
						<td>
							<input type="checkbox" id="sms_translit"
								   name="robokassa_payment_sms_translit" <?php echo get_option('robokassa_payment_sms_translit') == 'on' ? 'checked="checked"' : ''; ?>
								   onchange="robokassa_payment_refresher();"><label for="sms_translit">Включить/Выключить</label>
						</td>
					</tr>

					<tr valign="top">
						<th scope="row">Уведомление об успешной оплате</th>
						<td>
							<input type="checkbox" id="sms1_enabled"
								   name="robokassa_payment_sms1_enabled" <?php echo get_option('robokassa_payment_sms1_enabled') == 'on' ? 'checked="checked"' : ''; ?>
								   onchange="robokassa_payment_refresher();"><label for="sms1_enabled">Включить/Выключить</label>
						</td>
					</tr>

					<tr valign="top">
						<th scope="row">Текст сообщения при успешной оплате</th>
						<td>
							<textarea name="robokassa_payment_sms1_text" id="sms1_text" class="custom-settings"
									  onkeyup="robokassa_payment_refresher();"><?php echo get_option('robokassa_payment_sms1_text'); ?></textarea>

							<p class="description" id="sms1_translit"></p>
							<p class="description float">
								<span id="counterX1" class="text"></span>
								написано, <span id="counterY1"></span> осталось (<span id="counterZ1"></span> смс )
							</p>
						</td>
					</tr>

					<tr valign="top">
						<th scope="row">Уведомление о завершении заказа</th>
						<td>
							<input type="checkbox" id="sms2_enabled"
								   name="robokassa_payment_sms2_enabled" <?php echo get_option('robokassa_payment_sms2_enabled') == 'on' ? 'checked="checked"' : ''; ?>
								   onchange="robokassa_payment_refresher();"><label for="sms2_enabled">Включить/Выключить</label>
						</td>
					</tr>

					<tr valign="top">
						<th scope="row">Текст сообщения при завершении заказа</th>
						<td>
							<textarea name="robokassa_payment_sms2_text" id="sms2_text" class="custom-settings"
									  onkeyup="robokassa_payment_refresher();"><?php echo get_option('robokassa_payment_sms2_text'); ?></textarea>

							<p class="description" id="sms2_translit"></p>
							<p class="description float">
								<span id="counterX2" class="text"></span>
								написано, <span id="counterY2"></span> осталось (<span id="counterZ2"></span> смс )
							</p>
						</td>
					</tr>
				</table>

				<input type="hidden" name="action" value="update"/>
				<input type="hidden" name="page_options"
					   value="robokassa_payment_sms_translit,robokassa_payment_sms1_enabled,robokassa_payment_sms1_text,robokassa_payment_sms2_enabled,robokassa_payment_sms2_text"/>

				<p class="submit">
					<input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>"/>
				</p>
			</form>
		</div>
	</div>
</div>

<?php
global $wpdb;

$dataBase = new RoboDataBase($wpdb);
$table_name = $wpdb->prefix . 'woocommerce_order_items';
$sql = "SELECT order_id FROM {$table_name} ORDER BY order_item_id DESC LIMIT 1";
$next_order_number = (int)$dataBase->getVar($sql);

if ($next_order_number < 0) {
	$next_order_number = 0;
}

\wp_add_inline_script(
	'robokassa_payment_admin_sms_settings_next_order',
	'var next_order = robokassa_payment_countDigits(' . ($next_order_number + 1) . ');'
);
?>
