<?php
// +----------------------------------------------------------------------
// | 应用设置
// +----------------------------------------------------------------------

return [
    // 应用地址
	'auto_multi_app'        => true,
    'app_host'         => env('app.host', ''),
	'app_debug'        => true,
	'APP_TRACE'        => true,
    // 应用的命名空间
    'app_namespace'    => '',
    // 是否启用路由
    'with_route'       => true,
    // 默认应用
    'default_app'      => 'home',
    // 默认时区
    'default_timezone' => 'Asia/Shanghai',

    // 应用映射（自动多应用模式有效）
    'app_map'          => [
        'login' => 'admin',  // 将login路径映射到admin应用
        'admin' => 'admin',  // 管理后台
        'business' => 'admin', // 商户后台也使用admin应用
        'shop' => 'home',    // 购物商城
    ],
    // 域名绑定（自动多应用模式有效）
    'domain_bind'      => [
// 	    'admin.maqiang2.com'=>'admin',
//         'api.maqiang2.com'=>'api',
// 		'maqiang2.com/'=>'home',
//         'uapi.qingtingshou.com'=>'uniapi',
//         'www.maqiangHome.com'=>'home'
	   ],
    // 禁止URL访问的应用列表（自动多应用模式有效）
    'deny_app_list'    => [],

   'upload_type' => 'jpg,jpeg,png,bmp,gif,webp,svg,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,zip,rar,7z,csv,json,pem,crt,cer,p12,pfx,apk,wgt,mp3,m4a,wav,aac,ogg,flac,opus,amr,wma,mp4,mov,m4v,avi,mkv,webm,ogv,wmv,flv,3gp,mpeg,mpg,glb,gltf,bin',
'upload_mime' => 'image/jpeg,image/png,image/bmp,image/gif,image/webp,image/svg+xml,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation,text/plain,text/csv,application/json,audio/mpeg,audio/mp3,audio/m4a,audio/wav,audio/x-wav,audio/aac,audio/ogg,audio/flac,audio/opus,audio/amr,audio/x-ms-wma,video/mp4,video/quicktime,video/x-msvideo,video/x-matroska,video/webm,video/ogg,video/x-ms-wmv,video/x-flv,video/3gpp,video/mpeg,model/gltf-binary,model/gltf+json,application/octet-stream',
    // 异常页面的模板文件
    'exception_tmpl'   => app()->getThinkPath() . 'tpl/think_exception.tpl',
    //  'err_tmpl'=> app()->getThinkPath() . 'tpl/dispatch_stop_tmpl.tpl',

    // 错误显示信息,非调试模式有效
    'error_message'    => '页面错误！请稍后再试～',
    // 显示错误信息
    'show_error_msg'   => true,
    
    // 临时添加：强制显示错误
    'display_errors'   => true,
    'error_reporting'  => E_ALL,
];
