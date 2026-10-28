<?php

namespace Robokassa\Payment;

class DebugLogger
{
	private const EVENTS = array(
		'module_status_reported' => array(),
		'result2_verification_failed' => array(),
		'receipt_total_mismatch' => array(),
		'second_receipt_sno_disabled' => array(),
		'second_receipt_status_skipped' => array(),
		'second_receipt_order_missing' => array('order_id'),
		'second_receipt_already_sent' => array('order_id'),
		'second_receipt_marking_invalid' => array('order_id', 'error_count'),
		'second_receipt_prepared' => array('order_id', 'item_count'),
		'second_receipt_transport_error' => array('order_id', 'curl_errno'),
		'second_receipt_response' => array('order_id', 'result_code'),
		'second_receipt_payment_skipped' => array(),
	);

	public function log($event, array $context = array())
	{
		if (!is_string($event) || !isset(self::EVENTS[$event])
			|| !class_exists('WC_Logger') || !class_exists('WC_Log_Handler_DB')) {
			return;
		}

		$safeContext = array('source' => 'robokassa');
		foreach (self::EVENTS[$event] as $key) {
			if (isset($context[$key]) && is_int($context[$key])) {
				$safeContext[$key] = $context[$key];
			}
		}

		try {
			$logger = new \WC_Logger(array(new \WC_Log_Handler_DB()));
			$logger->debug($event, $safeContext);
		} catch (\Throwable $error) {
			// ошибка журнала не должна прерывать обработку оплаты
		}
	}

	public static function removeLegacyLog($path)
	{
		if (!file_exists($path) && !is_link($path)) {
			return true;
		}
		if (@unlink($path)) {
			return true;
		}
		if (is_link($path) || !is_file($path)) {
			return false;
		}

		// при запрете удаления убираем содержимое старого публичного журнала
		$handle = @fopen($path, 'c');
		if ($handle === false) {
			return false;
		}
		$cleaned = false;
		if (flock($handle, LOCK_EX)) {
			clearstatcache(true, $path);
			$opened = fstat($handle);
			$current = @lstat($path);
			// проверяем сам открытый файл, чтобы не обнулить цель подменённой ссылки
			if ($opened && $current && ($current['mode'] & 0170000) === 0100000
				&& $opened['dev'] === $current['dev'] && $opened['ino'] === $current['ino']) {
				$cleaned = ftruncate($handle, 0) && fflush($handle);
			}
			flock($handle, LOCK_UN);
		}
		fclose($handle);

		return $cleaned;
	}

	public static function clearExpiredLogs()
	{
		global $wpdb;
		if (!$wpdb instanceof \wpdb || !class_exists('WC_Log_Handler_DB')) {
			return;
		}

		if (is_callable(array('Automattic\\WooCommerce\\Utilities\\LoggingUtil', 'get_retention_period'))) {
			$days = \Automattic\WooCommerce\Utilities\LoggingUtil::get_retention_period();
		} else {
			$days = (int)apply_filters('woocommerce_logger_days_to_retain_logs', 30);
		}
		$days = $days > 0 ? $days : 30;
		$deleted = $wpdb->query($wpdb->prepare(
			"DELETE FROM {$wpdb->prefix}woocommerce_log WHERE source = %s AND timestamp < %s",
			'robokassa',
			date('Y-m-d H:i:s', strtotime('-' . $days . ' days'))
		));
		if ($deleted && class_exists('WC_Cache_Helper')) {
			\WC_Cache_Helper::get_transient_version('logs-db', true);
		}
	}
}
