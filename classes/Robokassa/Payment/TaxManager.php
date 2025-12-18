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
		'vat8',
		'vat12'
	);

	/** @var array */
	private $taxLabels = array(
		'none' => 'Без НДС',
		'vat0' => 'НДС по ставке 0%',
		'vat10' => 'НДС чека по ставке 10%',
		'vat22' => 'НДС чека по ставке 22%',
		'vat20' => 'НДС чека по ставке 20%',
		'vat110' => 'НДС чека по расчётной ставке 10/110',
		'vat118' => 'НДС чека по расчётной ставке 20/120',
		'vat122' => 'НДС чека по расчётной ставке 22/122',
		'vat5' => 'НДС по ставке 5%',
		'vat7' => 'НДС по ставке 7%',
		'vat105' => 'НДС чека по расчётной ставке 5/105',
		'vat107' => 'НДС чека по расчётной ставке 7/107',
		'vat8' => 'НДС чека по ставке 8% (Казахстан)',
		'vat12' => 'НДС чека по ставке 12% (Казахстан)'
	);

	/** @var array */
	private $taxRates = array(
		'none' => 0,
		'vat0' => 0,
		'vat5' => 5,
		'vat7' => 7,
		'vat8' => 8,
		'vat10' => 10,
		'vat12' => 12,
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
		if ($tax === 'vat120') {
			return 'vat118';
		}

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

		return $this->normalizeTaxCode((string)$tax);
	}

	/**
	 * Возвращает список подписей ставок для админки.
	 *
	 * @return array
	 */
	public function getTaxLabels() {
		return $this->taxLabels;
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

		return $this->normalizeTaxCode((string)$tax);
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

		foreach ($this->getTaxLabels() as $code => $label) {
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

		if (!in_array($value, $this->knownTaxes, true)) {
			$product->delete_meta_data($this->metaKey);
			return;
		}

		$product->update_meta_data(
			$this->metaKey,
			$this->normalizeTaxCode($value)
		);
	}
}
