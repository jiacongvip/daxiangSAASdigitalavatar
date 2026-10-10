<?php
/**
 * 点大商城（www.diandashop.com） - 微信公众号小程序商城系统!
 * Copyright © 2020 山东点大网络科技有限公司 保留所有权利
 * =========================================================
 * 版本：V2
 * 授权主体：全网矩阵（厦门）供应链有限公司
 * 授权域名：@@@
 * 授权码：iKIhYWRiGyzXtPXXMjNl
 * ----------------------------------------------
 * 您只能在商业授权范围内使用，不可二次转售、分发、分享、传播
 * 任何企业和个人不得对代码以任何目的任何形式的再发布
 * =========================================================
 */

// 全局中间件定义文件
return [
    \app\middleware\SystemLicenseGuard::class,
    // 全局请求缓存
    // \think\middleware\CheckRequestCache::class,
    // 多语言加载
    // \think\middleware\LoadLangPack::class,
    // Session初始化
    \think\middleware\SessionInit::class,
	//跨域请求
	//\think\middleware\AllowCrossDomain::class
];
