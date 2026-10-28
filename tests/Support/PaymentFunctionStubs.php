<?php

namespace Robokassa\Payment {
	function file_get_contents($filename, ...$args) {
		if (isset($GLOBALS['robokassa_test_stream_callback'])) {
			return call_user_func($GLOBALS['robokassa_test_stream_callback'], $filename);
		}
		return \file_get_contents($filename, ...$args);
	}
}

namespace {
	function wcs_order_contains_subscription($order = null) {
		return !empty($GLOBALS['robokassa_test_subscriptions']);
	}

	function wcs_get_subscriptions_for_order($orderId) {
		return $GLOBALS['robokassa_test_subscriptions'] ?? array();
	}

	function wcs_get_subscription($id) {
		foreach (array_merge($GLOBALS['robokassa_test_subscriptions'] ?? array(), $GLOBALS['robokassa_test_renewal_subscriptions'] ?? array()) as $subscription) {
			if ($subscription->get_id() === (int)$id) { return $subscription; }
		}
		return false;
	}

	function wcs_get_subscriptions_for_renewal_order($orderId) {
		return $GLOBALS['robokassa_test_renewal_subscriptions'] ?? array();
	}
}
