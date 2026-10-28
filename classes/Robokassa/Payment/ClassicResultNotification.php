<?php

namespace Robokassa\Payment;

class ClassicResultNotification
{
	const PROCESSED_META_KEY = '_robokassa_result_processed';

	private $database;

	public function __construct(\wpdb $database)
	{
		$this->database = $database;
	}

	public function process(array $request, $orderStatus)
	{
		if (!robokassa_payment_is_valid_callback_signature($request, robokassa_payment_get_callback_password(2))) {
			return new \WP_Error('robokassa_result_bad_signature', 'BAD SIGN', 400);
		}
		$invoiceId = (string)$request['InvId'];
		$orderId = (int)$invoiceId;
		if (!ctype_digit($invoiceId) || $orderId <= 0 || ltrim($invoiceId, '0') !== (string)$orderId) {
			return new \WP_Error('robokassa_result_bad_invoice', 'INVALID INVOICE', 400);
		}

		$lockName = 'robokassa_result_' . md5((defined('DB_NAME') ? DB_NAME : '') . ':' . $this->database->prefix . ':' . $orderId);
		$locked = $this->database->get_var($this->database->prepare('SELECT GET_LOCK(%s, 10)', $lockName));
		if ((string)$locked !== '1') {
			return new \WP_Error('robokassa_result_busy', 'RETRY LATER', 503);
		}

		try {
			// заказ и его метаданные читаем только после получения блокировки
			$dataStore = \WC_Data_Store::load('order');
			if (method_exists($dataStore->get_current_class_name(), 'clear_cached_data')) {
				$dataStore->clear_cached_data(array($orderId));
			}
			wp_cache_delete($orderId, 'posts');
			wp_cache_delete($orderId, 'post_meta');
			try {
				$order = new \WC_Order($orderId);
			} catch (\Exception $error) {
				return new \WP_Error('robokassa_result_order_missing', 'ORDER NOT FOUND', 404);
			}
			if (!$order->get_id()) {
				return new \WP_Error('robokassa_result_order_missing', 'ORDER NOT FOUND', 404);
			}
			$order->read_meta_data(true);
			if ($order->get_meta(self::PROCESSED_META_KEY, true) === 'yes'
				|| $order->get_date_paid('edit') || $order->is_paid()) {
				return 'OK' . $invoiceId;
			}

			if (!robokassa_mark_order_payment_complete($order, $orderStatus)) {
				return new \WP_Error('robokassa_result_payment_failed', 'RETRY LATER', 503);
			}
			$order->update_meta_data(self::PROCESSED_META_KEY, 'yes');
			$order->save();
			$order->add_order_note('Заказ успешно оплачен!');
			robokassa_payment_result_side_effects($order, $invoiceId);

			return 'OK' . $invoiceId;
		} catch (\Throwable $error) {
			return new \WP_Error('robokassa_result_processing_failed', 'RETRY LATER', 503);
		} finally {
			$this->database->get_var($this->database->prepare('SELECT RELEASE_LOCK(%s)', $lockName));
		}
	}
}
