<?php
$widget_enabled      = get_option('robokassa_widget_enabled', 'false');
$component           = get_option('robokassa_widget_component', 'widget');
$widget_theme        = get_option('robokassa_widget_theme', 'light');
$widget_size         = get_option('robokassa_widget_size', 'm');
$show_logo           = get_option('robokassa_widget_show_logo', 'true');
$widget_type         = get_option('robokassa_widget_type', '');
if (!in_array($widget_type, ['', 'bnpl', 'credit'], true)) {
	$widget_type = '';
}
$border_radius       = get_option('robokassa_widget_border_radius', '');
$has_second_line     = get_option('robokassa_widget_has_second_line', 'false');
$description_position = get_option('robokassa_widget_description_position', 'left');
$color_scheme        = get_option('robokassa_widget_color_scheme', 'primary');
?>

<div class="robokassa-widget-settings">
	<p class="mid_title_rb robokassa-card__title">Настройка Robokassa Badge & Widget</p>

	<table class="robokassa-form-table form-table">
		<tr valign="top">
			<th scope="row">Включить виджет или бейдж</th>
			<td>
				<label><input type="radio" name="robokassa_widget_enabled" value="true" <?php checked($widget_enabled, 'true'); ?>> Включено</label>
				<label style="margin-left: 15px;"><input type="radio" name="robokassa_widget_enabled" value="false" <?php checked($widget_enabled, 'false'); ?>> Отключено</label>
				<br/>
				<span class="text-description">После активации на странице товара будет отображаться выбранный компонент Robokassa.</span>
			</td>
		</tr>

		<tr valign="top">
			<th scope="row">Компонент по умолчанию</th>
			<td>
				<select id="robokassa_widget_component" name="robokassa_widget_component">
					<option value="widget" <?php selected($component, 'widget'); ?>>Виджет</option>
					<option value="badge" <?php selected($component, 'badge'); ?>>Бейдж</option>
				</select>
				<br/>
				<span class="text-description">Выберите компонент, который будет отображаться на странице товара.</span>
			</td>
		</tr>

		<tr valign="top">
			<th scope="row">Цветовая тема</th>
			<td>
				<select name="robokassa_widget_theme">
					<option value="light" <?php selected($widget_theme, 'light'); ?>>Светлая тема</option>
					<option value="dark" <?php selected($widget_theme, 'dark'); ?>>Тёмная тема</option>
				</select>
			</td>
		</tr>
	</table>
</div>

<div class="robokassa-widget-advanced-settings">
	<p class="mid_title_rb robokassa-card__title">Продвинутые настройки компонентов</p>

	<table class="robokassa-form-table form-table">
		<tr class="robokassa-widget-option" valign="top">
			<th scope="row">Размер компонента</th>
			<td>
				<select name="robokassa_widget_size">
					<option value="s" <?php selected($widget_size, 's'); ?>>s</option>
					<option value="m" <?php selected($widget_size, 'm'); ?>>m</option>
				</select>
				<br/>
				<span class="text-description">Задаёт значение атрибута <code>size</code>. Доступны варианты «s» и «m».</span>
			</td>
		</tr>

		<tr class="robokassa-widget-option" valign="top">
			<th scope="row">Показывать логотип Robokassa</th>
			<td>
				<label><input type="radio" name="robokassa_widget_show_logo" value="true" <?php checked($show_logo, 'true'); ?>> Да</label>
				<label style="margin-left: 15px;"><input type="radio" name="robokassa_widget_show_logo" value="false" <?php checked($show_logo, 'false'); ?>> Нет</label>
				<br/>
				<span class="text-description">Управляет атрибутом <code>showLogo</code>. Значение «false» скрывает логотип.</span>
			</td>
		</tr>

		<tr class="robokassa-widget-option robokassa-widget-option--widget" valign="top">
			<th scope="row">Тип предложения</th>
			<td>
				<select name="robokassa_widget_type">
					<option value="" <?php selected($widget_type, ''); ?>>Отображать оба</option>
					<option value="bnpl" <?php selected($widget_type, 'bnpl'); ?>>bnpl</option>
					<option value="credit" <?php selected($widget_type, 'credit'); ?>>credit</option>
				</select>
				<br/>
				<span class="text-description">Значение атрибута <code>type</code> для компонента «Виджет». По умолчанию атрибут не передаётся, что включает оба типа предложений.</span>
			</td>
		</tr>

		<tr class="robokassa-widget-option robokassa-widget-option--widget robokassa-widget-option--second-line" valign="top">
			<th scope="row">Скругление углов</th>
			<td>
				<input type="text" name="robokassa_widget_border_radius" value="<?php echo esc_attr($border_radius); ?>" placeholder="Например: 12px" class="regular-text" />
				<br/>
				<span class="text-description">Атрибут <code>borderRadius</code> для компонента <code>Виджет</code>.</span>
			</td>
		</tr>

		<tr class="robokassa-widget-option robokassa-widget-option--widget" valign="top">
			<th scope="row">Вторая строка описания</th>
			<td>
				<label><input type="radio" name="robokassa_widget_has_second_line" value="true" <?php checked($has_second_line, 'true'); ?>> Да</label>
				<label style="margin-left: 15px;"><input type="radio" name="robokassa_widget_has_second_line" value="false" <?php checked($has_second_line, 'false'); ?>> Нет</label>
				<br/>
				<span class="text-description">Соответствует атрибуту <code>hasSecondLine</code> (Только для widget & size: m).</span>
			</td>
		</tr>

		<tr class="robokassa-widget-option robokassa-widget-option--widget" valign="top">
			<th scope="row">Позиция описания</th>
			<td>
				<select name="robokassa_widget_description_position">
					<option value="left" <?php selected($description_position, 'left'); ?>>Слева</option>
					<option value="right" <?php selected($description_position, 'right'); ?>>Справа</option>
				</select>
				<br/>
				<span class="text-description">Значение атрибута <code>descriptionPosition</code> только для виджета.</span>
			</td>
		</tr>

		<tr class="robokassa-widget-option robokassa-widget-option--badge" valign="top">
			<th scope="row">Цветовая схема бейджа</th>
			<td>
				<select name="robokassa_widget_color_scheme">
					<option value="primary" <?php selected($color_scheme, 'primary'); ?>>primary</option>
					<option value="secondary" <?php selected($color_scheme, 'secondary'); ?>>secondary</option>
					<option value="accent" <?php selected($color_scheme, 'accent'); ?>>accent</option>
					<option value="" <?php selected($color_scheme, ''); ?>>Не указывать</option>
				</select>
				<br/>
				<span class="text-description">Атрибут <code>colorScheme</code> доступен только для компонента <code>Бейдж</code>.</span>
			</td>
		</tr>
	</table>
</div>
