<?php

if (!\current_user_can('activate_plugins')) {
	return;
}

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
	\plugin_dir_url(__FILE__) . 'assets/css/admin-style.css'
);

$country_code = get_option('robokassa_country_code', 'RU');

$current_tab = 'main';

if (isset($_GET['li'])) {
	$current_tab = sanitize_key(wp_unslash((string) $_GET['li']));
}

$menu_items = array(
	'main' => array(
		'label' => 'Основные настройки',
		'description' => 'API-ключи и параметры подключения магазина.',
		'page' => 'robokassa_payment_main_rb',
	),
	'credit' => array(
		'label' => 'Виджет и бейдж',
		'description' => 'Внешний вид и сценарии работы витринных элементов.',
		'page' => 'robokassa_payment_credit',
	),
	'sms' => array(
		'label' => 'Настройки оповещений',
		'description' => 'Сценарии и шаблоны SMS для клиентов.',
		'page' => 'robokassa_payment_sms_rb',
	),
	'registration' => array(
		'label' => 'Регистрация Robokassa',
		'description' => 'Быстрый старт и заявка на подключение сервиса.',
		'page' => 'robokassa_payment_registration',
	),
);

if ($country_code === 'KZ') {
	unset($menu_items['credit']);
}

if (!isset($menu_items[$current_tab])) {
	$current_tab = 'main';
}


$base_url = admin_url('admin.php');

?>

<div class="robokassa-admin-wrapper">
	<div class="robokassa-admin-container">
		<div class="robokassa-admin-header">
			<h1 class="robokassa-admin-header__title">Настройки плагина Robokassa для WooCommerce</h1>
			<p class="robokassa-admin-header__subtitle">Управляйте интеграцией, уведомлениями и витринными решениями Robokassa из единого центра.</p>
		</div>

		<nav class="robokassa-card robokassa-card--compact">
			<ul class="robokassa-admin-nav" role="tablist">
				<?php foreach ($menu_items as $tab => $item) :
					$is_active = ($current_tab === $tab);
					$link = add_query_arg(
						array(
							'page' => $item['page'],
							'li' => $tab,
						),
						$base_url
					);
					?>
					<li>
						<a
								href="<?php echo esc_url($link); ?>"
								class="robokassa-admin-nav__item<?php echo $is_active ? ' robokassa-admin-nav__item--active' : ''; ?>"
								role="tab"
								aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>"
						>
							<span class="robokassa-admin-nav__label"><?php echo esc_html($item['label']); ?></span>
							<span class="robokassa-admin-nav__text"><?php echo esc_html($item['description']); ?></span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</nav>
	</div>
</div>
