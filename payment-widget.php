<?php

add_action('woocommerce_single_product_summary', 'payment_product_widget', 25);
add_action('wp_enqueue_scripts', 'robokassa_widget_enqueue_assets');

/**
 * Выводит компонент Robokassa на странице товара.
 *
 * @return void
 */
function payment_product_widget() {
	global $product;

	if (!$product instanceof WC_Product) {
		return;
	}

	$settings = robokassa_widget_get_settings();
	if (!$settings['enabled']) {
		return;
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

	robokassa_widget_output_component($settings['component'], $attributes, $product->get_id(), $checkout_url, $settings);
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
 *
 * @return void
 */
function robokassa_widget_output_component($component, array $attributes, $product_id, $checkout_url, array $settings) {
	if (empty($attributes)) {
		return;
	}

	$tag = $component === 'badge' ? 'robokassa-badge' : 'robokassa-widget';
	$element_id = sprintf('%s-%d', $tag, (int)$product_id);
	$attributes['id'] = $element_id;

	echo '<div class="robokassa-widget-wrapper">';
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
	if (!is_product()) {
		return;
	}

	$settings = robokassa_widget_get_settings();
	if (!$settings['enabled']) {
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
