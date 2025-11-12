<?php

namespace Robokassa\Payment;

use WC_Order_Item_Product;
use WC_Product;
use WP_Post;

/**
 * Управляет полями агенского признака товаров Robokassa.
 */
class AgentManager {
	/** @var array */
	private $metaKeys = array(
		'type' => '_robokassa_agent_type',
		'name' => '_robokassa_agent_supplier_name',
		'inn' => '_robokassa_agent_supplier_inn',
		'phones' => '_robokassa_agent_supplier_phones',
	);

	/** @var array */
	private $agentTypes = array(
		'bank_paying_agent' => 'Банковский платежный агент',
		'bank_paying_subagent' => 'Банковский платежный субагент',
		'paying_agent' => 'Платежный агент',
		'paying_subagent' => 'Платежный субагент',
		'attorney' => 'Поверенный',
		'commission_agent' => 'Комиссионер',
		'another' => 'Другой тип агента',
	);

	/** @var string */
	private $optionKey = 'robokassa_payment_agent_fields_enabled';

	/**
	 * Отрисовывает поля агенского признака в карточке товара.
	 *
	 * @return void
	 */
	public function renderProductAgentFields() {
		if (!$this->shouldDisplayProductFields()) {
			return;
		}

		$post = $this->getCurrentPost();

		if (!$post instanceof WP_Post) {
			return;
		}

		$values = $this->getProductValues((int)$post->ID);

		$this->renderSectionStart();
		$this->renderAgentTypeField($values['type']);
		$this->renderTextField('name', 'Robokassa: Наименование поставщика', $values['name']);
		$this->renderTextField('inn', 'Robokassa: ИНН поставщика', $values['inn']);
		$this->renderTextField(
			'phones',
			'Robokassa: Телефон поставщика',
			$values['phones'],
			'Перечислите телефоны через запятую.'
		);
		$this->renderSectionEnd();
	}

	/**
	 * Сохраняет поля агенского признака товара.
	 *
	 * @param WC_Product $product
	 *
	 * @return void
	 */
	public function saveProductAgentFields($product) {
		if (!$this->isEnabled() || !$product instanceof WC_Product) {
			return;
		}

		$this->saveAgentType($product);
		$this->saveTextValue($product, 'name');
		$this->saveTextValue($product, 'inn');
		$this->saveTextValue($product, 'phones');
	}

	/**
	 * Возвращает данные агенского признака для позиции заказа.
	 *
	 * @param WC_Order_Item_Product $item
	 *
	 * @return array
	 */
	public function getItemAgentData($item) {
		if (!$this->isEnabled() || !$item instanceof WC_Order_Item_Product) {
			return array();
		}

		$product = $item->get_product();

		if (!$product instanceof WC_Product) {
			return array();
		}

		return $this->buildAgentPayload($product);
	}

	/**
	 * Формирует данные агенского признака для товара.
	 *
	 * @param WC_Product $product
	 *
	 * @return array
	 */
	public function buildAgentPayload($product) {
		if (!$this->isEnabled() || !$product instanceof WC_Product) {
			return array();
		}

		$values = $this->getProductMetaValues($product);
		$phones = $this->preparePhones($values['phones']);

		if (!$this->isPayloadComplete($values['type'], $values['name'], $values['inn'], $phones)) {
			return array();
		}

		return array(
			'agent_info' => array('type' => $values['type']),
			'supplier_info' => $this->buildSupplierInfo($values['name'], $values['inn'], $phones),
		);
	}

	/**
	 * Сохраняет выбранный тип агента.
	 *
	 * @param WC_Product $product
	 *
	 * @return void
	 */
	private function saveAgentType($product) {
		if (!isset($_POST[$this->metaKeys['type']])) {
			return;
		}

		$rawValue = wp_unslash($_POST[$this->metaKeys['type']]);
		$value = sanitize_text_field($rawValue);

		if ($value === '' || !isset($this->agentTypes[$value])) {
			$product->delete_meta_data($this->metaKeys['type']);
			return;
		}

		$product->update_meta_data($this->metaKeys['type'], $value);
	}

	/**
	 * Сохраняет текстовое значение поля.
	 *
	 * @param WC_Product $product
	 * @param string $field
	 *
	 * @return void
	 */
	private function saveTextValue($product, $field) {
		$key = $this->metaKeys[$field];

		if (!isset($_POST[$key])) {
			return;
		}

		$rawValue = wp_unslash($_POST[$key]);
		$value = wc_clean($rawValue);

		if ($value === '') {
			$product->delete_meta_data($key);
			return;
		}

		$product->update_meta_data($key, $value);
	}

	/**
	 * Возвращает описание агента поставщика.
	 *
	 * @param string $name
	 * @param string $inn
	 * @param array $phones
	 *
	 * @return array
	 */
	private function buildSupplierInfo($name, $inn, array $phones) {
		return array(
			'name' => $name,
			'inn' => $inn,
			'phones' => $phones,
		);
	}

	/**
	 * Преобразует текст телефонов в массив.
	 *
	 * @param string $value
	 *
	 * @return array
	 */
	private function preparePhones($value) {
		if ($value === '') {
			return array();
		}

		$rawPhones = preg_split('/[,;\r\n]+/', $value);

		if (!is_array($rawPhones)) {
			return array();
		}

		$phones = array();

		foreach ($rawPhones as $phone) {
			$clean = trim($phone);

			if ($clean === '') {
				continue;
			}

			$phones[] = $clean;
		}

		return array_values(array_unique($phones));
	}

	/**
	 * Проверяет, нужно ли отображать поля агента в карточке товара.
	 *
	 * @return bool
	 */
	private function shouldDisplayProductFields() {
		if (!$this->isEnabled() || !function_exists('woocommerce_wp_select')) {
			return false;
		}

		return $this->getStoreCountryCode() !== 'KZ';
	}

	/**
	 * Возвращает код страны магазина.
	 *
	 * @return string
	 */
	private function getStoreCountryCode() {
		return (string)get_option('robokassa_country_code', 'RU');
	}

	/**
	 * Возвращает текущий объект записи товара в админке.
	 *
	 * @return WP_Post|null
	 */
	private function getCurrentPost() {
		global $post;

		if ($post instanceof WP_Post) {
			return $post;
		}

		return null;
	}

	/**
	 * Возвращает сохранённые значения полей для страницы редактирования товара.
	 *
	 * @param int $productId
	 *
	 * @return array
	 */
	private function getProductValues($productId) {
		return array(
			'type' => (string)get_post_meta($productId, $this->metaKeys['type'], true),
			'name' => (string)get_post_meta($productId, $this->metaKeys['name'], true),
			'inn' => (string)get_post_meta($productId, $this->metaKeys['inn'], true),
			'phones' => (string)get_post_meta($productId, $this->metaKeys['phones'], true),
		);
	}

	/**
	 * Возвращает сохранённые значения полей из объекта товара.
	 *
	 * @param WC_Product $product
	 *
	 * @return array
	 */
	private function getProductMetaValues($product) {
		return array(
			'type' => (string)$product->get_meta($this->metaKeys['type'], true),
			'name' => trim((string)$product->get_meta($this->metaKeys['name'], true)),
			'inn' => trim((string)$product->get_meta($this->metaKeys['inn'], true)),
			'phones' => (string)$product->get_meta($this->metaKeys['phones'], true),
		);
	}

	/**
	 * Проверяет, заполнены ли все поля для передачи агенских данных.
	 *
	 * @param string $type
	 * @param string $name
	 * @param string $inn
	 * @param array $phones
	 *
	 * @return bool
	 */
	private function isPayloadComplete($type, $name, $inn, array $phones) {
		if ($type === '' || !isset($this->agentTypes[$type])) {
			return false;
		}

		if ($name === '' || $inn === '' || empty($phones)) {
			return false;
		}

		return true;
	}

	/**
	 * Открывает контейнер блока настроек агента.
	 *
	 * @return void
	 */
	private function renderSectionStart() {
		echo '<div class="options_group options_group--robokassa-agent">';
		echo '<p class="form-field robokassa-agent-title"><strong>';
		echo esc_html('Агентский товар Robokassa');
		echo '</strong></p>';
	}

	/**
	 * Закрывает контейнер блока настроек агента.
	 *
	 * @return void
	 */
	private function renderSectionEnd() {
		echo '</div>';
	}

	/**
	 * Проверяет, включена ли поддержка агенских товаров.
	 *
	 * @return bool
	 */
	private function isEnabled() {
		return get_option($this->optionKey, 'no') === 'yes';
	}

	/**
	 * Отрисовывает поле выбора типа агента.
	 *
	 * @param string $value
	 *
	 * @return void
	 */
	private function renderAgentTypeField($value) {
		woocommerce_wp_select(array(
			'id' => $this->metaKeys['type'],
			'label' => 'Robokassa: Тип агента',
			'options' => $this->agentTypes,
			'value' => $value,
			'wrapper_class' => 'form-field form-row-wide robokassa-agent-field',
			'desc_tip' => true,
			'description' => 'Выберите признак агента для передачи в Робокассу.'
		));
	}

	/**
	 * Отрисовывает текстовое поле настроек.
	 *
	 * @param string $field
	 * @param string $label
	 * @param string $value
	 * @param string $description
	 *
	 * @return void
	 */
	private function renderTextField($field, $label, $value, $description = '') {
		woocommerce_wp_text_input(array(
			'id' => $this->metaKeys[$field],
			'label' => $label,
			'value' => $value,
			'wrapper_class' => 'form-field form-row-wide robokassa-agent-field',
			'description' => $description,
			'desc_tip' => $description !== ''
		));
	}
}
