<?php

use think\facade\Route;

Route::get('/', 'Index/index');
Route::get('about', 'Index/about');
Route::get('contact', 'Index/contact');
Route::post('contact', 'Index/contact');
Route::get('products', 'Index/products');
Route::get('cases', 'Index/cases');
Route::get('cases.html', 'Index/cases');
Route::get('news/:article', 'Index/newsDetail')->pattern(['article' => '[A-Za-z0-9-]+(?:\.html)?']);
Route::get('news', 'Index/news');
Route::get('detail', 'Index/detail');
Route::get('page/:key', 'Index/page')->pattern(['key' => '[A-Za-z0-9_-]+']);
Route::get('sitemap', 'Index/sitemap')->ext('xml');
Route::get('robots', 'Index/robots')->ext('txt');
Route::get('s/:slug/sitemap', 'Index/sitemap')->pattern(['slug' => '[a-z0-9-]+'])->ext('xml');
Route::get('s/:slug/robots', 'Index/robots')->pattern(['slug' => '[a-z0-9-]+'])->ext('txt');
Route::get('site/:aid/sitemap', 'Index/sitemap')->pattern(['aid' => '\d+'])->ext('xml');
Route::get('site/:aid/robots', 'Index/robots')->pattern(['aid' => '\d+'])->ext('txt');
Route::get('website/sitemap', 'Index/sitemap')->ext('xml');
Route::get('website/robots', 'Index/robots')->ext('txt');

Route::group('s/:slug', function () {
    Route::get('/', 'Index/index');
    Route::get('api/bootstrap', 'Api/bootstrap');
    Route::get('about', 'Index/about');
    Route::get('contact', 'Index/contact');
    Route::post('contact', 'Index/contact');
    Route::get('products', 'Index/products');
    Route::get('cases', 'Index/cases');
    Route::get('cases.html', 'Index/cases');
    Route::get('news/:article', 'Index/newsDetail');
    Route::get('news', 'Index/news');
    Route::get('detail', 'Index/detail');
    Route::get('page/:key', 'Index/page');
})->pattern([
    'slug' => '[a-z0-9-]+',
    'key' => '[A-Za-z0-9_-]+',
    'article' => '[A-Za-z0-9-]+(?:\.html)?',
]);

Route::group('site/:aid', function () {
    Route::get('/', 'Index/index');
    Route::get('api/bootstrap', 'Api/bootstrap');
    Route::get('about', 'Index/about');
    Route::get('contact', 'Index/contact');
    Route::post('contact', 'Index/contact');
    Route::get('products', 'Index/products');
    Route::get('cases', 'Index/cases');
    Route::get('cases.html', 'Index/cases');
    Route::get('news/:article', 'Index/newsDetail');
    Route::get('news', 'Index/news');
    Route::get('detail', 'Index/detail');
    Route::get('page/:key', 'Index/page');
})->pattern([
    'aid' => '\d+',
    'key' => '[A-Za-z0-9_-]+',
    'article' => '[A-Za-z0-9-]+(?:\.html)?',
]);

Route::group('website', function () {
    Route::get('/', 'Index/index');
    Route::get('api/bootstrap', 'Api/bootstrap');
    Route::get('about', 'Index/about');
    Route::get('contact', 'Index/contact');
    Route::post('contact', 'Index/contact');
    Route::get('products', 'Index/products');
    Route::get('cases', 'Index/cases');
    Route::get('cases.html', 'Index/cases');
    Route::get('news/:article', 'Index/newsDetail');
    Route::get('news', 'Index/news');
    Route::get('detail', 'Index/detail');
    Route::get('page/:key', 'Index/page');
})->pattern([
    'key' => '[A-Za-z0-9_-]+',
    'article' => '[A-Za-z0-9-]+(?:\.html)?',
]);

Route::group('portalapi/zhuibao', function () {
    Route::post('getMemberLoginConfig', 'PortalApiZhuibaoAgent/getMemberLoginConfig');
    Route::post('sendLoginSms', 'PortalApiZhuibaoAgent/sendLoginSms');
    Route::post('submitMemberLogin', 'PortalApiZhuibaoAgent/submitMemberLogin');
    Route::post('submitMemberRegister', 'PortalApiZhuibaoAgent/submitMemberRegister');
    Route::post('resetMemberPasswordBySms', 'PortalApiZhuibaoAgent/resetMemberPasswordBySms');
    Route::post('logoutMemberSession', 'PortalApiZhuibaoAgent/logoutMemberSession');
    Route::post('getPortalProfile', 'PortalApiZhuibaoAgent/getPortalProfile');
    Route::post('getPackageList', 'PortalApiZhuibaoAgent/getPackageList');
    Route::post('createPackageOrder', 'PortalApiZhuibaoAgent/createPackageOrder');
    Route::post('getMemberAvatarProfiles', 'PortalApiZhuibaoAgent/getMemberAvatarProfiles');
    Route::post('getMemberVoiceProfiles', 'PortalApiZhuibaoAgent/getMemberVoiceProfiles');
    Route::post('uploadMemberMedia', 'PortalApiZhuibaoAgent/uploadMemberMedia');
    Route::post('getMemberMediaList', 'PortalApiZhuibaoAgent/getMemberMediaList');
    Route::post('deleteMemberMedia', 'PortalApiZhuibaoAgent/deleteMemberMedia');
    Route::post('saveMemberAvatarProfile', 'PortalApiZhuibaoAgent/saveMemberAvatarProfile');
    Route::post('saveMemberVoiceProfile', 'PortalApiZhuibaoAgent/saveMemberVoiceProfile');
    Route::post('deleteMemberAvatarProfile', 'PortalApiZhuibaoAgent/deleteMemberAvatarProfile');
    Route::post('deleteMemberVoiceProfile', 'PortalApiZhuibaoAgent/deleteMemberVoiceProfile');
    Route::post('cloneMemberAvatarProfile', 'PortalApiZhuibaoAgent/cloneMemberAvatarProfile');
    Route::post('cloneMemberVoiceProfile', 'PortalApiZhuibaoAgent/cloneMemberVoiceProfile');
    Route::post('updatePortalProfile', 'PortalApiZhuibaoAgent/updatePortalProfile');
    Route::post('updatePortalPassword', 'PortalApiZhuibaoAgent/updatePortalPassword');
    Route::post('getWorkbenchDetail', 'PortalApiZhuibaoAgent/getWorkbenchDetail');
    Route::post('getAgentList', 'PortalApiZhuibaoAgent/getAgentList');
    Route::post('getRunningHubWorkflowDetail', 'PortalApiZhuibaoAgent/getRunningHubWorkflowDetail');
    Route::post('runRunningHubWorkflow', 'PortalApiZhuibaoAgent/runRunningHubWorkflow');
    Route::post('getRunningHubTaskStatus', 'PortalApiZhuibaoAgent/getRunningHubTaskStatus');
    Route::post('getGraphicWorkbenchDetail', 'PortalApiZhuibaoAgent/getGraphicWorkbenchDetail');
    Route::post('getImageStudioWorkbenchDetail', 'PortalApiZhuibaoAgent/getImageStudioWorkbenchDetail');
    Route::post('getLightingDesignWorkbench', 'PortalApiZhuibaoAgent/getLightingDesignWorkbench');
    Route::post('getImageStudioConversationList', 'PortalApiZhuibaoAgent/getImageStudioConversationList');
    Route::post('getImageStudioConversationDetail', 'PortalApiZhuibaoAgent/getImageStudioConversationDetail');
    Route::post('getImageSetWorkbenchDetail', 'PortalApiZhuibaoAgent/getImageSetWorkbenchDetail');
    Route::post('planImageSetCreative', 'PortalApiZhuibaoAgent/planImageSetCreative');
    Route::post('createTask', 'PortalApiZhuibaoAgent/createTask');
    Route::post('createGraphicTask', 'PortalApiZhuibaoAgent/createGraphicTask');
    Route::post('submitImageStudioTask', 'PortalApiZhuibaoAgent/submitImageStudioTask');
    Route::post('submitLightingDesignTask', 'PortalApiZhuibaoAgent/submitLightingDesignTask');
    Route::post('submitImageSetTask', 'PortalApiZhuibaoAgent/submitImageSetTask');
    Route::post('getTaskList', 'PortalApiZhuibaoAgent/getTaskList');
    Route::post('getGraphicTaskList', 'PortalApiZhuibaoAgent/getGraphicTaskList');
    Route::post('getImageStudioTaskList', 'PortalApiZhuibaoAgent/getImageStudioTaskList');
    Route::post('getLightingDesignTaskList', 'PortalApiZhuibaoAgent/getLightingDesignTaskList');
    Route::post('getImageSetTaskList', 'PortalApiZhuibaoAgent/getImageSetTaskList');
    Route::post('getTaskDetail', 'PortalApiZhuibaoAgent/getTaskDetail');
    Route::post('getGraphicTaskDetail', 'PortalApiZhuibaoAgent/getGraphicTaskDetail');
    Route::post('getImageStudioTaskDetail', 'PortalApiZhuibaoAgent/getImageStudioTaskDetail');
    Route::post('getLightingDesignTaskDetail', 'PortalApiZhuibaoAgent/getLightingDesignTaskDetail');
    Route::post('getImageSetTaskDetail', 'PortalApiZhuibaoAgent/getImageSetTaskDetail');
    Route::post('retryImageStudioTask', 'PortalApiZhuibaoAgent/retryImageStudioTask');
    Route::post('retryLightingDesignTask', 'PortalApiZhuibaoAgent/retryLightingDesignTask');
    Route::post('updateLightingDesignTask', 'PortalApiZhuibaoAgent/updateLightingDesignTask');
    Route::post('deleteLightingDesignTask', 'PortalApiZhuibaoAgent/deleteLightingDesignTask');
    Route::post('retryImageSetTask', 'PortalApiZhuibaoAgent/retryImageSetTask');
    Route::post('saveGraphicTaskPayload', 'PortalApiZhuibaoAgent/saveGraphicTaskPayload');
    Route::post('generateGraphicOutline', 'PortalApiZhuibaoAgent/generateGraphicOutline');
    Route::post('generateGraphicContent', 'PortalApiZhuibaoAgent/generateGraphicContent');
    Route::post('saveGraphicPageContents', 'PortalApiZhuibaoAgent/saveGraphicPageContents');
    Route::post('saveGraphicPublishContent', 'PortalApiZhuibaoAgent/saveGraphicPublishContent');
    Route::post('generateGraphicImages', 'PortalApiZhuibaoAgent/generateGraphicImages');
    Route::post('regenerateGraphicPage', 'PortalApiZhuibaoAgent/regenerateGraphicPage');
    Route::post('retryGraphicFailedPages', 'PortalApiZhuibaoAgent/retryGraphicFailedPages');
    Route::post('generateTaskCode', 'PortalApiZhuibaoAgent/generateTaskCode');
    Route::post('runTaskStep', 'PortalApiZhuibaoAgent/runTaskStep');
});

Route::group('portalapi/geo', function () {
    Route::rule('getLoginConfig', 'PortalApiGeoOptimizer/getLoginConfig', 'GET|POST|OPTIONS');
    Route::rule('sendRegisterSms', 'PortalApiGeoOptimizer/sendRegisterSms', 'GET|POST|OPTIONS');
    Route::rule('submitLogin', 'PortalApiGeoOptimizer/submitLogin', 'GET|POST|OPTIONS');
    Route::rule('submitRegister', 'PortalApiGeoOptimizer/submitRegister', 'GET|POST|OPTIONS');
    Route::rule('logout', 'PortalApiGeoOptimizer/logout', 'GET|POST|OPTIONS');
    Route::rule('getPortalProfile', 'PortalApiGeoOptimizer/getPortalProfile', 'GET|POST|OPTIONS');
    Route::rule('uploadMemberMedia', 'PortalApiGeoOptimizer/uploadMemberMedia', 'GET|POST|OPTIONS');
    Route::rule('getMemberMediaList', 'PortalApiGeoOptimizer/getMemberMediaList', 'GET|POST|OPTIONS');
    Route::rule('deleteMemberMedia', 'PortalApiGeoOptimizer/deleteMemberMedia', 'GET|POST|OPTIONS');
    Route::rule('getWorkbenchDetail', 'PortalApiGeoOptimizer/getWorkbenchDetail', 'GET|POST|OPTIONS');
    Route::rule('getProjectList', 'PortalApiGeoOptimizer/getProjectList', 'GET|POST|OPTIONS');
    Route::rule('saveProject', 'PortalApiGeoOptimizer/saveProject', 'GET|POST|OPTIONS');
    Route::rule('getProjectDetail', 'PortalApiGeoOptimizer/getProjectDetail', 'GET|POST|OPTIONS');
    Route::rule('getAssetBindList', 'PortalApiGeoOptimizer/getAssetBindList', 'GET|POST|OPTIONS');
    Route::rule('saveAssetBind', 'PortalApiGeoOptimizer/saveAssetBind', 'GET|POST|OPTIONS');
    Route::rule('deleteAssetBind', 'PortalApiGeoOptimizer/deleteAssetBind', 'GET|POST|OPTIONS');
    Route::rule('getBrandProfile', 'PortalApiGeoOptimizer/getBrandProfile', 'GET|POST|OPTIONS');
    Route::rule('saveBrandProfile', 'PortalApiGeoOptimizer/saveBrandProfile', 'GET|POST|OPTIONS');
    Route::rule('getTaskList', 'PortalApiGeoOptimizer/getTaskList', 'GET|POST|OPTIONS');
    Route::rule('saveTask', 'PortalApiGeoOptimizer/saveTask', 'GET|POST|OPTIONS');
    Route::rule('deleteTask', 'PortalApiGeoOptimizer/deleteTask', 'GET|POST|OPTIONS');
    Route::rule('requeueTask', 'PortalApiGeoOptimizer/requeueTask', 'GET|POST|OPTIONS');
    Route::rule('pauseTask', 'PortalApiGeoOptimizer/pauseTask', 'GET|POST|OPTIONS');
    Route::rule('resumeTask', 'PortalApiGeoOptimizer/resumeTask', 'GET|POST|OPTIONS');
    Route::rule('getTaskDetail', 'PortalApiGeoOptimizer/getTaskDetail', 'GET|POST|OPTIONS');
    Route::rule('ensureTaskDispatchTaskCode', 'PortalApiGeoOptimizer/ensureTaskDispatchTaskCode', 'GET|POST|OPTIONS');
    Route::rule('getKeywordSeedList', 'PortalApiGeoOptimizer/getKeywordSeedList', 'GET|POST|OPTIONS');
    Route::rule('saveKeywordSeed', 'PortalApiGeoOptimizer/saveKeywordSeed', 'GET|POST|OPTIONS');
    Route::rule('deleteKeywordSeed', 'PortalApiGeoOptimizer/deleteKeywordSeed', 'GET|POST|OPTIONS');
    Route::rule('getKeywordDistillList', 'PortalApiGeoOptimizer/getKeywordDistillList', 'GET|POST|OPTIONS');
    Route::rule('generateKeywordDistillByAi', 'PortalApiGeoOptimizer/generateKeywordDistillByAi', 'GET|POST|OPTIONS');
    Route::rule('getKeywordExpansionSuggestions', 'PortalApiGeoOptimizer/getKeywordExpansionSuggestions', 'GET|POST|OPTIONS');
    Route::rule('getKeywordTemplateOptions', 'PortalApiGeoOptimizer/getKeywordTemplateOptions', 'GET|POST|OPTIONS');
    Route::rule('getGenerateResultList', 'PortalApiGeoOptimizer/getGenerateResultList', 'GET|POST|OPTIONS');
    Route::rule('getGenerateResultDetail', 'PortalApiGeoOptimizer/getGenerateResultDetail', 'GET|POST|OPTIONS');
    Route::rule('saveGenerateResult', 'PortalApiGeoOptimizer/saveGenerateResult', 'GET|POST|OPTIONS');
    Route::rule('getProductAccess', 'PortalApiGeoOptimizer/getProductAccess', 'GET|POST|OPTIONS');
    Route::rule('getCreditSummary', 'PortalApiGeoOptimizer/getCreditSummary', 'GET|POST|OPTIONS');
    Route::rule('getAvailablePackages', 'PortalApiGeoOptimizer/getAvailablePackages', 'GET|POST|OPTIONS');
    Route::rule('getMyCreditLog', 'PortalApiGeoOptimizer/getMyCreditLog', 'GET|POST|OPTIONS');
    Route::rule('getMediaCatalog', 'PortalApiGeoOptimizer/getMediaCatalog', 'GET|POST|OPTIONS');
    Route::rule('getMediaCatalogFilters', 'PortalApiGeoOptimizer/getMediaCatalogFilters', 'GET|POST|OPTIONS');
    Route::rule('getMediaContact', 'PortalApiGeoOptimizer/getMediaContact', 'GET|POST|OPTIONS');
});

// AI数字人电脑端 Portal API，使用会员平台登录态和 Hash 路由。
Route::group('portalapi/digitalavatar', function () {
    Route::rule('getLoginConfig', 'PortalApiDigitalAvatar/getLoginConfig', 'GET|POST|OPTIONS');
    Route::rule('getLoginCaptcha', 'PortalApiDigitalAvatar/getLoginCaptcha', 'GET|POST|OPTIONS');
    Route::rule('sendLoginSms', 'PortalApiDigitalAvatar/sendLoginSms', 'GET|POST|OPTIONS');
    Route::rule('submitMemberLogin', 'PortalApiDigitalAvatar/submitMemberLogin', 'GET|POST|OPTIONS');
    Route::rule('submitMemberSmsLogin', 'PortalApiDigitalAvatar/submitMemberSmsLogin', 'GET|POST|OPTIONS');
    Route::rule('submitMemberRegister', 'PortalApiDigitalAvatar/submitMemberRegister', 'GET|POST|OPTIONS');
    Route::rule('logoutMemberSession', 'PortalApiDigitalAvatar/logoutMemberSession', 'GET|POST|OPTIONS');
    Route::rule('bootstrap', 'PortalApiDigitalAvatar/bootstrap', 'GET|POST|OPTIONS');
    Route::rule('getSidebarMenu', 'PortalApiDigitalAvatar/getSidebarMenu', 'GET|POST|OPTIONS');
    Route::rule('getWorkbench', 'PortalApiDigitalAvatar/getWorkbench', 'GET|POST|OPTIONS');
    Route::rule('getAvatars', 'PortalApiDigitalAvatar/getAvatars', 'GET|POST|OPTIONS');
    Route::rule('getVoices', 'PortalApiDigitalAvatar/getVoices', 'GET|POST|OPTIONS');
    Route::rule('uploadMemberMedia', 'PortalApiDigitalAvatar/uploadMemberMedia', 'POST|OPTIONS');
    Route::rule('createAvatarCloneTask', 'PortalApiDigitalAvatar/createAvatarCloneTask', 'POST|OPTIONS');
    Route::rule('createVoiceCloneTask', 'PortalApiDigitalAvatar/createVoiceCloneTask', 'POST|OPTIONS');
    Route::rule('previewAudio', 'PortalApiDigitalAvatar/previewAudio', 'GET|POST|OPTIONS');
    Route::rule('createTask', 'PortalApiDigitalAvatar/createTask', 'GET|POST|OPTIONS');
    Route::rule('getTasks', 'PortalApiDigitalAvatar/getTasks', 'GET|POST|OPTIONS');
    Route::rule('exportTasks', 'PortalApiDigitalAvatar/exportTasks', 'GET|POST|OPTIONS');
    Route::rule('getTaskDetail', 'PortalApiDigitalAvatar/getTaskDetail', 'GET|POST|OPTIONS');
    Route::rule('playTaskMedia', 'PortalApiDigitalAvatar/playTaskMedia', 'GET|POST|OPTIONS');
    Route::rule('playAssetMedia', 'PortalApiDigitalAvatar/playAssetMedia', 'GET|POST|OPTIONS');
    Route::rule('cancelTask', 'PortalApiDigitalAvatar/cancelTask', 'GET|POST|OPTIONS');
    Route::rule('getAssets', 'PortalApiDigitalAvatar/getAssets', 'GET|POST|OPTIONS');
    Route::rule('saveCoverImage', 'PortalApiDigitalAvatar/saveCoverImage', 'GET|POST|OPTIONS');
    Route::rule('updateMemberAvatarCover', 'PortalApiDigitalAvatar/updateMemberAvatarCover', 'GET|POST|OPTIONS');
    Route::rule('deleteMemberAvatar', 'PortalApiDigitalAvatar/deleteMemberAvatar', 'GET|POST|OPTIONS');
    Route::rule('deleteMemberVoice', 'PortalApiDigitalAvatar/deleteMemberVoice', 'GET|POST|OPTIONS');
    Route::rule('updateMemberVoice', 'PortalApiDigitalAvatar/updateMemberVoice', 'GET|POST|OPTIONS');
    Route::rule('deleteMemberAsset', 'PortalApiDigitalAvatar/deleteMemberAsset', 'GET|POST|OPTIONS');
    Route::rule('deleteMemberTask', 'PortalApiDigitalAvatar/deleteMemberTask', 'GET|POST|OPTIONS');
    Route::rule('clearMemberTasks', 'PortalApiDigitalAvatar/clearMemberTasks', 'GET|POST|OPTIONS');
    Route::rule('saveMemberAsset', 'PortalApiDigitalAvatar/saveMemberAsset', 'GET|POST|OPTIONS');
    Route::rule('retryTask', 'PortalApiDigitalAvatar/retryTask', 'GET|POST|OPTIONS');
    Route::rule('updateMemberProfile', 'PortalApiDigitalAvatar/updateMemberProfile', 'GET|POST|OPTIONS');
    Route::rule('updateMemberPassword', 'PortalApiDigitalAvatar/updateMemberPassword', 'GET|POST|OPTIONS');
    Route::rule('getCreditSummary', 'PortalApiDigitalAvatar/getCreditSummary', 'GET|POST|OPTIONS');
    Route::rule('getCreditLogs', 'PortalApiDigitalAvatar/getCreditLogs', 'GET|POST|OPTIONS');
    Route::rule('exportCreditLogs', 'PortalApiDigitalAvatar/exportCreditLogs', 'GET|POST|OPTIONS');
    Route::rule('getPackages', 'PortalApiDigitalAvatar/getPackages', 'GET|POST|OPTIONS');
    Route::rule('createPackageOrder', 'PortalApiDigitalAvatar/createPackageOrder', 'GET|POST|OPTIONS');
    Route::rule('getPackagePayOptions', 'PortalApiDigitalAvatar/getPackagePayOptions', 'GET|POST|OPTIONS');
    Route::rule('payPackageOrder', 'PortalApiDigitalAvatar/payPackageOrder', 'GET|POST|OPTIONS');
    Route::rule('getPackageOrderStatus', 'PortalApiDigitalAvatar/getPackageOrderStatus', 'GET|POST|OPTIONS');
    Route::rule('redeemCode', 'PortalApiDigitalAvatar/redeemCode', 'GET|POST|OPTIONS');
    Route::rule('getPackageOrders', 'PortalApiDigitalAvatar/getPackageOrders', 'GET|POST|OPTIONS');
    Route::rule('getInvoices', 'PortalApiDigitalAvatar/getInvoices', 'GET|POST|OPTIONS');
    Route::rule('applyInvoice', 'PortalApiDigitalAvatar/applyInvoice', 'GET|POST|OPTIONS');
    Route::rule('getCheckinInfo', 'PortalApiDigitalAvatar/getCheckinInfo', 'GET|POST|OPTIONS');
    Route::rule('doCheckin', 'PortalApiDigitalAvatar/doCheckin', 'GET|POST|OPTIONS');
    Route::rule('getCheckinLogs', 'PortalApiDigitalAvatar/getCheckinLogs', 'GET|POST|OPTIONS');
    Route::rule('getInviteInfo', 'PortalApiDigitalAvatar/getInviteInfo', 'GET|POST|OPTIONS');
    Route::rule('getInviteLogs', 'PortalApiDigitalAvatar/getInviteLogs', 'GET|POST|OPTIONS');
    Route::rule(':action', 'PortalApiDigitalAvatar/:action', 'GET|POST|OPTIONS')->pattern(['action' => '[A-Za-z][A-Za-z0-9_]*']);
})->allowCrossDomain();
