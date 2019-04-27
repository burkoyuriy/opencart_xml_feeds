<?php

// Configuration
require_once('../admin/config.php');

define('VERSION', '2.3.0.2');

require_once(DIR_SYSTEM . 'startup.php');
require_once(DIR_SYSTEM . 'library/cart/currency.php');
require_once(DIR_SYSTEM . 'library/cart/user.php');
require_once(DIR_SYSTEM . 'library/cart/weight.php');
require_once(DIR_SYSTEM . 'library/cart/length.php');
require_once(DIR_SYSTEM . 'library/url.php');
require_once(DIR_CATALOG . 'controller/startup/seo_url.php');


// Registry
$registry = new Registry();

// Loader
$loader = new Loader($registry);
$registry->set('load', $loader);

// Config
$config = new Config();
$registry->set('config', $config);

// Database
$db = new DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
$registry->set('db', $db);

// Store
if (isset($_SERVER['HTTPS']) && (($_SERVER['HTTPS'] == 'on') || ($_SERVER['HTTPS'] == '1'))) {
	$store_query = $db->query("SELECT * FROM " . DB_PREFIX . "store WHERE REPLACE(`ssl`, 'www.', '') = '" . $db->escape('https://' . str_replace('www.', '', $_SERVER['HTTP_HOST']) . rtrim(dirname($_SERVER['PHP_SELF']), '/.\\') . '/') . "'");
} else {
	$store_query = $db->query("SELECT * FROM " . DB_PREFIX . "store WHERE REPLACE(`url`, 'www.', '') = '" . $db->escape('http://' . str_replace('www.', '', $_SERVER['HTTP_HOST']) . rtrim(dirname($_SERVER['PHP_SELF']), '/.\\') . '/') . "'");
}

if ($store_query->num_rows) {
	$config->set('config_store_id', $store_query->row['store_id']);
} else {
	$config->set('config_store_id', 0);
}

// Settings
$query = $db->query("SELECT * FROM `" . DB_PREFIX . "setting` WHERE store_id = '0' OR store_id = '" . (int)$config->get('config_store_id') . "' ORDER BY store_id ASC");

foreach ($query->rows as $result) {
	if (!$result['serialized']) {
		$config->set($result['key'], $result['value']);
	} else {
		$config->set($result['key'], json_decode($result['value'], true));
	}
}

if (!$store_query->num_rows) {
	$config->set('config_url', HTTP_SERVER);
	$config->set('config_ssl', HTTPS_SERVER);
}

// url
$registry->set('url', new Url(str_replace('admin/', '', $config->get('config_ssl')), str_replace('admin/', '', $config->get('config_ssl'))));

// Log
$log = new Log($config->get('config_error_filename'));
$registry->set('log', $log);


// Error Handler
function error_handler($errno, $errstr, $errfile, $errline) {
	global $config, $log;

	if (0 === error_reporting()) return TRUE;
	switch ($errno) {
		case E_NOTICE:
		case E_USER_NOTICE:
			$error = 'Notice';
			break;
		case E_WARNING:
		case E_USER_WARNING:
			$error = 'Warning';
			break;
		case E_ERROR:
		case E_USER_ERROR:
			$error = 'Fatal Error';
			break;
		default:
			$error = 'Unknown';
			break;
	}

	if ($config->get('config_error_display')) {
		echo '<b>' . $error . '</b>: ' . $errstr . ' in <b>' . $errfile . '</b> on line <b>' . $errline . '</b>';
	}

	if ($config->get('config_error_log')) {
		$log->write('PHP ' . $error . ':  ' . $errstr . ' in ' . $errfile . ' on line ' . $errline);
	}

	return TRUE;
}

// Error Handler
set_error_handler('error_handler');

// Request
$request = new Request();
$registry->set('request', $request);

// Response
$response = new Response();
$response->addHeader('Content-Type: text/html; charset=utf-8');
$registry->set('response', $response);

// Session
$registry->set('session', new Session());

// Cache
$registry->set('cache', new Cache('file'));

// Document
$registry->set('document', new Document());

// Language
$languages = array();

$query = $db->query("SELECT * FROM " . DB_PREFIX . "language");

foreach ($query->rows as $result) {
	$languages[$result['code']] = array(
		'language_id'	=> $result['language_id'],
		'name'		=> $result['name'],
		'code'		=> $result['code'],
		'locale'	=> $result['locale'],
		'directory'	=> $result['directory']
	);
}

$config->set('config_language_id', $languages[$config->get('config_admin_language')]['language_id']);

// Language
$language = new Language($languages[$config->get('config_admin_language')]['directory']);
$language->load($languages[$config->get('config_admin_language')]['directory']);
$registry->set('language', $language);

// Currency
$registry->set('currency', new Cart\Currency($registry));

// Weight
$registry->set('weight', new Cart\Weight($registry));

// Length
$registry->set('length', new Cart\Length($registry));

// User
$registry->set('user', new Cart\User($registry));

//OpenBay Pro
$registry->set('openbay', new Openbay($registry));

// Event
$event = new Event($registry);
$registry->set('event', $event);

// Front Controller
$controller = new Front($registry);




// Router
if (isset($request->get['target'])) {
	switch ($request->get['target']) {
		case 'priceua':
			$action = new Action('extension/module/allxml/get_from_price_ua');
		break;
		
		case 'ecatalog':
			$action = new Action('extension/module/allxml/get_from_ecatalog');
		break;

		case 'hotline':
			$action = new Action('extension/module/allxml/get_from_hotline');
		break;

		default:
			echo "not set required param\n";
	}
} else {
	echo "no access\n<br>";
}

// Dispatch
if (isset($action)) {
	$controller->dispatch($action, new Action('error/not_found'));
}

// Output
$response->output();
?>
