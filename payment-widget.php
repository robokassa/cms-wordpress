<?php

add_action('woocommerce_single_product_summary', 'payment_product_widget', 25);
add_action('woocommerce_before_shop_loop_item_title', 'robokassa_product_loop_badge', 11);
add_action('wp_enqueue_scripts', 'robokassa_widget_enqueue_assets');

/**
 * Выводит компонент Robokassa на странице товара.
 *
 * @return void
 */
function payment_product_widget() {
	global $product;

	robokassa_widget_render_for_product($product);
}

/**
 * Выводит бейдж Robokassa поверх изображения товара в листинге.
 *
 * @return void
 */
function robokassa_product_loop_badge() {
	global $product;

	if (!$product instanceof WC_Product) {
		return;
	}

	if (get_option('robokassa_catalog_badge_enabled', 'false') !== 'true') {
		return;
	}

	if (!robokassa_catalog_badge_has_installment_method()) {
		return;
	}

	$badge = robokassa_catalog_badge_get_settings();
	if (!file_exists(__DIR__ . '/assets/images/robokassa-badges/' . $badge['file'])) {
		return;
	}

	$src = plugin_dir_url(__FILE__) . 'assets/images/robokassa-badges/' . $badge['file'];

	echo '<div class="robokassa-widget-wrapper robokassa-widget-wrapper--loop robokassa-widget-wrapper--loop-overlay">';
	echo '<img class="robokassa-catalog-badge robokassa-catalog-badge--' . esc_attr($badge['size']) . '" src="' . esc_url($src) . '" alt="' . esc_attr__('Оплата частями Robokassa', 'robokassa') . '" loading="lazy" decoding="async" width="' . esc_attr($badge['width']) . '" height="' . esc_attr($badge['height']) . '" />';
	echo '</div>';
}

/**
 * Выводит компонент Robokassa для переданного товара.
 *
 * @param WC_Product|null $product
 * @param string|null     $component
 * @param string          $context
 *
 * @return void
 */
function robokassa_widget_render_for_product($product, $component = null, $context = 'single') {
	if (!$product instanceof WC_Product) {
		return;
	}

	$settings = robokassa_widget_get_settings();
	if (!$settings['enabled']) {
		return;
	}
	if ($component !== null) {
		$settings['component'] = $component;
	}
	if ($context === 'loop' && $settings['component'] === 'badge') {
		$settings['size'] = 's';
	}

	$amount = wc_get_price_to_display($product);
	if ($amount <= 0) {
		return;
	}

	$signature = robokassa_widget_get_signature_data($amount);
	if (!$signature) {
		return;
	}

	$checkout_url = robokassa_widget_prepare_checkout_url($product->get_id(), $settings);
	$attributes = robokassa_widget_prepare_attributes($settings, $signature, $checkout_url);

	robokassa_widget_output_component($settings['component'], $attributes, $product->get_id(), $checkout_url, $settings, $context);
}

/**
 * Возвращает настройки отображения компонента.
 *
 * @return array
 */
function robokassa_widget_get_settings() {
	$settings = [
		'enabled' => get_option('robokassa_widget_enabled', 'false') === 'true',
		'component' => robokassa_widget_get_choice_option('robokassa_widget_component', ['widget', 'badge'], 'widget'),
		'theme' => robokassa_widget_get_choice_option('robokassa_widget_theme', ['light', 'dark'], 'light'),
		'size' => robokassa_widget_get_choice_option('robokassa_widget_size', ['s', 'm'], 'm'),
		'show_logo' => robokassa_widget_get_boolean_option('robokassa_widget_show_logo', 'true'),
		'type' => robokassa_widget_get_choice_option('robokassa_widget_type', ['', 'bnpl', 'credit'], ''),
		'border_radius' => sanitize_text_field(get_option('robokassa_widget_border_radius', '')),
		'has_second_line' => robokassa_widget_get_boolean_option('robokassa_widget_has_second_line', 'false'),
		'description_position' => robokassa_widget_get_choice_option('robokassa_widget_description_position', ['left', 'right'], 'left'),
		'color_scheme' => robokassa_widget_get_choice_option('robokassa_widget_color_scheme', ['primary', 'secondary', 'accent', ''], ''),
	];

	if (get_option('robokassa_country_code', 'RU') === 'KZ') {
		$settings['enabled'] = false;
	}

	return $settings;
}

/**
 * Возвращает настройки статичного бейджа для каталога.
 *
 * @return array
 */
function robokassa_catalog_badge_get_settings() {
	$size = robokassa_widget_get_choice_option('robokassa_catalog_badge_size', ['xs', 's'], 'xs');
	$theme_default = robokassa_widget_get_choice_option('robokassa_catalog_badge_variant', ['light', 'dark'], 'light');
	$theme = robokassa_widget_get_choice_option('robokassa_catalog_badge_theme', ['light', 'dark'], $theme_default);
	$dimensions = robokassa_catalog_badge_get_dimensions($size);

	return [
		'size' => $size,
		'theme' => $theme,
		'file' => sprintf('%s-catalog-badge-%s.svg', $size, $theme),
		'width' => $dimensions['width'],
		'height' => $dimensions['height'],
	];
}

/**
 * Возвращает размеры статичного бейджа для каталога.
 *
 * @param string $size
 *
 * @return array
 */
function robokassa_catalog_badge_get_dimensions($size) {
	if ($size === 's') {
		return [
			'width' => 88,
			'height' => 48,
		];
	}

	return [
		'width' => 74,
		'height' => 42,
	];
}

/**
 * Проверяет наличие хотя бы одного метода оплаты частями для каталожного бейджа.
 *
 * @return bool
 */
function robokassa_catalog_badge_has_installment_method() {
	static $has_installment_method = null;

	if ($has_installment_method !== null) {
		return $has_installment_method;
	}

	$gateway_ids = [
		'robokassa_podeli',
		'robokassa_mokka',
		'robokassa_split',
	];

	if (!function_exists('robokassa_get_optional_method_config_by_gateway') || !function_exists('robokassa_is_optional_method_active')) {
		$has_installment_method = false;
		return $has_installment_method;
	}

	foreach ($gateway_ids as $gateway_id) {
		$config = robokassa_get_optional_method_config_by_gateway($gateway_id);
		if (!empty($config) && robokassa_is_optional_method_active($config)) {
			$has_installment_method = true;
			return $has_installment_method;
		}
	}

	$has_installment_method = false;
	return $has_installment_method;
}

/**
 * Возвращает значение опции с проверкой допустимых значений.
 *
 * @param string $option_name
 * @param array $allowed
 * @param string $default
 *
 * @return string
 */
function robokassa_widget_get_choice_option($option_name, array $allowed, $default) {
	$value = sanitize_text_field(get_option($option_name, $default));

	return in_array($value, $allowed, true) ? $value : $default;
}

/**
 * Возвращает текстовое представление булевой опции.
 *
 * @param string $option_name
 * @param string $default
 *
 * @return string
 */
function robokassa_widget_get_boolean_option($option_name, $default) {
	$value = get_option($option_name, $default);

	return $value === 'false' ? 'false' : 'true';
}

/**
 * Возвращает подпись для компонента.
 *
 * @param float $amount
 *
 * @return array|null
 */
function robokassa_widget_get_signature_data($amount) {
	$merchant = sanitize_text_field(get_option('robokassa_payment_MerchantLogin'));
	if ($merchant === '') {
		return null;
	}

	$test_mode = get_option('robokassa_payment_test_onoff') === 'true';
	$pass = $test_mode ? get_option('robokassa_payment_testshoppass1') : get_option('robokassa_payment_shoppass1');
	$pass = is_string($pass) ? $pass : '';
	if ($pass === '') {
		return null;
	}

	$out_sum = number_format((float)$amount, 2, '.', '');
	$signature = md5(sprintf('%s:%s::%s', $merchant, $out_sum, $pass));

	return [
		'merchantLogin' => $merchant,
		'outSum' => $out_sum,
		'signature' => $signature,
	];
}

/**
 * Формирует URL оформления заказа для компонента.
 *
 * @param int $product_id
 * @param array $settings
 *
 * @return string
 */
function robokassa_widget_prepare_checkout_url($product_id, array $settings) {
	$base_url = wc_get_checkout_url();
	$checkout_url = add_query_arg(
		[
			'add-to-cart' => $product_id,
		],
		$base_url
	);

	return esc_url_raw($checkout_url);
}

/**
 * Подготавливает атрибуты для вывода компонента.
 *
 * @param array $settings
 * @param array $signature
 * @param string $checkout_url
 *
 * @return array
 */
function robokassa_widget_prepare_attributes(array $settings, array $signature, $checkout_url) {
	$attributes = robokassa_widget_prepare_common_attributes($settings, $signature);

	if ($settings['component'] === 'widget') {
		return robokassa_widget_apply_widget_attributes($attributes, $settings, $checkout_url);
	}

	return robokassa_widget_apply_badge_attributes($attributes, $settings);
}

/**
 * Готовит базовые атрибуты компонента.
 *
 * @param array $settings
 * @param array $signature
 *
 * @return array
 */
function robokassa_widget_prepare_common_attributes(array $settings, array $signature) {
	$attributes = [
		'outSum' => $signature['outSum'],
		'merchantLogin' => $signature['merchantLogin'],
		'signature' => $signature['signature'],
		'theme' => $settings['theme'],
		'size' => $settings['size'],
		'mode' => 'checkout',
		'oncheckout' => 'robokassaWidgetHandleCheckout',
	];

	if ($settings['show_logo'] === 'false') {
		$attributes['showLogo'] = 'false';
	}

	if ($settings['type'] !== '') {
		$attributes['type'] = $settings['type'];
	}

	return $attributes;
}

/**
 * Дополняет атрибуты для основного виджета.
 *
 * @param array $attributes
 * @param array $settings
 * @param string $checkout_url
 *
 * @return array
 */
function robokassa_widget_apply_widget_attributes(array $attributes, array $settings, $checkout_url) {
	if ($settings['border_radius'] !== '') {
		$attributes['borderRadius'] = $settings['border_radius'];
	}
	if ($settings['has_second_line'] === 'true') {
		$attributes['hasSecondLine'] = 'true';
	}

	$attributes['descriptionPosition'] = $settings['description_position'];
	$attributes['checkoutUrl'] = $checkout_url;

	return $attributes;
}

/**
 * Дополняет атрибуты для бейджа.
 *
 * @param array $attributes
 * @param array $settings
 *
 * @return array
 */
function robokassa_widget_apply_badge_attributes(array $attributes, array $settings) {
	if ($settings['color_scheme'] !== '') {
		$attributes['colorScheme'] = $settings['color_scheme'];
	}

	return $attributes;
}

/**
 * Отображает компонент и подключает обработчики.
 *
 * @param string $component
 * @param array $attributes
 * @param int $product_id
 * @param string $checkout_url
 * @param array $settings
 * @param string $context
 *
 * @return void
 */
function robokassa_widget_output_component($component, array $attributes, $product_id, $checkout_url, array $settings, $context = 'single') {
	if (empty($attributes)) {
		return;
	}

	$tag = $component === 'badge' ? 'robokassa-badge' : 'robokassa-widget';
	$unique_suffix = function_exists('wp_unique_id') ? wp_unique_id() : uniqid('', false);
	$element_id = sprintf('%s-%d-%s', $tag, (int)$product_id, $unique_suffix);
	$context_class = sanitize_html_class($context);
	$attributes['id'] = $element_id;

	echo '<div class="robokassa-widget-wrapper robokassa-widget-wrapper--' . esc_attr($context_class) . '">';
	echo robokassa_widget_build_tag($tag, $attributes);
	echo '</div>';

	if ($component === 'badge') {
		robokassa_widget_print_badge_script($element_id, $checkout_url);
	}
}

/**
 * Собирает HTML тега компонента.
 *
 * @param string $tag
 * @param array $attributes
 *
 * @return string
 */
function robokassa_widget_build_tag($tag, array $attributes) {
	$parts = [];

	foreach ($attributes as $name => $value) {
		if ($value === '') {
			continue;
		}

		$parts[] = sprintf('%s="%s"', esc_attr($name), esc_attr($value));
	}

	$attributes_string = implode(' ', $parts);
	$attributes_string = trim($attributes_string);
	$attributes_string = $attributes_string === '' ? '' : ' ' . $attributes_string;

	$html = sprintf('<%1$s%2$s></%1$s>', esc_attr($tag), $attributes_string);

	return $html;
}

/**
 * Добавляет обработчик клика для бейджа по умолчанию.
 *
 * @param string $element_id
 * @param string $checkout_url
 *
 * @return void
 */
function robokassa_widget_print_badge_script($element_id, $checkout_url) {
	$url = esc_url_raw($checkout_url);
	$script = sprintf(
		"document.addEventListener('DOMContentLoaded',function(){var el=document.getElementById('%s');if(!el){return;}el.addEventListener('click',function(){window.location.href='%s';});});",
		esc_js($element_id),
		esc_js($url)
	);

	echo '<script>' . $script . '</script>';
}

/**
 * Подключает скрипт виджета, если он нужен.
 *
 * @return void
 */
function robokassa_widget_enqueue_assets() {
	if (!robokassa_widget_should_enqueue_assets()) {
		return;
	}

	$settings = robokassa_widget_get_settings();
	$is_product = function_exists('is_product') && is_product();
	$catalog_badge_enabled = get_option('robokassa_catalog_badge_enabled', 'false') === 'true';

	if (!$settings['enabled'] && (!$catalog_badge_enabled || $is_product)) {
		return;
	}

	$style_path = __DIR__ . '/assets/css/robokassa-widget.css';
	$style_url = plugin_dir_url(__FILE__) . 'assets/css/robokassa-widget.css';
	$style_version = file_exists($style_path) ? filemtime($style_path) : null;

	wp_enqueue_style(
		'robokassa-widget',
		$style_url,
		[],
		$style_version
	);

	if (!$is_product || !$settings['enabled']) {
		return;
	}

	wp_enqueue_script(
		'robokassa-badge-widget',
		'https://auth.robokassa.ru/merchant/bundle/robokassa-iframe-badge.js',
		[],
		null,
		true
	);

	$local_script_path = __DIR__ . '/assets/js/robokassa-widget-init.js';
	$local_script_url = plugin_dir_url(__FILE__) . 'assets/js/robokassa-widget-init.js';
	$local_script_version = file_exists($local_script_path) ? filemtime($local_script_path) : null;

	wp_enqueue_script(
		'robokassa-badge-widget-init',
		$local_script_url,
		['robokassa-badge-widget'],
		$local_script_version,
		true
	);
}

/**
 * Проверяет, нужна ли загрузка скриптов Robokassa на текущей витрине.
 *
 * @return bool
 */
function robokassa_widget_should_enqueue_assets() {
	if (function_exists('is_product') && is_product()) {
		return true;
	}

	if (function_exists('is_shop') && is_shop()) {
		return true;
	}

	if (function_exists('is_product_taxonomy') && is_product_taxonomy()) {
		return true;
	}

	return false;
}
