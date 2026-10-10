<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
use think\facade\Route;

Route::get('think', function () {
    return 'hello,ThinkPHP6!';
});

Route::get('hello/:name', 'index/hello');
Route::get('captcha/[:id]', "\\think\\captcha\\CaptchaController@index");

// 移动端页面访问统计，兼容 /?s=/ApiVisit/track 这类前台调用
Route::rule('ApiVisit/track', 'admin.ApiVisit/track', 'GET|POST|OPTIONS');

// 微信开放平台授权回调，兼容旧地址 /?s=/binding/callbackxcx，完成后回到 Vue 绑定页
Route::rule('binding/callbackxcx', 'admin.Binding/callbackxcx', 'GET|POST');
Route::rule('Binding/fastregcallback', 'admin.Binding/fastregcallback', 'GET|POST');

// 脑机睡眠管理手机端短链接，兼容浏览器直接访问 /brainsleep/index
$brainSleepH5Redirect = function ($page = 'index') {
    $allowedPages = ['index', 'player', 'favorite', 'history', 'device'];
    $page = in_array($page, $allowedPages, true) ? $page : 'index';
    $query = request()->get();
    unset($query['s']);
    if ($page === 'index' && !empty($query['audio_id'])) {
        $page = 'player';
    }
    $target = '/h5/#/brainsleep/' . $page;
    if (!empty($query)) {
        $target .= '?' . http_build_query($query);
    }
    return redirect($target, 302);
};
Route::get('brainsleep', $brainSleepH5Redirect);
Route::get('brainsleep/:page', $brainSleepH5Redirect)->pattern(['page' => '[A-Za-z0-9_-]+']);

// 控制台兼容路由（兼容旧版 iframe URL /admin/xxx）
Route::rule('admin/ChannelAdmin/index', 'admin/ChannelAdmin/index');
Route::rule('admin/ChannelSubUser/index', 'admin/ChannelSubUser/index');
Route::rule('admin/WebSystem/index', 'admin/WebSystem/index');

// 官网前台路由
Route::get('sitemap', 'website.Index/sitemap')->ext('xml');
Route::get('robots', 'website.Index/robots')->ext('txt');
Route::get('s/:slug/sitemap', 'website.Index/sitemap')->pattern(['slug' => '[a-z0-9-]+'])->ext('xml');
Route::get('s/:slug/robots', 'website.Index/robots')->pattern(['slug' => '[a-z0-9-]+'])->ext('txt');
Route::get('site/:aid/sitemap', 'website.Index/sitemap')->pattern(['aid' => '\d+'])->ext('xml');
Route::get('site/:aid/robots', 'website.Index/robots')->pattern(['aid' => '\d+'])->ext('txt');
Route::get('website/sitemap', 'website.Index/sitemap')->ext('xml');
Route::get('website/robots', 'website.Index/robots')->ext('txt');
Route::get('cases', 'website.Index/cases');
Route::get('cases.html', 'website.Index/cases');
Route::get('news/:article', 'website.Index/newsDetail')->pattern(['article' => '[A-Za-z0-9-]+(?:\.html)?']);

Route::group('s/:slug', function () {
    Route::get('/', 'website.Index/index');
    Route::get('api/bootstrap', 'website.Api/bootstrap');
    Route::get('about', 'website.Index/about');
    Route::get('contact', 'website.Index/contact');
    Route::post('contact', 'website.Index/contact');
    Route::get('products', 'website.Index/products');
    Route::get('cases', 'website.Index/cases');
    Route::get('cases.html', 'website.Index/cases');
    Route::get('news/:article', 'website.Index/newsDetail');
    Route::get('news', 'website.Index/news');
    Route::get('detail', 'website.Index/detail');
    Route::get('page/:key', 'website.Index/page');
})->pattern(['slug' => '[a-z0-9-]+', 'key' => '[A-Za-z0-9_-]+', 'article' => '[A-Za-z0-9-]+(?:\.html)?']);

Route::group('site/:aid', function () {
    Route::get('/', 'website.Index/index');
    Route::get('api/bootstrap', 'website.Api/bootstrap');
    Route::get('about', 'website.Index/about');
    Route::get('contact', 'website.Index/contact');
    Route::post('contact', 'website.Index/contact');
    Route::get('products', 'website.Index/products');
    Route::get('cases', 'website.Index/cases');
    Route::get('cases.html', 'website.Index/cases');
    Route::get('news/:article', 'website.Index/newsDetail');
    Route::get('news', 'website.Index/news');
    Route::get('detail', 'website.Index/detail');
    Route::get('page/:key', 'website.Index/page');
})->pattern(['aid' => '\d+', 'key' => '[A-Za-z0-9_-]+', 'article' => '[A-Za-z0-9-]+(?:\.html)?']);

// 官网前台路由（通过GET参数访问）
Route::group('website', function () {
    Route::get('/', 'website.Index/index');
    Route::get('api/bootstrap', 'website.Api/bootstrap');
    Route::get('about', 'website.Index/about');
    Route::get('contact', 'website.Index/contact');
    Route::post('contact', 'website.Index/contact');
    Route::get('products', 'website.Index/products');
    Route::get('cases', 'website.Index/cases');
    Route::get('cases.html', 'website.Index/cases');
    Route::get('news/:article', 'website.Index/newsDetail');
    Route::get('news', 'website.Index/news');
    Route::get('detail', 'website.Index/detail');
    Route::get('page/:key', 'website.Index/page');
})->pattern(['key' => '[A-Za-z0-9_-]+', 'article' => '[A-Za-z0-9-]+(?:\.html)?']);

// 客户端授权中心公开路由
Route::group('clientauth', function () {
    Route::rule('login', 'adminapi.ClientAuthGateway/login', 'POST|OPTIONS');
    Route::rule('activate', 'adminapi.ClientAuthGateway/activate', 'POST|OPTIONS');
    Route::rule('refresh', 'adminapi.ClientAuthGateway/refresh', 'POST|OPTIONS');
    Route::rule('logout', 'adminapi.ClientAuthGateway/logout', 'POST|OPTIONS');
    Route::rule('releaseDevice', 'adminapi.ClientAuthGateway/releaseDevice', 'POST|OPTIONS');
    Route::rule('heartbeat', 'adminapi.ClientAuthGateway/heartbeat', 'POST|OPTIONS');
    Route::rule('me', 'adminapi.ClientAuthGateway/me', 'GET|POST|OPTIONS');
})->allowCrossDomain();

// 授权中心安装器公开路由，供客户 install.php 和运行期 SDK 调用
Route::group('licensecenter', function () {
    Route::rule('verify-install', function () {
        return (new \app\plugin\licensecenter\controller\ApiLicenseCenter())->verifyInstall();
    }, 'POST|OPTIONS');
    Route::rule('confirm-install', function () {
        return (new \app\plugin\licensecenter\controller\ApiLicenseCenter())->confirmInstall();
    }, 'POST|OPTIONS');
    Route::rule('check', function () {
        return (new \app\plugin\licensecenter\controller\ApiLicenseCenter())->check();
    }, 'POST|OPTIONS');
    Route::rule('license/verifyInstall', function () {
        return (new \app\plugin\licensecenter\controller\ApiLicenseCenter())->verifyInstall();
    }, 'POST|OPTIONS');
    Route::rule('license/confirmInstall', function () {
        return (new \app\plugin\licensecenter\controller\ApiLicenseCenter())->confirmInstall();
    }, 'POST|OPTIONS');
    Route::rule('license/check', function () {
        return (new \app\plugin\licensecenter\controller\ApiLicenseCenter())->check();
    }, 'POST|OPTIONS');
})->allowCrossDomain();

Route::group('api/licensecenter', function () {
    Route::rule('license/verifyInstall', function () {
        return (new \app\plugin\licensecenter\controller\ApiLicenseCenter())->verifyInstall();
    }, 'POST|OPTIONS');
    Route::rule('license/confirmInstall', function () {
        return (new \app\plugin\licensecenter\controller\ApiLicenseCenter())->confirmInstall();
    }, 'POST|OPTIONS');
    Route::rule('license/check', function () {
        return (new \app\plugin\licensecenter\controller\ApiLicenseCenter())->check();
    }, 'POST|OPTIONS');
})->allowCrossDomain();

// GEO 本地发布助手公开路由
Route::group('geoclient', function () {
    Route::rule('pull', 'adminapi.GeoClientGateway/pull', 'GET|POST|OPTIONS');
    Route::rule('ack', 'adminapi.GeoClientGateway/ack', 'POST|OPTIONS');
    Route::rule('progress', 'adminapi.GeoClientGateway/progress', 'POST|OPTIONS');
    Route::rule('finish', 'adminapi.GeoClientGateway/finish', 'POST|OPTIONS');
    Route::rule('createGenerateTask', 'adminapi.GeoClientGateway/createGenerateTask', 'POST|OPTIONS');
})->allowCrossDomain();

// GEO 对外公开数据报告页（可独立发给客户查看，无需登录）
// 访问 URL：https://你的域名/georeport?company=xinzhengcaiwu 或 /georeport?pid=项目ID
Route::rule('georeport/data', function () {
    return (new \app\plugin\geooptimizer\controller\GeoReportPublic())->data();
}, 'GET|POST|OPTIONS')->allowCrossDomain();
Route::rule('georeport', function () {
    return (new \app\plugin\geooptimizer\controller\GeoReportPublic())->view();
}, 'GET|OPTIONS')->allowCrossDomain();

// 一键追爆本地桌面拉取结果公开路由
Route::group('zhuibaoclient', function () {
    Route::rule('pull', 'adminapi.ZhuibaoClientGateway/pull', 'GET|POST|OPTIONS');
})->allowCrossDomain();

// 企业微信事件回调公开路由（仅注册转发，业务逻辑在插件 app\plugin\qywx\controller\ApiQywx 内）
// 回调 URL：https://你的域名/qywx/callback?aid=站点ID
Route::rule('qywx/callback', function () {
    return (new \app\plugin\qywx\controller\ApiQywx())->callback();
}, 'GET|POST|OPTIONS')->allowCrossDomain();

// 麦芽田聚合配送状态回调公开路由（业务逻辑在插件 app\plugin\maiyatian\controller\ApiMytNotify 内）
// 回调 URL：https://你的域名/maiyatian/callback
Route::rule('maiyatian/callback', function () {
    return (new \app\plugin\maiyatian\controller\ApiMytNotify())->index();
}, 'GET|POST|OPTIONS')->allowCrossDomain();

// 聚水潭库存等事件回调公开路由，站点通过 aid 定位配置
Route::rule('jushuitan/webhook/:route_key', function () {
    return (new \app\plugin\jushuitan\controller\JushuitanWebhook())->index();
}, 'GET|POST|OPTIONS')->allowCrossDomain();
Route::rule('jushuitan/webhook', function () {
    return (new \app\plugin\jushuitan\controller\JushuitanWebhook())->index();
}, 'GET|POST|OPTIONS')->allowCrossDomain();
Route::rule('jushuitan/auth/:route_key', function () {
    return (new \app\plugin\jushuitan\controller\JushuitanAuth())->index();
}, 'GET|POST|OPTIONS')->allowCrossDomain();
Route::rule('jushuitan/auth', function () {
    return (new \app\plugin\jushuitan\controller\JushuitanAuth())->index();
}, 'GET|POST|OPTIONS')->allowCrossDomain();

// 法大大电子签异步回调公开路由（业务逻辑在插件 app\plugin\fadada\controller\NotifyFadada 内）
// 回调 URL：https://你的域名/fadada/callback?aid=站点ID
Route::rule('fadada/callback', function () {
    return (new \app\plugin\fadada\controller\NotifyFadada())->index();
}, 'GET|POST|OPTIONS')->allowCrossDomain();
