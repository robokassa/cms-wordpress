<?php

namespace Robokassa\Payment;

use WC_Order_Item;
use WC_Order_Item_Product;
use WC_Product;
use WP_Post;

/**
 * Управляет источником признака предмета расчёта.
 */
class PaymentObjectManager {
	/** @var string */
	private $metaKey = '_robokassa_payment_object';

	/** @var string */
	private $sourceOptionKey = 'robokassa_payment_payment_object_source';

	/** @var string */
	private $globalOptionKey = 'robokassa_payment_paymentObject';

	/**
	 * Возвращает список допустимых кодов предмета расчёта.
	 *
	 * @return array
	 */
	public function getKnownPaymentObjects() {
		$codes = array();

		foreach (Helper::$paymentObjects as $paymentObject) {
			if (!isset($paymentObject['code'])) {
				continue;
			}

			$codes[] = (string)$paymentObject['code'];
		}

		return $codes;
	}

	/**
	 * Возвращает предмет расчёта по умолчанию из настроек плагина.
	 *
	 * @return string
	 */
	public function getDefaultPaymentObject() {
		$paymentObject = get_option($this->globalOptionKey, 'commodity');

		return $this->normalizePaymentObject((string)$paymentObject);
	}

	/**
	 * Возвращает признак предмета расчёта для позиции заказа.
	 *
	 * @param WC_Order_Item $item
	 *
	 * @return string
	 */
	public function getItemPaymentObject($item) {
		$defaultPaymentObject = $this->getDefaultPaymentObject();
		$source = get_option($this->sourceOptionKey, 'global');

		if ($source !== 'product' || !$item instanceof WC_Order_Item_Product) {
			return $defaultPaymentObject;
		}

		$product = $item->get_product();

		if (!$product instanceof WC_Product) {
			return $defaultPaymentObject;
		}

		return $this->resolveProductPaymentObject($product, $defaultPaymentObject);
	}

	/**
	 * Отрисовывает поле выбора признака предмета расчёта в карточке товара.
	 *
	 * @return void
	 */
	public function renderProductPaymentObjectField() {
		if (!function_exists('woocommerce_wp_select')) {
			return;
		}

		global $post;

		if (!$post instanceof WP_Post) {
			return;
		}

		$value = (string)get_post_meta($post->ID, $this->metaKey, true);
		$options = $this->getProductFieldOptions();

		woocommerce_wp_select(array(
			'id' => $this->metaKey,
			'label' => 'Robokassa: предмет расчёта',
			'options' => $options,
			'value' => $value,
			'desc_tip' => true,
			'description' => 'Выберите предмет расчёта для передачи в Робокассу. Если значение не указано, будет использована настройка из общих параметров плагина.'
		));
	}

	/**
	 * Сохраняет признак предмета расчёта из карточки товара.
	 *
	 * @param WC_Product $product
	 *
	 * @return void
	 */
	public function saveProductPaymentObjectField($product) {
		if (!$product instanceof WC_Product) {
			return;
		}

		if (!isset($_POST[$this->metaKey])) {
			return;
		}

		$value = sanitize_text_field(wp_unslash($_POST[$this->metaKey]));

		if ($value === '' || !$this->isKnownPaymentObject($value)) {
			$product->delete_meta_data($this->metaKey);
			return;
		}

		$product->update_meta_data($this->metaKey, $value);
	}

	/**
	 * Возвращает значение предмета расчёта товара с учётом fallback.
	 *
	 * @param WC_Product $product
	 * @param string     $defaultPaymentObject
	 *
	 * @return string
	 */
	private function resolveProductPaymentObject($product, $defaultPaymentObject) {
		$paymentObject = $product->get_meta($this->metaKey, true);

		if ($paymentObject === '' || $paymentObject === null) {
			return $defaultPaymentObject;
		}

		return $this->normalizePaymentObject((string)$paymentObject);
	}

	/**
	 * Возвращает набор опций для поля товара.
	 *
	 * @return array
	 */
	private function getProductFieldOptions() {
		$options = array('' => 'Использовать настройку по умолчанию');

		foreach (Helper::$paymentObjects as $paymentObject) {
			if (!isset($paymentObject['code'], $paymentObject['title'])) {
				continue;
			}

			$options[(string)$paymentObject['code']] = (string)$paymentObject['title'];
		}

		return $options;
	}

	/**
	 * Проверяет, что код предмета расчёта поддерживается.
	 *
	 * @param string $value
	 *
	 * @return bool
	 */
	private function isKnownPaymentObject($value) {
		return in_array($value, $this->getKnownPaymentObjects(), true);
	}

	/**
	 * Нормализует код предмета расчёта.
	 *
	 * @param string $value
	 *
	 * @return string
	 */
	private function normalizePaymentObject($value) {
		if ($this->isKnownPaymentObject($value)) {
			return $value;
		}

		return 'commodity';
	}
}