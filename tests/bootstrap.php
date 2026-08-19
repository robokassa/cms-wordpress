<?php

require_once __DIR__ . '/Support/WordPressWooCommerceStubs.php';
require_once __DIR__ . '/Support/TestCase.php';

$GLOBALS['robokassa_test_options'] = array();
$GLOBALS['robokassa_test_orders'] = array();
$GLOBALS['robokassa_test_wc'] = new Robokassa_Test_WC_Container();
$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';

require_once dirname(__DIR__) . '/classes/Robokassa/Payment/Helper.php';
require_once dirname(__DIR__) . '/classes/Robokassa/Payment/Util.php';
require_once dirname(__DIR__) . '/classes/Robokassa/Payment/TaxManager.php';
require_once dirname(__DIR__) . '/classes/Robokassa/Payment/PaymentObjectManager.php';
require_once dirname(__DIR__) . '/classes/Robokassa/Payment/AgentManager.php';
require_once dirname(__DIR__) . '/classes/Robokassa/Payment/RobokassaPayAPI.php';
require_once dirname(__DIR__) . '/classes/Robokassa/Payment/RoboDataBase.php';
require_once dirname(__DIR__) . '/classes/Robokassa/Payment/RobokassaSms.php';

chdir(dirname(__DIR__));
require_once dirname(__DIR__) . '/wp_robokassa.php';
require_once dirname(__DIR__) . '/labelsClasses.php';
