<?php
/**
 * 鲸犀商城 - 系统配置管理类
 * SystemConfig - 统一管理系统配置，支持缓存和日志
 * 
 * @author Augment Agent
 * @date 2025-11-18
 */

namespace app\admin\common;
use think\facade\Db;
use think\facade\Cache;

class SystemConfig
{
    /**
     * 缓存前缀
     */
    const CACHE_PREFIX = 'system_config_';
    
    /**
     * 缓存时间(秒)
     */
    const CACHE_TIME = 86400; // 24小时
    
    /**
     * 获取配置值
     * 
     * @param string $key 配置键名
     * @param mixed $default 默认值
     * @return mixed
     */
    public static function get($key, $default = null)
    {
        // 尝试从缓存读取
        $cacheKey = self::CACHE_PREFIX . $key;
        $value = Cache::get($cacheKey);
        
        if($value === false){
            // 缓存不存在，从数据库读取
            $config = Db::name('admin_system_config')
                ->where('key', $key)
                ->find();
            
            if(!$config){
                return $default;
            }
            
            // 根据类型转换值
            $value = self::castValue($config['value'], $config['type']);
            
            // 写入缓存
            Cache::set($cacheKey, $value, self::CACHE_TIME);
        }
        
        return $value !== null ? $value : $default;
    }
    
    /**
     * 设置配置值
     * 
     * @param string $key 配置键名
     * @param mixed $value 配置值
     * @param int $uid 操作人ID
     * @return bool
     */
    public static function set($key, $value, $uid = 0)
    {
        try {
            // 获取旧值用于记录日志
            $oldConfig = Db::name('admin_system_config')
                ->where('key', $key)
                ->find();
            
            $oldValue = $oldConfig ? $oldConfig['value'] : null;
            
            // 更新或插入配置
            $data = [
                'key' => $key,
                'value' => $value,
                'updatetime' => time(),
                'update_uid' => $uid
            ];
            
            if($oldConfig){
                // 更新
                Db::name('admin_system_config')
                    ->where('key', $key)
                    ->update($data);
            }else{
                // 插入
                $data['createtime'] = time();
                Db::name('admin_system_config')->insert($data);
            }
            
            // 清除缓存
            self::clearCache($key);
            
            // 记录日志
            self::log($key, $oldValue, $value, $uid);
            
            return true;
            
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * 批量设置配置
     * 
     * @param array $configs 配置数组 ['key' => 'value', ...]
     * @param int $uid 操作人ID
     * @return bool
     */
    public static function setBatch($configs, $uid = 0)
    {
        if(empty($configs) || !is_array($configs)){
            return false;
        }
        
        try {
            foreach($configs as $key => $value){
                self::set($key, $value, $uid);
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * 获取所有配置
     * 
     * @param string $group 分组，为空则获取所有
     * @return array
     */
    public static function getAll($group = '')
    {
        $query = Db::name('admin_system_config');
        
        if(!empty($group)){
            $query->where('group', $group);
        }
        
        $configs = $query->select()->toArray();
        
        $result = [];
        foreach($configs as $config){
            $result[$config['key']] = self::castValue($config['value'], $config['type']);
        }
        
        return $result;
    }
    
    /**
     * 删除配置
     * 
     * @param string $key 配置键名
     * @return bool
     */
    public static function delete($key)
    {
        try {
            Db::name('admin_system_config')->where('key', $key)->delete();
            self::clearCache($key);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * 清除缓存
     * 
     * @param string|null $key 配置键名，为空则清除所有配置缓存
     */
    public static function clearCache($key = null)
    {
        if($key !== null){
            $cacheKey = self::CACHE_PREFIX . $key;
            Cache::delete($cacheKey);
        }else{
            // 清除所有配置缓存
            $configs = Db::name('admin_system_config')->column('key');
            foreach($configs as $k){
                $cacheKey = self::CACHE_PREFIX . $k;
                Cache::delete($cacheKey);
            }
        }
    }
    
    /**
     * 类型转换
     * 
     * @param mixed $value 原始值
     * @param string $type 类型
     * @return mixed
     */
    private static function castValue($value, $type)
    {
        switch($type){
            case 'int':
                return (int)$value;
            case 'bool':
                // ✅ 正确的 bool 判断逻辑
                if($value === true || $value === 1 || $value === '1' || $value === 'true'){
                    return true;
                }
                if($value === false || $value === 0 || $value === '0' || $value === 'false' || $value === '' || $value === null){
                    return false;
                }
                return (bool)$value;
            case 'float':
                return (float)$value;
            case 'json':
                return json_decode($value, true);
            case 'array':
                if(is_array($value)){
                    return $value;
                }
                return json_decode($value, true) ?: [];
            default:
                return $value;
        }
    }
    
    /**
     * 记录配置修改日志
     * 
     * @param string $key 配置键名
     * @param mixed $oldValue 旧值
     * @param mixed $newValue 新值
     * @param int $uid 操作人ID
     */
    private static function log($key, $oldValue, $newValue, $uid)
    {
        try {
            // 获取操作人信息
            $username = '系统';
            if($uid > 0){
                $user = Db::name('admin_user')->where('id', $uid)->find();
                if($user){
                    $username = $user['username'] ?? $user['nickname'] ?? 'User' . $uid;
                }
            }
            
            // 获取IP
            $ip = request()->ip();
            
            $logData = [
                'config_key' => $key,
                'old_value' => is_array($oldValue) ? json_encode($oldValue, JSON_UNESCAPED_UNICODE) : $oldValue,
                'new_value' => is_array($newValue) ? json_encode($newValue, JSON_UNESCAPED_UNICODE) : $newValue,
                'uid' => $uid,
                'username' => $username,
                'ip' => $ip,
                'createtime' => time()
            ];
            
            Db::name('admin_system_config_log')->insert($logData);
            
        } catch (\Exception $e) {
            // 日志记录失败不影响主流程
        }
    }
    
    /**
     * 获取配置修改日志
     * 
     * @param string|null $key 配置键名，为空则获取所有日志
     * @param int $limit 数量限制
     * @return array
     */
    public static function getLogs($key = null, $limit = 100)
    {
        $query = Db::name('admin_system_config_log');
        
        if($key !== null){
            $query->where('config_key', $key);
        }
        
        return $query->order('createtime', 'desc')
            ->limit($limit)
            ->select()
            ->toArray();
    }
}

