<?php
/**
 * Vue前端 API 入口文件
 * 访问地址：/api.php/controller/method
 */

namespace think;

// 定义应用目录
define('APP_PATH', __DIR__ . '/../app/');
define('ROOT_PATH', __DIR__ . '/../');

// 定义PRE_URL常量
if((isset($_SERVER['REQUEST_SCHEME']) && 'https' == $_SERVER['REQUEST_SCHEME']) || (isset($_SERVER['SERVER_PORT']) && ('443' == $_SERVER['SERVER_PORT'])) || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && 'https' == $_SERVER['HTTP_X_FORWARDED_PROTO']) || (isset($_SERVER['HTTP_X_CLIENT_SCHEME']) && 'https' == $_SERVER['HTTP_X_CLIENT_SCHEME'])){
	define('PRE_URL','https://'.(isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME']));
}else{
	define('PRE_URL','http://'.(isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME']));
}

// 加载框架引导文件
require __DIR__ . '/../vendor/autoload.php';

// 执行HTTP应用并响应
$http = (new App())->http;

$response = $http->name('adminapi')->run();

$response->send();

$http->end($response);

