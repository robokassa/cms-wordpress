<?php

if (!defined('MINUTE_IN_SECONDS')) {
	define('MINUTE_IN_SECONDS', 60);
}
if (!defined('HOUR_IN_SECONDS')) {
	define('HOUR_IN_SECONDS', 3600);
}

class WP_Error {
	private $code;
	private $message;
	private $data;

	public function __construct($code = '', $message = '', $data = null) {
		$this->code = $code;
		$this->message = $message;
		$this->data = $data;
	}

	public function get_error_code() {
		return $this->code;
	}

	public function get_error_message() {
		return $this->message;
	}

	public function get_error_data() {
		return $this->data;
	}
}

class WP_Post {
	public $ID;

	public function __construct($id = 0) {
		$this->ID = $id;
	}
}

class WC_Product {
	private $meta = array();
	private $title;

	public function __construct($title = 'Product', array $meta = array()) {
		$this->title = $title;
		$this->meta = $meta;
	}

	public function get_meta($key, $single = true) {
		return isset($this->meta[$key]) ? $this->meta[$key] : '';
	}

	public function update_meta_data($key, $value) {
		$this->meta[$key] = $value;
	}

	public function delete_meta_data($key) {
		unset($this->meta[$key]);
	}

	public function get_title() {
		return $this->title;
	}
}

class WC_Order_Item {
}

class WC_Order_Item_Product extends WC_Order_Item {
	private $product;
	private $quantity;
	private $total;
	private $meta;
	private $id;
	private $name;
	private $order;

	public function __construct($product = null, $quantity = 1, $total = 0, array $meta = array(), $id = 1, $name = '') {
		$this->product = $product;
		$this->quantity = $quantity;
		$this->total = $total;
		$this->meta = $meta;
		$this->id = $id;
		$this->name = $name !== '' ? $name : ($product ? $product->get_title() : 'Product');
	}

	public function get_product() {
		return $this->product;
	}

	public function get_quantity() {
		return $this->quantity;
	}

	public function get_total() {
		return $this->total;
	}

	public function get_total_tax() {
		return 0;
	}

	public function get_meta($key, $single = true) {
		return isset($this->meta[$key]) ? $this->meta[$key] : '';
	}

	public function update_meta_data($key, $value) {
		$this->meta[$key] = $value;
	}

	public function add_meta_data($key, $value, $unique = false) {
		$this->meta[$key] = $value;
	}

	public function save() {
	}

	public function get_id() {
		return $this->id;
	}

	public function get_name() {
		return $this->name;
	}

	public function set_order($order) {
		$this->order = $order;
	}

	public function get_order() {
		return $this->order;
	}

	public function get_order_id() {
		return $this->order ? $this->order->get_id() : 0;
	}
}

class Robokassa_Test_Fee_Item {
	private $name;
	private $quantity;
	private $total;

	public function __construct($name, $quantity, $total) {
		$this->name = $name;
		$this->quantity = $quantity;
		$this->total = $total;
	}

	public function get_name() {
		return $this->name;
	}

	public function get_quantity() {
		return $this->quantity;
	}

	public function get_total() {
		return $this->total;
	}
}

class WC_Order {
	private $data;
	public $billing_address_1 = '';
	public $billing_address_2 = '';
	public $billing_first_name = '';
	public $billing_last_name = '';

	public function __construct($order = 0) {
		if (is_array($order)) {
			$this->data = $order;
			$this->attachOrderToItems();
			return;
		}

		$this->data = isset($GLOBALS['robokassa_test_orders'][$order])
			? $GLOBALS['robokassa_test_orders'][$order]
			: array();

		foreach (array('billing_address_1', 'billing_address_2', 'billing_first_name', 'billing_last_name') as $field) {
			if (isset($this->data[$field])) {
				$this->{$field} = $this->data[$field];
			}
		}

		$this->attachOrderToItems();
	}

	public function get_items($type = 'line_item') {
		$key = array(
			'line_item' => 'items',
			'fee' => 'fees',
			'shipping' => 'shipping_items',
		)[$type] ?? 'items';
		return isset($this->data[$key]) ? $this->data[$key] : array();
	}

	public function get_item($item_id) {
		foreach ($this->get_items() as $item) {
			if (method_exists($item, 'get_id') && (int)$item->get_id() === (int)$item_id) {
				return $item;
			}
		}
		return false;
	}

	public function get_total() {
		return isset($this->data['total']) ? $this->data['total'] : 0;
	}

	public function get_total_refunded() {
		return isset($this->data['total_refunded']) ? $this->data['total_refunded'] : 0;
	}

	public function get_refunds() {
		return isset($GLOBALS['robokassa_test_refunds'][$this->get_id()])
			? $GLOBALS['robokassa_test_refunds'][$this->get_id()]
			: array();
	}

	public function get_shipping_total() {
		return isset($this->data['shipping']) ? $this->data['shipping'] : 0;
	}

	public function get_id() {
		return isset($this->data['id']) ? $this->data['id'] : 0;
	}

	public function get_qty_refunded_for_item($item_id) {
		return isset($this->data['refunded'][$item_id]) ? $this->data['refunded'][$item_id] : 0;
	}

	public function add_order_note($note) {
		$this->data['notes'][] = $note;
	}

	public function get_meta($key, $single = true) {
		return isset($this->data['meta'][$key]) ? $this->data['meta'][$key] : '';
	}

	public function update_meta_data($key, $value) {
		$this->data['meta'][$key] = $value;
	}

	public function delete_meta_data($key) {
		unset($this->data['meta'][$key]);
	}

	public function save() {
		if ($this->get_id() > 0) {
			$GLOBALS['robokassa_test_orders'][$this->get_id()] = $this->data;
		}
	}

	public function payment_complete() {
		$this->data['payment_complete_calls'] = isset($this->data['payment_complete_calls'])
			? $this->data['payment_complete_calls'] + 1
			: 1;
		$this->data['status'] = 'processing';
	}

	public function update_status($status) {
		$this->data['status'] = $status;
	}

	public function get_status() {
		return isset($this->data['status']) ? $this->data['status'] : 'pending';
	}

	public function get_payment_method() {
		return isset($this->data['payment_method']) ? $this->data['payment_method'] : '';
	}

	public function get_checkout_payment_url($on_checkout = false) {
		return isset($this->data['checkout_payment_url'])
			? $this->data['checkout_payment_url']
			: 'https://shop.example.test/checkout/order-pay';
	}

	public function get_checkout_order_received_url() {
		return isset($this->data['checkout_order_received_url'])
			? $this->data['checkout_order_received_url']
			: 'https://shop.example.test/checkout/order-received/' . $this->get_id() . '/?key=' . $this->get_order_key();
	}

	public function get_view_order_url() {
		return isset($this->data['view_order_url'])
			? $this->data['view_order_url']
			: 'https://shop.example.test/my-account/view-order/' . $this->get_id() . '/';
	}

	public function get_order_key() {
		return isset($this->data['order_key']) ? $this->data['order_key'] : '';
	}

	public function export_data() {
		return $this->data;
	}

	private function attachOrderToItems() {
		foreach (isset($this->data['items']) ? $this->data['items'] : array() as $item) {
			if (method_exists($item, 'set_order')) {
				$item->set_order($this);
			}
		}
	}
}

class WC_Order_Refund extends WC_Order {
	public function get_amount() {
		$data = $this->export_data();
		return isset($data['amount']) ? $data['amount'] : abs((float)$this->get_total());
	}

	public function get_reason() {
		$data = $this->export_data();
		return isset($data['reason']) ? $data['reason'] : '';
	}
}

class WC_Payment_Gateway {
	public $id;
	public $method_title;
	public $method_description;
	public $description;
	public $title;
	public $long_name;
	public $enabled = 'yes';
	public $supports = array();
	public $form_fields = array();

	public function init_settings() {
	}

	public function get_option($key, $default = '') {
		return get_option($key, $default);
	}

	public function is_available() {
		return $this->enabled === 'yes';
	}

	public function can_refund_order($order) {
		return (bool)$order && in_array('refunds', $this->supports, true);
	}
}

class wpdb {
	public $prefix = 'wp_';
	public $last_error = '';
}

class Robokassa_Test_Session {
	private $values = array();

	public function get($key) {
		return isset($this->values[$key]) ? $this->values[$key] : null;
	}

	public function set($key, $value) {
		$this->values[$key] = $value;
	}
}

class Robokassa_Test_WC_Container {
	public $session;
	public $cart;

	public function __construct() {
		$this->session = new Robokassa_Test_Session();
		$this->cart = null;
	}
}

function get_option($key, $default = false) {
	return array_key_exists($key, $GLOBALS['robokassa_test_options'])
		? $GLOBALS['robokassa_test_options'][$key]
		: $default;
}

function update_option($key, $value, $autoload = null) {
	$GLOBALS['robokassa_test_options'][$key] = $value;
	return true;
}

function add_option($key, $value, $deprecated = '', $autoload = null) {
	if (!array_key_exists($key, $GLOBALS['robokassa_test_options'])) {
		$GLOBALS['robokassa_test_options'][$key] = $value;
		return true;
	}
	return false;
}

function delete_option($key) {
	unset($GLOBALS['robokassa_test_options'][$key]);
	return true;
}

function add_action() {
}

function add_filter() {
}

function register_activation_hook() {
}

function register_deactivation_hook() {
}

function is_plugin_active($plugin) {
	return $plugin === 'woocommerce/woocommerce.php';
}

function apply_filters($hook, $value) {
	return $value;
}

function wc_format_decimal($value, $decimals = 2) {
	return number_format((float)$value, (int)$decimals, '.', '');
}

function sanitize_text_field($value) {
	return trim(strip_tags((string)$value));
}

function wp_unslash($value) {
	return is_string($value) ? stripslashes($value) : $value;
}

function wc_clean($value) {
	return sanitize_text_field($value);
}

function esc_attr($value) {
	return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function esc_html($value) {
	return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function esc_url($value) {
	return (string)$value;
}

function esc_html__($value) {
	return $value;
}

function esc_attr__($value) {
	return $value;
}

function __($value) {
	return $value;
}

function get_locale() {
	return 'ru_RU';
}

function wp_json_encode($value, $flags = 0) {
	return json_encode($value, $flags);
}

function is_wp_error($value) {
	return $value instanceof WP_Error;
}

function add_query_arg($key, $value = null, $url = '') {
	$args = is_array($key) ? $key : array($key => $value);
	if (is_array($key)) {
		$url = (string)$value;
	}
	$separator = strpos($url, '?') === false ? '?' : '&';
	return $url . $separator . http_build_query($args);
}

function wp_remote_post($url, $args = array()) {
	$GLOBALS['robokassa_test_http_requests'][] = array('method' => 'POST', 'url' => $url, 'args' => $args);
	if (isset($GLOBALS['robokassa_test_http_callback'])) {
		return call_user_func($GLOBALS['robokassa_test_http_callback'], 'POST', $url, $args);
	}
	return new WP_Error('http_not_mocked', 'HTTP request was not mocked.');
}

function wp_remote_get($url, $args = array()) {
	$GLOBALS['robokassa_test_http_requests'][] = array('method' => 'GET', 'url' => $url, 'args' => $args);
	if (isset($GLOBALS['robokassa_test_http_callback'])) {
		return call_user_func($GLOBALS['robokassa_test_http_callback'], 'GET', $url, $args);
	}
	return new WP_Error('http_not_mocked', 'HTTP request was not mocked.');
}

function wp_remote_retrieve_response_code($response) {
	return isset($response['response']['code']) ? $response['response']['code'] : 0;
}

function wp_remote_retrieve_body($response) {
	return isset($response['body']) ? $response['body'] : '';
}

function wp_schedule_single_event($timestamp, $hook, $args = array()) {
	$GLOBALS['robokassa_test_scheduled_events'][] = compact('timestamp', 'hook', 'args');
	return true;
}

function as_schedule_single_action($timestamp, $hook, $args = array(), $group = '', $unique = false) {
	$GLOBALS['robokassa_test_scheduled_events'][] = compact('timestamp', 'hook', 'args', 'group', 'unique');
	return count($GLOBALS['robokassa_test_scheduled_events']);
}

function absint($value) {
	return abs((int)$value);
}

function site_url($path = '') {
	return 'https://shop.example.test' . $path;
}

function plugin_dir_url($file) {
	return 'https://shop.example.test/wp-content/plugins/robokassa/';
}

function plugin_dir_path($file) {
	return dirname($file) . '/';
}

function plugins_url($path = '', $file = '') {
	return 'https://shop.example.test/wp-content/plugins/robokassa/' . ltrim($path, '/');
}

function admin_url($path = '') {
	return 'https://shop.example.test/wp-admin/' . ltrim($path, '/');
}

function wc_get_checkout_url() {
	return 'https://shop.example.test/checkout/';
}

function get_page_link($page_id) {
	return $page_id > 0 ? 'https://shop.example.test/?page_id=' . $page_id : false;
}

function wc_get_order($order_id) {
	if (isset($GLOBALS['robokassa_test_refund_orders'][$order_id])) {
		return $GLOBALS['robokassa_test_refund_orders'][$order_id];
	}
	return isset($GLOBALS['robokassa_test_orders'][$order_id])
		? new WC_Order($order_id)
		: false;
}

function wc_get_order_refunds($order_id) {
	return isset($GLOBALS['robokassa_test_refunds'][$order_id])
		? $GLOBALS['robokassa_test_refunds'][$order_id]
		: array();
}

function current_time($type) {
	return $type === 'mysql' ? '2026-07-23 12:00:00' : time();
}

function plugin_basename($file) {
	return basename($file);
}

function wp_unique_id($prefix = '') {
	static $counter = 0;
	$counter++;
	return $prefix . $counter;
}

function WC() {
	return $GLOBALS['robokassa_test_wc'];
}
