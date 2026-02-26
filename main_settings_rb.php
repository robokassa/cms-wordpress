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

\wp_enqueue_script(
	'robokassa_payment_admin_main_payment',
	\plugin_dir_url(__FILE__) . 'assets/js/admin-payment.js'
);

\wp_enqueue_style(
	'robokassa_payment_admin_style_menu',
	\plugin_dir_url(__FILE__) . 'assets/css/admin-style.css'
);

$country_code = get_option('robokassa_country_code', 'RU');
$woocommercePricesIncludeTax = false;

if (function_exists('wc_prices_include_tax')) {
	$woocommercePricesIncludeTax = wc_prices_include_tax();
} else {
	$woocommercePricesIncludeTax = get_option('woocommerce_prices_include_tax') === 'yes';
}
?>

<div class="robokassa-admin-wrapper">
	<div class="robokassa-admin-container">
		<div class="content_holder">
			<?php if ($woocommercePricesIncludeTax): ?>
				<div class="notice notice-error robokassa-notice-top">
					<p>Включено добавление налога в цену WooCommerce. Указывайте цены без налога и настраивайте ставки в
						плагине Robokassa.</p>
				</div>
			<?php endif; ?>
			<?php

			if (isset($_REQUEST['settings-updated'])) {
				include 'labelsGenerator.php';

				$has_methods = get_transient('robokassa_payment_methods_available');
				delete_transient('robokassa_payment_methods_available');

				if ($country_code === 'RU' && $has_methods) {
					wp_redirect('admin.php?page=robokassa_payment_credit');
					exit;
				}
			}

			$formProperties = [
				'robokassa_payment_wc_robokassa_enabled',
				'robokassa_payment_MerchantLogin',
				'robokassa_payment_shoppass1',
				'robokassa_payment_shoppass2',
				'robokassa_payment_test_onoff',
				'robokassa_payment_testshoppass1',
				'robokassa_payment_testshoppass2',
				'robokassa_payment_sno',
				'robokassa_payment_tax',
				'robokassa_payment_tax_source',
				'robokassa_payment_agent_fields_enabled',
				'robokassa_payment_who_commission',
				'robokassa_payment_size_commission',
				'robokassa_payment_paytype',
				'robokassa_payment_SuccessURL',
				'robokassa_payment_FailURL',
				'robokassa_payment_paymentMethod',
				'robokassa_payment_paymentObject',
				'robokassa_payment_second_check_paymentObject',
				'robokassa_payment_paymentObject_shipping',
				'robokassa_patyment_markup',
				'robokassa_culture',
				'robokassa_iframe',
				'robokassa_country_code',
				'robokassa_out_currency',
				'robokassa_agreement_text',
				'robokassa_agreement_pd_link',
				'robokassa_agreement_oferta_link',
				'robokassa_payment_hold_onoff',
				'robokassa_payment_order_status_after_payment',
				'robokassa_payment_order_status_for_second_check',
				'robokassa_payment_method_credit_enabled',
				'robokassa_payment_method_podeli_enabled',
				'robokassa_payment_method_mokka_enabled',
				'robokassa_payment_method_split_enabled',
			];

			require_once __DIR__ . '/labelsClasses.php';

			foreach ((array)robokassa_payment_add_WC_WP_robokassa_class() as $class):
				$method = new $class;
				$formProperties[] = 'RobokassaOrderPageTitle_' . $method->id;
				$formProperties[] = 'RobokassaOrderPageDescription_' . $method->id;
			endforeach;
			?>

			<div class="main-settings">
				<div class="robokassa-card robokassa-card--compact">
					<h2 class="robokassa-card__title">Помощь и инструкция по установке</h2>
					<ol class="robokassa-info-list">
						<li>Введите данные API в разделе «Основные настройки».</li>
						<li>В личном кабинете Robokassa укажите следующие URL-адреса для уведомлений:</li>
					</ol>
					<table class="robokassa-form-table robokassa-info-table">
						<tr>
							<th scope="row">ResultURL</th>
							<td>
								<code id="ResultURL"
									  class="robokassa-code"><?php echo Util::siteUrl('/?robokassa=result'); ?></code>
							</td>
							<td>
								<button class="robokassa-button-primary btn btn-default btn-clipboard btn-main"
										data-clipboard-target="#ResultURL" onclick="event.preventDefault();">
									Скопировать
								</button>
							</td>
						</tr>
						<tr>
							<th scope="row">SuccessURL</th>
							<td>
								<code id="SuccessURL"
									  class="robokassa-code"><?php echo Util::siteUrl('/?robokassa=success'); ?></code>
							</td>
							<td>
								<button class="robokassa-button-primary btn btn-default btn-clipboard btn-main"
										data-clipboard-target="#SuccessURL" onclick="event.preventDefault();">
									Скопировать
								</button>
							</td>
						</tr>
						<tr>
							<th scope="row">FailURL</th>
							<td>
								<code id="FailURL"
									  class="robokassa-code"><?php echo Util::siteUrl('/?robokassa=fail'); ?></code>
							</td>
							<td>
								<button class="robokassa-button-primary btn btn-default btn-clipboard btn-main"
										data-clipboard-target="#FailURL" onclick="event.preventDefault();">
									Скопировать
								</button>
							</td>
						</tr>
					</table>
					<p class="robokassa-text-note">Метод отсылки данных — <code class="robokassa-code">POST</code></p>
					<p class="robokassa-text-note">Алгоритм расчёта хеша — <code class="robokassa-code">MD5</code></p>
					<p class="robokassa-text-note">После этого заполните логин и пароли магазина в полях ниже.</p>
				</div>

				<div class="robokassa-card">
					<h2 class="robokassa-card__title">Основные настройки</h2>

					<form action="options.php" method="POST">

						<?php wp_nonce_field('update-options'); ?>

						<table class="robokassa-form-table form-table">
							<tr valign="top">
								<th scope="row">Оплата через Робокассу</th>
								<td>
									<input type="radio" id="enabled_on" name="robokassa_payment_wc_robokassa_enabled"
										   value="yes"
										<?php echo get_option('robokassa_payment_wc_robokassa_enabled') == 'yes' ? 'checked="checked"' : ''; ?>>
									<label for="enabled_on">Включить</label>

									<input type="radio" id="enabled_off" name="robokassa_payment_wc_robokassa_enabled"
										   value="no"
										<?php echo get_option('robokassa_payment_wc_robokassa_enabled') == 'no' ? 'checked="checked"' : ''; ?>>
									<label for="enabled_off">Отключить</label>
								</td>
							</tr>

							<tr valign="top">
								<th scope="row">Заголовок на странице оформления заказа</th>
								<td>
									<input type="text" name="RobokassaOrderPageTitle_robokassa"
										   value="<?php echo get_option('RobokassaOrderPageTitle_robokassa'); ?>"/>
								</td>
							</tr>
							<tr valign="top">
								<th scope="row">Описание на странице оформления заказа</th>
								<td>
									<input type="text" name="RobokassaOrderPageDescription_robokassa"
										   value="<?php echo get_option('RobokassaOrderPageDescription_robokassa'); ?>"/>
								</td>
							</tr>

							<tr valign="top">
								<th scope="row">Страна магазина</th>
								<td>
									<select id="robokassa_country_code" name="robokassa_country_code">
										<option value="RU" <?php echo((get_option('robokassa_country_code') == 'RU') ? ' selected' : ''); ?>>
											Россия
										</option>
										<option value="KZ" <?php echo((get_option('robokassa_country_code') == 'KZ') ? ' selected' : ''); ?>>
											Казахстан
										</option>
									</select>
								</td>
							</tr>

							<!--<tr valign="top">
				<th scope="row">Валюта заказа</th>
				<td>
					<select id="robokassa_out_currency" name="robokassa_out_currency">
						<?php
							/*
														$woocommerce_currency = get_option('woocommerce_currency');

														$currencies = array(
															'RUR' => 'Рубли',
															'USD' => 'Доллары',
															'EUR' => 'Евро',
															'KZT' => 'Тенге',
															$woocommerce_currency => 'Валюта по умолчанию из настроек WC',
														);

														$valid_wc_currencies = array('RUR', 'USD', 'EUR', 'KZT');

														if ($woocommerce_currency === $woocommerce_currency) {
															$currencies = array_intersect_key($currencies, array_flip($valid_wc_currencies));
														}

														$currencies = ['' => 'Не передавать значение валюты'] + $currencies;

														foreach ($currencies as $currency_code => $currency_name) {
															$selected = (get_option('robokassa_out_currency') === $currency_code) ? 'selected' : '';
															echo '<option value="' . esc_attr($currency_code) . '" ' . $selected . '>';
															echo esc_html($currency_name);
															echo '</option>';
														}
														*/ ?>
					</select>
				</td>
			</tr>-->

							<tr valign="top">
								<th scope="row">Идентификатор магазина</th>
								<td><input type="text" name="robokassa_payment_MerchantLogin" value="<?php
									echo get_option('robokassa_payment_MerchantLogin'); ?>"/></td>
							</tr>

							<tr valign="top">
								<th scope="row">Пароль магазина #1</th>
								<td><input type="password" name="robokassa_payment_shoppass1" value="<?php
									echo get_option('robokassa_payment_shoppass1'); ?>"/></td>
							</tr>

							<tr valign="top">
								<th scope="row">Пароль магазина #2</th>
								<td><input type="password" name="robokassa_payment_shoppass2" value="<?php
									echo get_option('robokassa_payment_shoppass2'); ?>"/></td>
							</tr>

							<tr valign="top">
								<th scope="row">Язык интерфейса робокассы</th>
								<td>
									<select name="robokassa_culture">
										<?php foreach (\Robokassa\Payment\Helper::$culture as $culture): ?>
											<option<?php if (get_option('robokassa_culture') == $culture['code']): ?> selected="selected"<?php endif; ?>
													value="<?= $culture['code']; ?>"><?= $culture['title']; ?></option>
										<?php endforeach; ?>
									</select>
								</td>
							</tr>
							<tr valign="top">
								<th scope="row">Включить iframe</th>
								<td>
									<select name="robokassa_iframe">
										<?php if (get_option('robokassa_iframe') == 1) { ?>
											<option selected="selected" value="1">Включено</option>
											<option value="0">Отключено</option>
										<?php } else { ?>
											<option value="1">Включено</option>
											<option selected="selected" value="0">Отключено</option>
										<?php } ?>
									</select><br/>
									<span class="text-description">При включённом iframe, способов оплаты меньше, чем в обычной платежной странице - только карты, Apple и Samsung pay, Qiwi. incurlabel работает, но ограничено.<span>
								</td>
							</tr>
						</table>

						<?php $optional_methods = ($country_code === 'KZ') ? [] : robokassa_get_optional_payment_methods_config(); ?>

						<?php if (!empty($optional_methods)) : ?>
							<div class="robokassa-optional-methods">
								<h2 class="robokassa-card__title">Дополнительные способы оплаты</h2>
								<p class="robokassa-text-note">Выберите партнёрские решения Robokassa, которые нужно
									показывать клиентам при оформлении заказа.</p>

								<div class="spoiler_body">
									<table class="robokassa-form-table form-table">
										<?php foreach ($optional_methods as $config) :
											$option_name = $config['option'];
											$field_id = $option_name . '_field';
											$is_available = robokassa_is_optional_method_available($config);
											$is_enabled = robokassa_is_optional_method_enabled($config);
											$hidden_value = $is_available ? 'no' : ($is_enabled ? 'yes' : 'no');
											?>
											<tr valign="top">
												<th scope="row">
													<?php echo esc_html($config['title']); ?>
													<?php if (!$is_available) : ?>
														<br/>
														<span class="text-description">Способ оплаты недоступен для магазина.</span>
													<?php endif; ?>
												</th>
												<td>
													<input type="hidden" name="<?php echo esc_attr($option_name); ?>"
														   value="<?php echo esc_attr($hidden_value); ?>"/>

													<?php if ($is_available) : ?>
														<label for="<?php echo esc_attr($field_id); ?>">
															<input type="checkbox"
																   id="<?php echo esc_attr($field_id); ?>"
																   name="<?php echo esc_attr($option_name); ?>"
																   value="yes" <?php checked($is_enabled); ?>/>
															<span>Отображать способ оплаты</span>
														</label>
													<?php else : ?>
														<label>
															<input type="checkbox" <?php checked($is_enabled); ?>
																   disabled="disabled"/>
															<span>Отображать способ оплаты</span>
														</label>
													<?php endif; ?>
												</td>
											</tr>
										<?php endforeach; ?>
									</table>
								</div>
							</div>
						<?php endif; ?>

						<? if (function_exists('wcs_order_contains_subscription')) { ?>
							<p class="mid_title_rb">Настройки для Woocommerce Subscriptions</p>

							<div class="spoiler_body">
								<table class="robokassa-form-table form-table">
									<tr valign="top">
										<th scope="row">Текст согласия с правилами на списания по подписке</th>
										<td>
											<input type="text" name="robokassa_agreement_text"
												   value="<?php echo htmlspecialchars(get_option('robokassa_agreement_text') ?: 'Я даю согласие на регулярные списания, на <a href="%s">обработку персональных данных</a> и принимаю условия <a href="%s">публичной оферты</a>.'); ?>"/>
										</td>
									</tr>
									<tr valign="top">
										<th scope="row">Ссылка на согласие на обработку ПД</th>
										<td>
											<input type="text" name="robokassa_agreement_pd_link"
												   value="<?php echo htmlspecialchars(get_option('robokassa_agreement_pd_link')); ?>"/>
										</td>
									</tr>
									<tr valign="top">
										<th scope="row">Ссылка на оферту</th>
										<td>
											<input type="text" name="robokassa_agreement_oferta_link"
												   value="<?php echo htmlspecialchars(get_option('robokassa_agreement_oferta_link')); ?>"/>
										</td>
									</tr>
								</table>
							</div>
						<? } ?>

						<h2 class="robokassa-card__title">Настройки тестового соединения</h2>

						<div class="spoiler_body">
							<table class="robokassa-form-table form-table">
								<tr valign="top">
									<th scope="row">Тестовый режим</th>
									<td>
										<input type="radio" id="test_on" name="robokassa_payment_test_onoff"
											   value="true"
											<?php echo get_option('robokassa_payment_test_onoff') == 'true' ? 'checked="checked"' : ''; ?>>
										<label for="test_on">Включить</label>

										<input type="radio" id="test_off" name="robokassa_payment_test_onoff"
											   value="false"
											<?php echo get_option('robokassa_payment_test_onoff') == 'false' ? 'checked="checked"' : ''; ?>>
										<label for="test_off">Отключить</label>
									</td>
								</tr>

								<tr valign="top">
									<th scope="row">Тестовый пароль магазина #1</th>
									<td><input type="password" name="robokassa_payment_testshoppass1"
											   value="<?php echo get_option('robokassa_payment_testshoppass1'); ?>"/>
									</td>
								</tr>

								<tr valign="top">
									<th scope="row">Тестовый пароль магазина #2</th>
									<td><input type="password" name="robokassa_payment_testshoppass2"
											   value="<?php echo get_option('robokassa_payment_testshoppass2'); ?>"/>
									</td>
								</tr>
							</table>
						</div>

						<h2 class="robokassa-card__title">Фискализация</h2>

						<div class="spoiler_body">
							<table class="robokassa-form-table form-table">
								<tr valign="top" id="sno">
									<th scope="row">Система налогообложения</th>
									<td>
										<select id="sno_select" name="robokassa_payment_sno" onchange="spoleer();">
											<option value="fckoff" <?php echo((get_option('robokassa_payment_sno') == 'fckoff') ? ' selected' : ''); ?>>
												Не передавать
											</option>
											<option value="osn" <?php echo((get_option('robokassa_payment_sno') == 'osn') ? ' selected' : ''); ?>>
												Общая СН
											</option>
											<option value="usn_income" <?php echo((get_option('robokassa_payment_sno') == 'usn_income') ? ' selected'
												: ''); ?>>Упрощенная СН (доходы)
											</option>
											<option value="usn_income_outcome" <?php echo((get_option('robokassa_payment_sno') == 'usn_income_outcome')
												? ' selected' : ''); ?>>Упрощенная СН (доходы минус расходы)
											</option>
											<option value="envd" <?php echo((get_option('robokassa_payment_sno') == 'envd') ? ' selected' : ''); ?>>
												Единый налог на вмененный доход
											</option>
											<option value="esn" <?php echo((get_option('robokassa_payment_sno') == 'esn') ? ' selected' : ''); ?>>
												Единый сельскохозяйственный налог
											</option>
											<option value="patent" <?php echo((get_option('robokassa_payment_sno') == 'patent') ? ' selected' : ''); ?>>
												Патентная СН
											</option>
										</select>
									</td>
								</tr>

								<tr valign="top" id="payment_method">
									<th scope="row">Признак способа расчёта</th>
									<td>
										<select id="payment_method_select" name="robokassa_payment_paymentMethod"
												onchange="spoleer();">
											<option value="">Не выбрано</option>
											<?php foreach (\Robokassa\Payment\Helper::$paymentMethods as $paymentMethod): ?>
												<option <?php if (\get_option('robokassa_payment_paymentMethod') === $paymentMethod['code']): ?> selected="selected"<?php endif; ?>
														value="<?php echo $paymentMethod['code']; ?>"><?php echo $paymentMethod['title']; ?></option>
											<?php endforeach; ?>
										</select>
									</td>
								</tr>
								<tr valign="top" id="payment_object">
									<th scope="row">Признак предмета расчёта для товаров/услуг</th>
									<td>
										<select id="payment_object_select" name="robokassa_payment_paymentObject"
												onchange="spoleer();">
											<option value="">Не выбрано</option>
											<?php foreach (\Robokassa\Payment\Helper::$paymentObjects as $paymentObject): ?>
												<option <?php if (\get_option('robokassa_payment_paymentObject') === $paymentObject['code']): ?>
														selected="selected"
														<?php endif; ?>value="<?php echo $paymentObject['code']; ?>"><?php echo $paymentObject['title']; ?></option>
											<?php endforeach; ?>
										</select>
									</td>
								</tr>
								<tr valign="top" id="payment_object_second_receipt">
									<th scope="row">Признак предмета расчёта для товаров/услуг (второй чек)</th>
									<td>
										<select id="payment_object_second_receipt_select"
												name="robokassa_payment_second_check_paymentObject"
												onchange="spoleer();">
											<option value="">Не выбрано</option>
											<?php foreach (\Robokassa\Payment\Helper::$paymentObjects as $paymentObject): ?>
												<option <?php if (\get_option('robokassa_payment_second_check_paymentObject') === $paymentObject['code']): ?>
														selected="selected"
														<?php endif; ?>value="<?php echo $paymentObject['code']; ?>"><?php echo $paymentObject['title']; ?></option>
											<?php endforeach; ?>
										</select>
										<br/>
										<span class="text-description">Если параметр не выбран, используется значение из поля «Признак предмета расчёта для товаров/услуг».</span>
									</td>
								</tr>

								<tr valign="top" id="payment_object_shipping">
									<th scope="row">Признак предмета расчёта для доставки</th>
									<td>
										<select id="payment_object_shipping_select"
												name="robokassa_payment_paymentObject_shipping"
												onchange="spoleer();">
											<option value="">Не выбрано</option>
											<?php foreach (\Robokassa\Payment\Helper::$paymentObjects as $paymentObject): ?>
												<option <?php if (\get_option('robokassa_payment_paymentObject_shipping') === $paymentObject['code']): ?>
														selected="selected"
														<?php endif; ?>value="<?php echo $paymentObject['code']; ?>"><?php echo $paymentObject['title']; ?></option>
											<?php endforeach; ?>
										</select>
									</td>
								</tr>
								<?php
								$woocommerceTaxRates = array();
								$woocommerceTaxesEnabled = function_exists('wc_tax_enabled') && wc_tax_enabled();

								if ($woocommerceTaxesEnabled && class_exists('WC_Tax')) {
									$taxClasses = array_merge(array(''), WC_Tax::get_tax_class_slugs());

									foreach ($taxClasses as $taxClass) {
										$woocommerceTaxRates = WC_Tax::get_rates($taxClass);

										if (!empty($woocommerceTaxRates)) {
											break;
										}
									}
								}

								if ($woocommerceTaxesEnabled && !empty($woocommerceTaxRates)): ?>
									<tr>
										<td colspan="2">
											<div class="notice notice-error robokassa-tax-warning">
												<p>
													В WooCommerce включены налоговые ставки.
													В России встроенные налоги WooCommerce не используются — при их
													включении фискализация по 54-ФЗ будет некорректной.
													Отключите налоги в разделе <strong>WooCommerce → Настройки → Общие →
														«Включить налоги и расчёт ставок»</strong>.
													Используйте ставки НДС только в настройках Robokassa и не применяйте
													налоговые классы WooCommerce.
												</p>
											</div>
										</td>
									</tr>
								<?php endif; ?>

								<tr valign="top" id="tax_source">
									<th scope="row">Источник налоговой ставки</th>
									<td>
										<?php $tax_source = get_option('robokassa_payment_tax_source', 'global'); ?>
										<select id="tax_source_select" name="robokassa_payment_tax_source"
												onchange="spoleer();">
											<option value="global" <?php selected($tax_source, 'global'); ?>>
												Использовать ставку из настроек плагина
											</option>
											<option value="product" <?php selected($tax_source, 'product'); ?>>
												Использовать ставку из карточки товара (при наличии)
											</option>
										</select>
										<br/>
										<span class="text-description">При выборе варианта с карточкой товара ставка из настроек плагина будет использоваться как значение по умолчанию.</span>
									</td>
								</tr>
								<tr valign="top" id="tax">
									<th scope="row">Налоговая ставка</th>
									<td>
										<select id="tax_select" name="robokassa_payment_tax" onchange="spoleer();">
											<option value="none" <?php echo((get_option('robokassa_payment_tax') == 'none') ? ' selected' : ''); ?>>
												Не передавать
											</option>
											<option value="none" <?php echo((get_option('robokassa_payment_tax') == 'none') ? ' selected' : ''); ?>>
												Без НДС
											</option>
											<option value="vat0" <?php echo((get_option('robokassa_payment_tax') == 'vat0') ? ' selected' : ''); ?>>
												НДС по ставке 0%
											</option>
											<option value="vat10" <?php echo((get_option('robokassa_payment_tax') == 'vat10') ? ' selected' : ''); ?>>
												НДС чека по ставке 10%
											</option>
											<option value="vat22" <?php echo((get_option('robokassa_payment_tax') == 'vat22') ? ' selected' : ''); ?>>
												НДС чека по ставке 22%
											</option>
											<option value="vat20" <?php echo((get_option('robokassa_payment_tax') == 'vat20') ? ' selected' : ''); ?>>
												НДС чека по ставке 20%
											</option>
											<option value="vat110" <?php echo((get_option('robokassa_payment_tax') == 'vat110') ? ' selected' : ''); ?>>
												НДС чека по расчетной ставке 10/110
											</option>
											<option value="vat118" <?php echo((get_option('robokassa_payment_tax') == 'vat120') ? ' selected' : ''); ?>>
												НДС чека по расчетной ставке 20/120
											</option>
											<option value="vat122" <?php echo((get_option('robokassa_payment_tax') == 'vat122') ? ' selected' : ''); ?>>
												НДС чека по расчетной ставке 22/122
											</option>
											<option value="vat5" <?php echo((get_option('robokassa_payment_tax') == 'vat5') ? ' selected' : ''); ?>>
												НДС по ставке 5%
											</option>
											<option value="vat7" <?php echo((get_option('robokassa_payment_tax') == 'vat7') ? ' selected' : ''); ?>>
												НДС по ставке 7%
											</option>
											<option value="vat105" <?php echo((get_option('robokassa_payment_tax') == 'vat105') ? ' selected' : ''); ?>>
												НДС чека по расчетной ставке 5/105
											</option>
											<option value="vat107" <?php echo((get_option('robokassa_payment_tax') == 'vat107') ? ' selected' : ''); ?>>
												НДС чека по расчетной ставке 7/107
											</option>
											<option value="vat8" <?php echo((get_option('robokassa_payment_tax') == 'vat8') ? ' selected' : ''); ?>>
												НДС чека по ставке 8% (Казахстан)
											</option>
											<option value="vat12" <?php echo((get_option('robokassa_payment_tax') == 'vat12') ? ' selected' : ''); ?>>
												НДС чека по ставке 12% (Казахстан)
											</option>
										</select>
									</td>
								</tr>
							</table>

							<h2 class="robokassa-card__title">Настройка статусов заказа</h2>

							<div class="spoiler_body">
								<table class="robokassa-form-table form-table">
									<tr valign="top">
										<th scope="row">Статус заказа после оплаты</th>
										<td>
											<select name="robokassa_payment_order_status_after_payment">
												<?php
												$selected = get_option('robokassa_payment_order_status_after_payment');
												if (empty($selected)) {
													$selected = 'wc-processing';
												}
												$statuses = wc_get_order_statuses();
												foreach ($statuses as $status => $label) {
													echo '<option value="' . esc_attr($status) . '" ' . selected($selected, $status, false) . '>' . esc_html($label) . '</option>';
												}
												?>
											</select>
											<br/>
											<span class="text-description">Этот статус будет присвоен заказу после успешной оплаты через Робокассу. Применяется только для обычных платежей (не отложенных).</span>
										</td>
									</tr>

									<tr valign="top" id="second_receipt_status_row">
										<th scope="row">Статус для автоматического выбивания второго чека</th>
										<td>
											<select name="robokassa_payment_order_status_for_second_check">
												<?php
												$selected = get_option('robokassa_payment_order_status_for_second_check');
												$statuses = wc_get_order_statuses();
												foreach ($statuses as $status => $label) {
													echo '<option value="' . esc_attr($status) . '" ' . selected($selected, $status, false) . '>' . esc_html($label) . '</option>';
												}
												?>
											</select><br/>
											<span class="text-description">Выберите статус, при котором будет автоматически выбиваться второй чек (если этот статус применен к заказу).</span>
										</td>
									</tr>
								</table>
							</div>

							<h2 class="robokassa-card__title">Прочие настройки</h2>

							<div class="spoiler_body">
								<table class="robokassa-form-table form-table">
									<tr valign="top" id="robokassa_agent_settings" onchange="spoleer();">
										<th scope="row">Агентские товары</th>
										<td>
											<?php $agent_enabled = get_option('robokassa_payment_agent_fields_enabled', 'no'); ?>
											<input type="hidden" name="robokassa_payment_agent_fields_enabled"
												   value="no"/>
											<label for="robokassa_agent_fields_enabled">
												<input type="checkbox" id="robokassa_agent_fields_enabled"
													   name="robokassa_payment_agent_fields_enabled"
													   value="yes" <?php checked($agent_enabled, 'yes'); ?> />
												Продаёте ли вы агентский товар?
											</label>
											<p class="description"><?php printf(wp_kses(__('Для передачи данных в чек используйте арендованную <a href="%s" target="_blank" rel="noopener noreferrer">Робокасса Онлайн</a>. С решением «Робочеки» агентский признак не будет передан в чек.', 'your-text-domain'), array('a' => array('href' => array(), 'target' => array(), 'rel' => array()))), 'https://robokassa.com/online-check/robokassa-online/'); ?></p>
											<p class="description"><?php esc_html_e('После включения в карточке товара появится блок «Агентский товар Robokassa». Заполните тип агента, наименование, ИНН и телефоны, чтобы данные попали в чек.', 'your-text-domain'); ?></p>
										</td>
									</tr>

									<tr valign="top" id="robokassa_hold_settings">
										<th scope="row">Отложенные платежи</th>
										<td>
											<select name="robokassa_payment_hold_onoff">
												<?php if (get_option('robokassa_payment_hold_onoff') == 1) { ?>
													<option selected="selected" value="1">Включено</option>
													<option value="0">Отключено</option>
												<?php } else { ?>
													<option value="1">Включено</option>
													<option selected="selected" value="0">Отключено</option>
												<?php } ?>
											</select><br/>
											<span class="text-description">Данная <a
														href="https://docs.robokassa.ru/holding/">услуга</a> доступна только по предварительному согласованию.<span><br/>
											<span class="text-description">Функционал доступен только при использовании банковских карт.<span><br/>
											<span class="text-description"><a
														href="https://docs.robokassa.ru/media/guides/hold_woocommerce.pdf">Инструкция по настройке</a></span>
										</td>
									</tr>

									<tr valign="top">
										<th scope="row">Страница успеха платежа</th>
										<td>
											<select id="SuccessURL" name="robokassa_payment_SuccessURL">
												<option value="wc_success" <?php echo((get_option('robokassa_payment_SuccessURL') == 'wc_success')
													? ' selected' : ''); ?>>Страница "Заказ принят" от WooCommerce
												</option>
												<option value="wc_checkout" <?php echo((get_option('robokassa_payment_SuccessURL') == 'wc_checkout')
													? ' selected' : ''); ?>>Страница оформления заказа от WooCommerce
												</option>
												<?php
												if (get_pages()) {
													foreach (get_pages() as $page) {
														$selected = ($page->ID == get_option('robokassa_payment_SuccessURL')) ? ' selected' : '';
														echo '<option value="' . $page->ID . '"' . $selected . '>' . $page->post_title . '</option>';
													}
												}
												?>
											</select><br/>
											<span class="text-description">Эту страницу увидит покупатель, когда оплатит заказ<span>
										</td>
									</tr>

									<tr valign="top">
										<th scope="row">Страница отказа</th>
										<td>
											<select id="FailURL" name="robokassa_payment_FailURL">
												<option value="wc_checkout" <?php echo((get_option('robokassa_payment_FailURL') == 'wc_checkout')
													? ' selected' : ''); ?>>Страница оформления заказа от WooCommerce
												</option>
												<option value="wc_payment" <?php echo((get_option('robokassa_payment_FailURL') == 'wc_payment') ? ' selected'
													: ''); ?>>Страница оплаты заказа от WooCommerce
												</option>
												<?php
												if ($pages = get_pages()) {
													foreach ($pages as $page) {
														$selected = ($page->ID == get_option('robokassa_payment_FailURL')) ? ' selected' : '';
														echo '<option value="' . $page->ID . '"' . $selected . '>' . $page->post_title . '</option>';
													}
												}
												?>
											</select><br/>
											<span class="text-description">Эту страницу увидит покупатель, если что-то пойдет не так: например, если ему не хватит денег на карте<span>
										</td>
									</tr>
								</table>
							</div>

							<input type="hidden" name="action" value="update"/>
							<input type="hidden" name="page_options"
								   value="<?php echo \implode(',', $formProperties); ?>"/>

							<p class="submit">
								<input type="submit"
									   class="robokassa-button-primary btn btn-default btn-clipboard btn-main"
									   value="<?php _e('Save Changes') ?>"/>
							</p>

					</form>
				</div>
			</div>
		</div>
	</div>
</div>
