<?php
/**
 * 调试辅助类
 * 提供统一的错误处理和日志记录功能
 */

namespace app\common;

use think\facade\Log;
use think\facade\Request;

class DebugHelper
{
    /**
     * 记录API错误日志并返回统一格式的错误响应
     * 
     * @param \Exception $e 异常对象
     * @param string $context 上下文信息
     * @param array $extraData 额外数据
     * @return array
     */
    public static function handleApiError($e, $context = '', $extraData = [])
    {
        // 构建错误信息
        $errorMsg = $e->getMessage() ?: '系统错误';
        $logContext = $context ? "[$context] " : '';
        
        // 记录详细日志
        Log::error($logContext . 'API异常: ' . $errorMsg, array_merge([
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'url' => Request::url(true),
            'method' => Request::method(),
            'params' => Request::param(),
            'trace' => $e->getTraceAsString()
        ], $extraData));
        
        // 构建返回数据
        $response = [
            'code' => 0,
            'status' => method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500,
            'msg' => $errorMsg,
            'data' => null
        ];
        
        // 调试模式下添加详细信息
        if (config('app.app_debug')) {
            $response['debug'] = [
                'context' => $context,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => array_slice(explode("\n", $e->getTraceAsString()), 0, 10) // 只显示前10行堆栈
            ];
        }
        
        return $response;
    }
    
    /**
     * 记录调试日志
     * 
     * @param string $message 日志消息
     * @param string $context 上下文
     * @param array $data 附加数据
     * @param string $level 日志级别
     */
    public static function log($message, $context = '', $data = [], $level = 'info')
    {
        $logMessage = $context ? "[$context] $message" : $message;
        
        if (!empty($data)) {
            Log::$level($logMessage, $data);
        } else {
            Log::$level($logMessage);
        }
    }
    
    /**
     * 检查并记录SQL错误
     * 
     * @param string $sql SQL语句
     * @param array $params 参数
     * @param \Exception $e 异常
     */
    public static function logSqlError($sql, $params = [], $e = null)
    {
        $logData = [
            'sql' => $sql,
            'params' => $params,
            'url' => Request::url(true),
            'method' => Request::method()
        ];
        
        if ($e) {
            $logData['error'] = $e->getMessage();
            $logData['file'] = $e->getFile();
            $logData['line'] = $e->getLine();
        }
        
        Log::error('SQL执行错误', $logData);
    }
    
    /**
     * 输出调试信息到控制台（仅调试模式）
     * 
     * @param mixed $data 要输出的数据
     * @param string $label 标签
     */
    public static function console($data, $label = 'Debug')
    {
        if (config('app.app_debug')) {
            $output = is_array($data) || is_object($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : $data;
            Log::info("[$label] Console Output: " . $output);
        }
    }
} 