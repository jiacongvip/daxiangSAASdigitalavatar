<?php
/**
 * 鲸犀商城 - 微信公众号小程序商城系统!
 * Copyright © 2020 鲸犀网络科技有限公司 保留所有权利
 * =========================================================
 * 版本：供应链版本
 * 授权主体：无限制版本
 * 授权域名：@@@
     
 * ----------------------------------------------
 * 您只能在商业授权范围内使用，不可二次转售、分发、分享、传播
 * 任何企业和个人不得对代码以任何目的任何形式的再发布
 * =========================================================
 */

// +----------------------------------------------------------------------
// | 插件数据库迁移基类
// +----------------------------------------------------------------------
namespace app\admin\common;
use think\facade\Db;

/**
 * 插件数据库迁移基类
 * 提供数据库表和字段的增删改操作，确保升级时不影响已有数据
 */
class PluginMigration
{
    /**
     * 数据库表前缀
     * @var string
     */
    protected $prefix;

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->prefix = config('database.connections.mysql.prefix');
    }

    /**
     * 检查表是否存在
     * @param string $tableName 表名（不含前缀）
     * @return bool
     */
    public function tableExists($tableName)
    {
        try {
            $fullTableName = $this->prefix . $tableName;
            $result = Db::query("SHOW TABLES LIKE '{$fullTableName}'");
            return !empty($result);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 检查字段是否存在
     * @param string $tableName 表名（不含前缀）
     * @param string $columnName 字段名
     * @return bool
     */
    public function columnExists($tableName, $columnName)
    {
        try {
            $fullTableName = $this->prefix . $tableName;
            $columns = Db::query("SHOW COLUMNS FROM `{$fullTableName}` LIKE '{$columnName}'");
            return !empty($columns);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 添加字段
     * @param string $tableName 表名（不含前缀）
     * @param string $columnName 字段名
     * @param string $columnDefinition 字段定义（如：VARCHAR(255) NOT NULL DEFAULT '' COMMENT '备注'）
     * @param string $afterColumn 在哪个字段之后添加（可选）
     * @return bool
     */
    public function addColumn($tableName, $columnName, $columnDefinition, $afterColumn = '')
    {
        try {
            // 检查字段是否已存在
            if ($this->columnExists($tableName, $columnName)) {
                return true; // 字段已存在，跳过
            }

            $fullTableName = $this->prefix . $tableName;
            $sql = "ALTER TABLE `{$fullTableName}` ADD COLUMN `{$columnName}` {$columnDefinition}";
            
            if ($afterColumn && $this->columnExists($tableName, $afterColumn)) {
                $sql .= " AFTER `{$afterColumn}`";
            }

            Db::execute($sql);
            return true;
        } catch (\Exception $e) {
            throw new \Exception("添加字段失败: " . $e->getMessage());
        }
    }

    /**
     * 修改字段
     * @param string $tableName 表名（不含前缀）
     * @param string $columnName 字段名
     * @param string $columnDefinition 新的字段定义
     * @return bool
     */
    public function modifyColumn($tableName, $columnName, $columnDefinition)
    {
        try {
            // 检查字段是否存在
            if (!$this->columnExists($tableName, $columnName)) {
                throw new \Exception("字段 {$columnName} 不存在");
            }

            $fullTableName = $this->prefix . $tableName;
            $sql = "ALTER TABLE `{$fullTableName}` MODIFY COLUMN `{$columnName}` {$columnDefinition}";
            
            Db::execute($sql);
            return true;
        } catch (\Exception $e) {
            throw new \Exception("修改字段失败: " . $e->getMessage());
        }
    }

    /**
     * 重命名字段
     * @param string $tableName 表名（不含前缀）
     * @param string $oldColumnName 旧字段名
     * @param string $newColumnName 新字段名
     * @param string $columnDefinition 字段定义
     * @return bool
     */
    public function renameColumn($tableName, $oldColumnName, $newColumnName, $columnDefinition)
    {
        try {
            // 检查旧字段是否存在
            if (!$this->columnExists($tableName, $oldColumnName)) {
                // 检查新字段是否已存在
                if ($this->columnExists($tableName, $newColumnName)) {
                    return true; // 新字段已存在，可能已经重命名过了
                }
                throw new \Exception("字段 {$oldColumnName} 不存在");
            }

            $fullTableName = $this->prefix . $tableName;
            $sql = "ALTER TABLE `{$fullTableName}` CHANGE COLUMN `{$oldColumnName}` `{$newColumnName}` {$columnDefinition}";

            Db::execute($sql);
            return true;
        } catch (\Exception $e) {
            throw new \Exception("重命名字段失败: " . $e->getMessage());
        }
    }

    /**
     * 删除字段
     * @param string $tableName 表名（不含前缀）
     * @param string $columnName 字段名
     * @return bool
     */
    public function dropColumn($tableName, $columnName)
    {
        try {
            // 检查字段是否存在
            if (!$this->columnExists($tableName, $columnName)) {
                return true; // 字段不存在，跳过
            }

            $fullTableName = $this->prefix . $tableName;
            $sql = "ALTER TABLE `{$fullTableName}` DROP COLUMN `{$columnName}`";

            Db::execute($sql);
            return true;
        } catch (\Exception $e) {
            throw new \Exception("删除字段失败: " . $e->getMessage());
        }
    }

    /**
     * 添加索引
     * @param string $tableName 表名（不含前缀）
     * @param string $indexName 索引名
     * @param string|array $columns 字段名（单个字段或字段数组）
     * @param string $indexType 索引类型（INDEX, UNIQUE, FULLTEXT）
     * @return bool
     */
    public function addIndex($tableName, $indexName, $columns, $indexType = 'INDEX')
    {
        try {
            // 检查索引是否已存在
            if ($this->indexExists($tableName, $indexName)) {
                return true; // 索引已存在，跳过
            }

            $fullTableName = $this->prefix . $tableName;

            if (is_array($columns)) {
                $columnList = '`' . implode('`, `', $columns) . '`';
            } else {
                $columnList = "`{$columns}`";
            }

            $sql = "ALTER TABLE `{$fullTableName}` ADD {$indexType} `{$indexName}` ({$columnList})";

            Db::execute($sql);
            return true;
        } catch (\Exception $e) {
            throw new \Exception("添加索引失败: " . $e->getMessage());
        }
    }

    /**
     * 检查索引是否存在
     * @param string $tableName 表名（不含前缀）
     * @param string $indexName 索引名
     * @return bool
     */
    public function indexExists($tableName, $indexName)
    {
        try {
            $fullTableName = $this->prefix . $tableName;
            $result = Db::query("SHOW INDEX FROM `{$fullTableName}` WHERE Key_name = '{$indexName}'");
            return !empty($result);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 删除索引
     * @param string $tableName 表名（不含前缀）
     * @param string $indexName 索引名
     * @return bool
     */
    public function dropIndex($tableName, $indexName)
    {
        try {
            // 检查索引是否存在
            if (!$this->indexExists($tableName, $indexName)) {
                return true; // 索引不存在，跳过
            }

            $fullTableName = $this->prefix . $tableName;
            $sql = "ALTER TABLE `{$fullTableName}` DROP INDEX `{$indexName}`";

            Db::execute($sql);
            return true;
        } catch (\Exception $e) {
            throw new \Exception("删除索引失败: " . $e->getMessage());
        }
    }

    /**
     * 执行原生SQL
     * @param string $sql SQL语句
     * @return mixed
     */
    public function execute($sql)
    {
        try {
            // 替换表前缀占位符
            $sql = str_replace('{prefix}', $this->prefix, $sql);
            return Db::execute($sql);
        } catch (\Exception $e) {
            throw new \Exception("执行SQL失败: " . $e->getMessage());
        }
    }

    /**
     * 执行查询SQL
     * @param string $sql SQL语句
     * @return array
     */
    public function query($sql)
    {
        try {
            // 替换表前缀占位符
            $sql = str_replace('{prefix}', $this->prefix, $sql);
            return Db::query($sql);
        } catch (\Exception $e) {
            throw new \Exception("查询SQL失败: " . $e->getMessage());
        }
    }
}

