<?php
namespace think;

$websiteEntryDispatch = __DIR__ . '/website_entry_dispatch.php';
if (is_file($websiteEntryDispatch)) {
	require $websiteEntryDispatch;
}
if (function_exists(__NAMESPACE__ . '\\codexServeDigitalAvatarPortal')) {
	codexServeDigitalAvatarPortal();
}
require __DIR__ . '/../vendor/autoload.php';

$appName = function_exists(__NAMESPACE__ . '\\codexResolvePublicAppName') ? codexResolvePublicAppName() : 'admin';

if(!defined('MN')){
	define('MN', $appName === 'website' ? 'website' : 'index');
}

define('ROOT_PATH', __DIR__ . '/');
define('APP_PATH', __DIR__ . '/app');
if((isset($_SERVER['REQUEST_SCHEME']) && 'https' == $_SERVER['REQUEST_SCHEME']) || (isset($_SERVER['SERVER_PORT']) && ('443' == $_SERVER['SERVER_PORT'])) || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && 'https' == $_SERVER['HTTP_X_FORWARDED_PROTO']) || (isset($_SERVER['HTTP_X_CLIENT_SCHEME']) && 'https' == $_SERVER['HTTP_X_CLIENT_SCHEME'])){
	define('PRE_URL','https://'.(isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME']));
}else{
	define('PRE_URL','http://'.(isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME']));
}

$brainSleepPath = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
if (preg_match('#^/(?:index\.php/)?brainsleep(?:/([A-Za-z0-9_-]+))?/?$#', $brainSleepPath, $brainSleepMatches)) {
	$brainSleepPage = $brainSleepMatches[1] ?? 'index';
	$brainSleepAllowedPages = ['index', 'player', 'favorite', 'history', 'device'];
	if (!in_array($brainSleepPage, $brainSleepAllowedPages, true)) {
		$brainSleepPage = 'index';
	}
	$brainSleepQueryString = (string)($_SERVER['QUERY_STRING'] ?? '');
	$brainSleepQuery = [];
	if ($brainSleepQueryString !== '') {
		parse_str($brainSleepQueryString, $brainSleepQuery);
		unset($brainSleepQuery['s']);
	}
	if ($brainSleepPage === 'index' && !empty($brainSleepQuery['audio_id'])) {
		$brainSleepPage = 'player';
	}
	$brainSleepTarget = '/h5/#/brainsleep/' . $brainSleepPage;
	if (!empty($brainSleepQuery)) {
		$brainSleepTarget .= '?' . http_build_query($brainSleepQuery);
	}
	header('Location: ' . $brainSleepTarget, true, 302);
	exit;
}


// 执行HTTP应用并响应
$http = (new App())->http;
$response = $http->name($appName)->run();
$response->send();
$http->end($response);
?>
