<?php
namespace think;

require __DIR__ . '/../vendor/autoload.php';

if(!defined('MN')){
	define('MN','website');
}

define('ROOT_PATH', __DIR__ . '/../');
define('APP_PATH', __DIR__ . '/../app/');
if((isset($_SERVER['REQUEST_SCHEME']) && 'https' == $_SERVER['REQUEST_SCHEME']) || (isset($_SERVER['SERVER_PORT']) && ('443' == $_SERVER['SERVER_PORT'])) || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && 'https' == $_SERVER['HTTP_X_FORWARDED_PROTO']) || (isset($_SERVER['HTTP_X_CLIENT_SCHEME']) && 'https' == $_SERVER['HTTP_X_CLIENT_SCHEME'])){
	define('PRE_URL','https://'.(isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME']));
}else{
	define('PRE_URL','http://'.(isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME']));
}

// 执行HTTP应用并响应
$http = (new App(__DIR__ . '/../'))->http;
$response = $http->name('website')->run();
$response->send();
$http->end($response);
?>
