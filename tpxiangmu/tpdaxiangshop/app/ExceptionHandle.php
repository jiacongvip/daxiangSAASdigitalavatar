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

namespace app;

use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\Handle;
use think\exception\HttpException;
use think\exception\HttpResponseException;
use think\exception\ValidateException;
use think\Response;
use Throwable;

/**
 * 应用异常处理类
 */
class ExceptionHandle extends Handle
{
    /**
     * 不需要记录信息（日志）的异常类列表
     * @var array
     */
    protected $ignoreReport = [
        HttpException::class,
        HttpResponseException::class,
        ModelNotFoundException::class,
        DataNotFoundException::class,
        ValidateException::class,
    ];

    /**
     * 记录异常信息（包括日志或者其它方式记录）
     *
     * @access public
     * @param  Throwable $exception
     * @return void
     */
    public function report(Throwable $exception): void
    {
        // 过滤不需要记录的异常
        $message = $exception->getMessage();
        
        // 不记录以下类型的错误
        $ignorePatterns = [
            '模板文件不存在',
            '访问不存在的方法',
            'fields not exists',
            '404'
        ];
        
        foreach($ignorePatterns as $pattern){
            if(strpos($message, $pattern) !== false){
                return; // 不记录日志
            }
        }
        
        // 使用内置的方式记录异常日志
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @access public
     * @param \think\Request   $request
     * @param Throwable $e
     * @return Response
     */
    public function render($request, Throwable $e): Response
    {
        // 添加自定义异常处理机制
        $message = $e->getMessage();
        
        // 判断是否是不重要的错误（404、模板不存在等）
        $isIgnorableError = false;
        $ignorePatterns = [
            '模板文件不存在',
            '访问不存在的方法',
            'fields not exists'
        ];
        
        foreach($ignorePatterns as $pattern){
            if(strpos($message, $pattern) !== false){
                $isIgnorableError = true;
                break;
            }
        }
        
        // 如果是404等不重要的错误，返回友好的404页面
        if($isIgnorableError || ($e instanceof HttpException && $e->getStatusCode() == 404)){
            // 判断是否是后台请求
            if(strpos($request->pathinfo(), 'admin') !== false){
                // 尝试返回404模板
                try{
                    return response(view('public/404'), 404);
                }catch(\Exception $ex){
                    // 如果模板也加载失败，返回简单的404文本
                    return response('页面不存在', 404);
                }
            }
        }

        // 判断是否是API请求（Ajax请求或者控制器名包含Api）
        $isApiRequest = $request->isAjax() || 
                       strpos($request->controller(), 'Api') !== false ||
                       strpos($request->pathinfo(), 'api/') !== false;
        
        if ($isApiRequest) {
            // API请求返回JSON格式的详细错误信息
            $errorData = [
                'code' => 0,
                'status' => method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500,
                'msg' => $e->getMessage() ?: '系统错误',
                'data' => null
            ];
            
            // 在调试模式下添加更多错误详情
            if (config('app.app_debug')) {
                $errorData['debug'] = [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ];
            }
            
            // 只记录非忽略类的API错误
            if(!$isIgnorableError){
                \think\facade\Log::error('API异常: ' . $e->getMessage(), [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'url' => $request->url(true),
                    'method' => $request->method(),
                    'params' => $request->param()
                ]);
            }
            
            return json($errorData);
        }

        // 其他错误交给系统处理
        return parent::render($request, $e);
    }
}
