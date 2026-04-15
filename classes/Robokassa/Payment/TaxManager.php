<?php

namespace Robokassa\Payment;

use WC_Order_Item;
use WC_Order_Item_Product;
use WC_Product;
use WP_Post;

/**
 * Управляет налоговыми ставками товаров и заказов Robokassa.
 */
class TaxManager {
	/** @var string */
	private $metaKey = '_robokassa_tax_rate';

	/** @var array */
	private $knownTaxes = array(
		'none',
		'vat0',
		'vat10',
		'vat22',
		'vat20',
		'vat110',
		'vat118',
		'vat120',
		'vat122',
		'vat5',
		'vat7',
		'vat105',
		'vat107',
		'vat12',
		'vat16'
	);

	/** @var array */
	private $countryTaxes = array(
		'RU' => array(
			'none',
			'vat0',
			'vat10',
			'vat110',
			'vat20',
			'vat22',
			'vat120',
			'vat122',
			'vat5',
			'vat7',
			'vat105',
			'vat107'
		),
		'KZ' => array(
			'none',
			'vat0',
			'vat5',
			'vat12',
			'vat16'
		)
	);

	/** @var array */
	private $taxLabels = array(
		'none' => 'Без НДС',
		'vat0' => 'НДС по ставке 0%',
		'vat10' => 'НДС чека по ставке 10%',
		'vat22' => 'НДС чека по ставке 22%',
		'vat20' => 'НДС чека по ставке 20%',
		'vat110' => 'НДС чека по расчётной ставке 10/110',
		'vat120' => 'НДС чека по расчётной ставке 20/120',
		'vat122' => 'НДС чека по расчётной ставке 22/122',
		'vat5' => 'НДС по ставке 5%',
		'vat7' => 'НДС по ставке 7%',
		'vat105' => 'НДС чека по расчётной ставке 5/105',
		'vat107' => 'НДС чека по расчётной ставке 7/107',
		'vat12' => 'НДС чека по расчётной ставке 12%',
		'vat16' => 'НДС чека по расчётной ставке 16%'
	);

	/** @var array */
	private $taxRates = array(
		'none' => 0,
		'vat0' => 0,
		'vat5' => 5,
		'vat7' => 7,
		'vat10' => 10,
		'vat12' => 12,
		'vat16' => 16,
		'vat22' => 22,
		'vat20' => 20,
		'vat105' => 5 / 105,
		'vat107' => 7 / 107,
		'vat110' => 10 / 110,
		'vat120' => 20 / 120,
		'vat122' => 22 / 122
	);

	/**
	 * Возвращает нормализованное значение налоговой ставки.
	 *
	 * @param string $tax
	 *
	 * @return string
	 */
	public function normalizeTaxCode($tax) {
		if ($tax === 'vat118') {
			return 'vat120';
		}

		if (!in_array($tax, $this->knownTaxes, true)) {
			return 'none';
		}

		return $tax;
	}

	/**
	 * Подготавливает значение налоговой ставки для отображения в админке.
	 *
	 * @param string $tax
	 *
	 * @return string
	 */
	public function prepareTaxValueForDisplay($tax) {
		return $tax;
	}

	/**
	 * Возвращает налоговую ставку по умолчанию.
	 *
	 * @return string
	 */
	public function getDefaultTax() {
		$tax = get_option('robokassa_payment_tax');

		if ($tax === false || $tax === '') {
			return 'none';
		}

		return $this->normalizeTaxCodeForCountry((string)$tax, get_option('robokassa_country_code', 'RU'));
	}

	/**
	 * Возвращает список подписей ставок для админки.
	 *
	 * @param string|null $country
	 *
	 * @return array
	 */
	public function getTaxLabels($country = null) {
		$country = $country ?: get_option('robokassa_country_code', 'RU');
		$codes = $this->getTaxCodesForCountry($country);
		$labels = array();

		foreach ($codes as $code) {
			if (isset($this->taxLabels[$code])) {
				$labels[$code] = $this->taxLabels[$code];
			}
		}

		return $labels;
	}

	/**
	 * Возвращает список налоговых кодов для страны.
	 *
	 * @param string $country
	 *
	 * @return array
	 */
	public function getTaxCodesForCountry($country) {
		if (isset($this->countryTaxes[$country])) {
			return $this->countryTaxes[$country];
		}

		return $this->countryTaxes['RU'];
	}

	/**
	 * Возвращает нормализованное значение ставки с учётом страны.
	 *
	 * @param string $tax
	 * @param string $country
	 *
	 * @return string
	 */
	public function normalizeTaxCodeForCountry($tax, $country) {
		$tax = $this->normalizeTaxCode($tax);

		if (!in_array($tax, $this->getTaxCodesForCountry($country), true)) {
			return 'none';
		}

		return $tax;
	}

	/**
	 * Вычисляет сумму налога для указанного чека.
	 *
	 * @param string $tax
	 * @param float|int $amount
	 *
	 * @return float
	 */
	public function calculateTaxSum($tax, $amount) {
		$code = $this->normalizeTaxCode((string)$tax);

		$rate = 0.0;

		if (isset($this->taxRates[$code])) {
			$rate = (float)$this->taxRates[$code];
		}

		$formattedAmount = wc_format_decimal(
			$amount,
			(int)get_option('woocommerce_price_num_decimals')
		);

		return ((float)$formattedAmount / 100) * $rate;
	}

	/**
	 * Определяет налоговую ставку для позиции заказа.
	 *
	 * @param WC_Order_Item $item
	 *
	 * @return string
	 */
	public function getItemTax($item) {
		$defaultTax = $this->getDefaultTax();
		$mode = get_option('robokassa_payment_tax_source', 'global');

		if ($mode !== 'product' || !$item instanceof WC_Order_Item_Product) {
			return $defaultTax;
		}

		$product = $item->get_product();

		if (!$product instanceof WC_Product) {
			return $defaultTax;
		}

		$tax = $product->get_meta($this->metaKey, true);

		if ($tax === '' || $tax === null) {
			return $defaultTax;
		}

		return $this->normalizeTaxCodeForCountry((string)$tax, get_option('robokassa_country_code', 'RU'));
	}

	/**
	 * Отрисовывает поле выбора налоговой ставки в карточке товара.
	 *
	 * @return void
	 */
	public function renderProductTaxField() {
		if (!function_exists('woocommerce_wp_select')) {
			return;
		}

		global $post;

		if (!$post instanceof WP_Post) {
			return;
		}

		$value = get_post_meta($post->ID, $this->metaKey, true);
		$value = $this->prepareTaxValueForDisplay((string)$value);

		$options = array('' => 'Использовать настройку по умолчанию');

		foreach ($this->getTaxLabels(get_option('robokassa_country_code', 'RU')) as $code => $label) {
			$options[$code] = $label;
		}

		woocommerce_wp_select(array(
			'id' => $this->metaKey,
			'label' => 'Robokassa: налоговая ставка',
			'options' => $options,
			'value' => $value,
			'desc_tip' => true,
			'description' => 'Выберите налоговую ставку для передачи в Робокассу. Если значение не указано, будет использована ставка из общих настроек плагина.'
		));
	}

	/**
	 * Сохраняет выбранную налоговую ставку товара.
	 *
	 * @param WC_Product $product
	 *
	 * @return void
	 */
	public function saveProductTaxField($product) {
		if (!$product instanceof WC_Product) {
			return;
		}

		if (!isset($_POST[$this->metaKey])) {
			return;
		}

		$rawValue = wp_unslash($_POST[$this->metaKey]);
		$value = sanitize_text_field($rawValue);

		if ($value === '') {
			$product->delete_meta_data($this->metaKey);
			return;
		}

		$value = $this->normalizeTaxCodeForCountry($value, get_option('robokassa_country_code', 'RU'));

		if ($value === 'none' && sanitize_text_field($rawValue) !== 'none') {
			$product->delete_meta_data($this->metaKey);
			return;
		}

		$product->update_meta_data(
			$this->metaKey,
			$value
		);
	}
}
