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
	'robokassa_payment_admin_style_menu',
	\plugin_dir_url(__FILE__) . 'assets/css/admin-style.css'
);

$formProperties = [
	'robokassa_widget_enabled',
	'robokassa_widget_component',
	'robokassa_widget_theme',
	'robokassa_widget_size',
	'robokassa_widget_show_logo',
	'robokassa_widget_type',
	'robokassa_widget_border_radius',
	'robokassa_widget_has_second_line',
	'robokassa_widget_description_position',
	'robokassa_widget_color_scheme',
];

?>

<div class="robokassa-admin-wrapper">
	<div class="robokassa-admin-container">
		<div class="robokassa-card">
			<h2 class="robokassa-card__title">Виджет и бейдж Robokassa</h2>
			<p class="robokassa-card__description">Настройте внешний вид и сценарии отображения фирменных компонентов Robokassa на витрине магазина.</p>

			<form action="options.php" method="POST">
				<?php wp_nonce_field('update-options'); ?>

				<?php include dirname(__FILE__) . '/templates/widget-badge-menu-form.php'; ?>

				<input type="hidden" name="action" value="update"/>
				<input type="hidden" name="page_options" value="<?php echo \implode(',', $formProperties); ?>"/>

				<p class="submit">
					<input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>"/>
				</p>
			</form>
		</div>
	</div>
</div>
