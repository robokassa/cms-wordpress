<?php

namespace Robokassa\Payment;

use InvalidArgumentException;
use WC_Order;
use WC_Order_Item_Product;
use WC_Product;

/**
 * Управляет кодами маркировки товаров для итогового кассового чека.
 */
class MarkingManager {
	const OPTION_ENABLED = 'robokassa_payment_marking_enabled';
	const PRODUCT_META_KEY = '_robokassa_marking_required';
	const ORDER_ITEM_REQUIRED_META_KEY = '_robokassa_marking_required';
	const ORDER_ITEM_CODES_META_KEY = '_robokassa_marking_codes';
	const AJAX_NONCE_ACTION = 'robokassa_marking_codes';

	/**
	 * @return bool
	 */
	public function isEnabled() {
		return get_option(self::OPTION_ENABLED, 'no') === 'yes'
			&& get_option('robokassa_country_code', 'RU') === 'RU';
	}

	/**
	 * @return void
	 */
	public function renderProductField() {
		if (!$this->isEnabled() || !function_exists('woocommerce_wp_checkbox')) {
			return;
		}

		woocommerce_wp_checkbox(array(
			'id' => self::PRODUCT_META_KEY,
			'label' => 'Robokassa: маркируемый товар',
			'description' => 'Для каждой единицы товара потребуется отсканировать код маркировки в карточке заказа. Код будет передан только во втором чеке.',
			'desc_tip' => true,
		));
	}

	/**
	 * @param WC_Product $product
	 * @return void
	 */
	public function saveProductField($product) {
		if (!$this->isEnabled() || !$product instanceof WC_Product) {
			return;
		}

		if (isset($_POST[self::PRODUCT_META_KEY]) && $_POST[self::PRODUCT_META_KEY] === 'yes') {
			$product->update_meta_data(self::PRODUCT_META_KEY, 'yes');
			return;
		}

		$product->delete_meta_data(self::PRODUCT_META_KEY);
	}

	/**
	 * Фиксирует признак маркировки в позиции заказа на момент оформления.
	 *
	 * @param WC_Order_Item_Product $item
	 * @param string                $cartItemKey
	 * @param array                 $values
	 * @return void
	 */
	public function snapshotOrderItem($item, $cartItemKey, $values) {
		if (!$this->isEnabled() || !$item instanceof WC_Order_Item_Product) {
			return;
		}

		$product = isset($values['data']) ? $values['data'] : null;

		if ($product instanceof WC_Product && $this->isProductMarked($product)) {
			$item->add_meta_data(self::ORDER_ITEM_REQUIRED_META_KEY, 'yes', true);
		}
	}

	/**
	 * @param WC_Product $product
	 * @return bool
	 */
	public function isProductMarked($product) {
		if (!$product instanceof WC_Product) {
			return false;
		}

		if ($product->get_meta(self::PRODUCT_META_KEY, true) === 'yes') {
			return true;
		}

		if (method_exists($product, 'is_type') && $product->is_type('variation')) {
			$parentId = (int)$product->get_parent_id();
			$parent = $parentId > 0 ? wc_get_product($parentId) : null;

			return $parent instanceof WC_Product
				&& $parent->get_meta(self::PRODUCT_META_KEY, true) === 'yes';
		}

		return false;
	}

	/**
	 * @param WC_Order_Item_Product $item
	 * @return bool
	 */
	public function isOrderItemMarked($item) {
		if (!$item instanceof WC_Order_Item_Product) {
			return false;
		}

		if ($item->get_meta(self::ORDER_ITEM_REQUIRED_META_KEY, true) === 'yes') {
			return true;
		}

		return $this->isProductMarked($item->get_product());
	}

	/**
	 * @param WC_Order_Item_Product $item
	 * @return int
	 */
	public function getRemainingQuantity($item) {
		$quantity = max(0, (int)$item->get_quantity());
		$order = method_exists($item, 'get_order') ? $item->get_order() : null;

		if ($order instanceof WC_Order && method_exists($order, 'get_qty_refunded_for_item')) {
			$quantity += (int)$order->get_qty_refunded_for_item($item->get_id());
		}

		return max(0, $quantity);
	}

	/**
	 * @param WC_Order_Item_Product $item
	 * @return array
	 */
	public function getCodes($item) {
		$codes = $item->get_meta(self::ORDER_ITEM_CODES_META_KEY, true);

		return is_array($codes) ? array_values($codes) : array();
	}

	/**
	 * @param array $encodedCodes
	 * @return array
	 */
	public function decodeCodes($encodedCodes) {
		if (!is_array($encodedCodes)) {
			throw new InvalidArgumentException('Некорректный список кодов маркировки.');
		}

		$codes = array();

		foreach ($encodedCodes as $encodedCode) {
			if (!is_string($encodedCode) || $encodedCode === '') {
				$codes[] = '';
				continue;
			}

			$code = base64_decode($encodedCode, true);

			if ($code === false || !$this->isValidCode($code)) {
				throw new InvalidArgumentException('Код маркировки содержит недопустимые символы или имеет неверный формат.');
			}

			$codes[] = $code;
		}

		return $codes;
	}

	/**
	 * @param string $code
	 * @return bool
	 */
	public function isValidCode($code) {
		$length = strlen($code);

		if ($length < 1 || $length > 1024) {
			return false;
		}

		// Коды маркировки состоят из ASCII-символов. Разрешаем также GS (ASCII 29).
		return preg_match('/^[\x1D\x20-\x7E]+$/D', $code) === 1;
	}

	/**
	 * Возвращает ошибки заполнения маркировки перед отправкой второго чека.
	 *
	 * @param WC_Order $order
	 * @return array
	 */
	public function validateOrder($order) {
		if (!$this->isEnabled() || !$order instanceof WC_Order) {
			return array();
		}

		$errors = array();
		$seen = array();

		foreach ($order->get_items() as $item) {
			if (!$this->isOrderItemMarked($item)) {
				continue;
			}

			$quantity = $this->getRemainingQuantity($item);
			$codes = array_values(array_filter($this->getCodes($item), 'strlen'));
			$name = method_exists($item, 'get_name') ? $item->get_name() : 'товар';

			if (count($codes) !== $quantity) {
				$errors[] = sprintf('%s: заполнено кодов %d из %d', $name, count($codes), $quantity);
			}

			foreach ($codes as $code) {
				$key = hash('sha256', $code);

				if (isset($seen[$key])) {
					$errors[] = sprintf('%s: обнаружен повторяющийся код маркировки', $name);
					break;
				}

				$seen[$key] = true;
			}
		}

		return $errors;
	}

	/**
	 * Разбивает маркированную позицию на строки по одной физической единице товара.
	 *
	 * @param WC_Order_Item_Product $item
	 * @param array                 $receiptItem
	 * @return array
	 */
	public function buildSecondReceiptItems($item, $receiptItem) {
		if (!$this->isEnabled() || !$this->isOrderItemMarked($item)) {
			return array($receiptItem);
		}

		$codes = array_values(array_filter($this->getCodes($item), 'strlen'));
		$quantity = $this->getRemainingQuantity($item);

		if ($quantity < 1 || count($codes) !== $quantity) {
			throw new InvalidArgumentException('Для маркируемого товара не заполнены все коды маркировки.');
		}

		$totalCents = (int)round((float)$receiptItem['sum'] * 100);
		$unitCents = intdiv($totalCents, $quantity);
		$remainder = $totalCents % $quantity;
		$result = array();

		foreach ($codes as $index => $code) {
			$current = $receiptItem;
			$current['quantity'] = 1;
			$current['sum'] = number_format(($unitCents + ($index < $remainder ? 1 : 0)) / 100, 2, '.', '');
			$current['nomenclature_code'] = $code;
			$result[] = $current;
		}

		return $result;
	}

	/**
	 * @return void
	 */
	public function renderOrderItemHeader() {
		if (!$this->isEnabled()) {
			return;
		}

		echo '<th class="robokassa-marking-column">Маркировка</th>';
	}

	/**
	 * @param WC_Product|null       $product
	 * @param WC_Order_Item_Product $item
	 * @param int                   $itemId
	 * @return void
	 */
	public function renderOrderItemValue($product, $item, $itemId) {
		if (!$this->isEnabled()) {
			return;
		}

		if (!$item instanceof WC_Order_Item_Product || !$this->isOrderItemMarked($item)) {
			echo '<td class="robokassa-marking-column">—</td>';
			return;
		}

		$quantity = $this->getRemainingQuantity($item);
		$filled = count(array_filter($this->getCodes($item), 'strlen'));
		$class = $quantity > 0 && $filled === $quantity ? ' is-complete' : '';

		echo '<td class="robokassa-marking-column">';
		echo '<button type="button" class="button robokassa-marking-open' . esc_attr($class) . '" data-item-id="' . esc_attr($itemId) . '">';
		echo esc_html(sprintf('%d из %d', $filled, $quantity));
		echo '</button></td>';
	}

	/**
	 * @return void
	 */
	public function enqueueOrderAssets() {
		if (!$this->isEnabled() || !$this->isOrderEditScreen()) {
			return;
		}

		$scriptPath = dirname(dirname(dirname(__DIR__))) . '/assets/js/admin-marking.js';
		$stylePath = dirname(dirname(dirname(__DIR__))) . '/assets/css/admin-marking.css';

		wp_enqueue_script(
			'robokassa-admin-marking',
			plugins_url('assets/js/admin-marking.js', dirname(dirname(dirname(__DIR__))) . '/wp_robokassa.php'),
			array('jquery'),
			file_exists($scriptPath) ? filemtime($scriptPath) : null,
			true
		);
		wp_localize_script('robokassa-admin-marking', 'robokassaMarking', array(
			'ajaxUrl' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce(self::AJAX_NONCE_ACTION),
		));

		wp_enqueue_style(
			'robokassa-admin-marking',
			plugins_url('assets/css/admin-marking.css', dirname(dirname(dirname(__DIR__))) . '/wp_robokassa.php'),
			array(),
			file_exists($stylePath) ? filemtime($stylePath) : null
		);
	}

	/**
	 * @return void
	 */
	public function renderOrderModal() {
		if (!$this->isEnabled() || !$this->isOrderEditScreen()) {
			return;
		}

		?>
		<div id="robokassa-marking-modal" class="robokassa-marking-modal" hidden>
			<div class="robokassa-marking-dialog" role="dialog" aria-modal="true" aria-labelledby="robokassa-marking-title">
				<button type="button" class="robokassa-marking-close" aria-label="Закрыть">&times;</button>
				<h2 id="robokassa-marking-title">Маркировка товара</h2>
				<p>Отсканируйте DataMatrix с каждой физической единицы товара.</p>
				<div class="robokassa-marking-fields"></div>
				<p class="robokassa-marking-message" hidden></p>
				<div class="robokassa-marking-actions">
					<button type="button" class="button button-primary robokassa-marking-save">Сохранить</button>
					<button type="button" class="button robokassa-marking-cancel">Отмена</button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * @return void
	 */
	public function ajaxGetCodes() {
		check_ajax_referer(self::AJAX_NONCE_ACTION, 'nonce');
		$item = $this->getRequestedOrderItem();

		if (!$item) {
			return;
		}

		$codes = array_map('base64_encode', $this->getCodes($item));

		wp_send_json_success(array(
			'itemId' => $item->get_id(),
			'name' => $item->get_name(),
			'quantity' => $this->getRemainingQuantity($item),
			'codes' => $codes,
		));
	}

	/**
	 * @return void
	 */
	public function ajaxSaveCodes() {
		check_ajax_referer(self::AJAX_NONCE_ACTION, 'nonce');
		$item = $this->getRequestedOrderItem();

		if (!$item) {
			return;
		}

		try {
			$encodedCodes = isset($_POST['codes']) ? $_POST['codes'] : array();
			$codes = $this->decodeCodes($encodedCodes);
			$quantity = $this->getRemainingQuantity($item);

			if (count($codes) !== $quantity) {
				throw new InvalidArgumentException(sprintf('Ожидалось кодов: %d.', $quantity));
			}

			$nonEmptyCodes = array_values(array_filter($codes, 'strlen'));
			$hashes = array_map(static function($code) {
				return hash('sha256', $code);
			}, $nonEmptyCodes);

			if (count($hashes) !== count(array_unique($hashes))) {
				throw new InvalidArgumentException('Один код нельзя использовать для нескольких единиц товара.');
			}

			$item->update_meta_data(self::ORDER_ITEM_CODES_META_KEY, $codes);
			$item->save();

			wp_send_json_success(array(
				'filled' => count($nonEmptyCodes),
				'quantity' => $quantity,
			));
		} catch (InvalidArgumentException $exception) {
			wp_send_json_error(array('message' => $exception->getMessage()), 422);
		}
	}

	/**
	 * @return WC_Order_Item_Product|null
	 */
	private function getRequestedOrderItem() {
		$itemId = isset($_POST['itemId']) ? absint($_POST['itemId']) : 0;
		$item = $itemId > 0 && class_exists('WC_Order_Factory')
			? \WC_Order_Factory::get_order_item($itemId)
			: null;

		if (!$item instanceof WC_Order_Item_Product || !$this->isOrderItemMarked($item)) {
			wp_send_json_error(array('message' => 'Позиция заказа не найдена или не требует маркировки.'), 404);
			return null;
		}

		$orderId = method_exists($item, 'get_order_id') ? (int)$item->get_order_id() : 0;

		if (!current_user_can('edit_shop_order', $orderId) && !current_user_can('edit_shop_orders')) {
			wp_send_json_error(array('message' => 'Недостаточно прав для изменения заказа.'), 403);
			return null;
		}

		return $item;
	}

	/**
	 * @return bool
	 */
	private function isOrderEditScreen() {
		if (!function_exists('get_current_screen')) {
			return false;
		}

		$screen = get_current_screen();

		return $screen && in_array($screen->id, array('shop_order', 'woocommerce_page_wc-orders'), true);
	}
}
