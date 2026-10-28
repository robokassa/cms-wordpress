<?php

require dirname(__DIR__, 2) . '/vendor/autoload.php';
require dirname(__DIR__) . '/bootstrap.php';

$socket = $argv[1];
$stateFile = $argv[2];
$barrierFile = $argv[3];
$orderStatus = $argv[4];
$GLOBALS['robokassa_test_options']['robokassa_payment_MerchantLogin'] = 'merchant';
$GLOBALS['robokassa_test_options']['robokassa_payment_shoppass2'] = 'password2';
$initialState = json_decode(file_get_contents($stateFile), true);
$GLOBALS['robokassa_test_orders'][42] = $initialState['order'];
$GLOBALS['robokassa_test_order_cache'] = $GLOBALS['robokassa_test_orders'];

$database = new class($socket, $stateFile) extends wpdb {
	private $connection;
	private $stateFile;

	public function __construct($socket, $stateFile) {
		$this->connection = new PDO('mysql:unix_socket=' . $socket, 'root', '', array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
		$this->stateFile = $stateFile;
	}

	public function prepare($sql, ...$args) {
		return vsprintf($sql, array_map(array($this->connection, 'quote'), $args));
	}

	public function get_var($sql) {
		$result = $this->connection->query($sql)->fetchColumn();
		if (strpos($sql, 'GET_LOCK') !== false && (string)$result === '1') {
			$state = json_decode(file_get_contents($this->stateFile), true);
			$GLOBALS['robokassa_test_orders'][42] = $state['order'];
		}
		return $result;
	}
};

$GLOBALS['robokassa_test_order_save_callback'] = function ($order) use ($stateFile) {
	$state = json_decode(file_get_contents($stateFile), true);
	$state['order'] = $order;
	file_put_contents($stateFile, json_encode($state));
};
$GLOBALS['robokassa_test_payment_complete_callback'] = function () use ($barrierFile) {
	file_put_contents($barrierFile, 'locked');
	usleep(800000);
};
$GLOBALS['robokassa_test_wc']->cart = new class($stateFile) {
	private $stateFile;

	public function __construct($stateFile) {
		$this->stateFile = $stateFile;
	}

	public function is_empty() { return false; }

	public function empty_cart() {
		$state = json_decode(file_get_contents($this->stateFile), true);
		$state['cart_calls']++;
		file_put_contents($this->stateFile, json_encode($state));
	}
};

$request = array('OutSum' => '100.00', 'InvId' => '42');
$request['SignatureValue'] = robokassa_payment_build_callback_signature($request, 'password2');
$start = microtime(true);
$result = (new Robokassa\Payment\ClassicResultNotification($database))->process($request, $orderStatus);
echo json_encode(array('result' => is_wp_error($result) ? $result->get_error_code() : $result, 'elapsed' => microtime(true) - $start));
