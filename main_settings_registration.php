<?php

use Robokassa\Payment\Util;

if (!\current_user_can('activate_plugins')) {
	echo '<br /><br />
				<div class="error notice">
	                <p>У Вас не хватает прав на настройку компонента</p>
				</div>
			';
	return;
}

\wp_enqueue_style(
	'robokassa_payment_admin_style_menu',
	\plugin_dir_url(__FILE__) . 'assets/css/admin-style.css'
);

?>

<div class="robokassa-admin-wrapper">
	<div class="robokassa-admin-container">
		<div class="robokassa-card robokassa-card--compact">
			<h2 class="robokassa-card__title">Регистрация в сервисе Robokassa</h2>
			<p class="robokassa-card__description">Отправьте заявку на подключение Robokassa прямо из админ-панели и начните принимать платежи без лишних шагов.</p>
			<div class="robokassa-frame-wrapper">
				<iframe onload="robokassaRegistrationInit();" id="robokassa-registration-frame" src="https://reg2.robokassa.ru/register/wordpress" title="Регистрация Robokassa" height="1000"></iframe>
			</div>
		</div>
	</div>
</div>

<script>
	const robokassaRegistrationData = {
		rk_reg: true,
		site_url: '<?php echo Util::siteUrl(); ?>',
		result_url: '<?php echo Util::siteUrl('/?robokassa=result'); ?>',
		success_url: '<?php echo Util::siteUrl('/?robokassa=success'); ?>',
		fail_url: '<?php echo Util::siteUrl('/?robokassa=fail'); ?>',
		callback_url: '<?php echo Util::siteUrl('/?robokassa=registration'); ?>'
	};

	function robokassaRegistrationInit() {
		const frame = document.getElementById('robokassa-registration-frame');
		if (!frame) {
			return;
		}
		frame.contentWindow.postMessage(robokassaRegistrationData, '*');
	}
</script>
