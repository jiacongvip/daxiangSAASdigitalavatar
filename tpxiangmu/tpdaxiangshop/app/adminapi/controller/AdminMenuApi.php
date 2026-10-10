<?php
/**
 * 后台菜单管理API
 * 用于Vue管理后台的菜单CRUD操作
 */
namespace app\adminapi\controller;

use think\facade\Db;
use think\facade\View;

class AdminMenuApi extends Common
{
    /**
     * 获取菜单列表（树形结构）
     */
    public function getList()
    {
        $keyword = input('keyword', '');
        $status = input('status', '');
        
        $where = [];
        if($keyword) {
            $where[] = ['name', 'like', '%'.$keyword.'%'];
        }
        if($status !== '') {
            $where[] = ['status', '=', intval($status)];
        }
        
        $list = Db::name('admin_menu')
            ->where($where)
            ->order('sort asc, id asc')
            ->select()
            ->toArray();
        
        // 构建树形结构
        $tree = $this->buildTree($list);
        
        return json([
            'status' => 1,
            'msg' => '获取成功',
            'data' => [
                'list' => $tree,
                'total' => count($list)
            ]
        ]);
    }
    
    /**
     * 获取菜单详情
     */
    public function getDetail()
    {
        $id = input('id/d', 0);
        if(!$id) {
            return json(['status' => 0, 'msg' => '参数错误']);
        }
        
        $info = Db::name('admin_menu')->where('id', $id)->find();
        if(!$info) {
            return json(['status' => 0, 'msg' => '菜单不存在']);
        }
        
        return json([
            'status' => 1,
            'msg' => '获取成功',
            'data' => $info
        ]);
    }
    
    /**
     * 保存菜单（新增/编辑）
     */
    public function save()
    {
        $id = input('post.id/d', 0);
        $data = [
            'pid' => input('post.pid/d', 0),
            'key' => input('post.key/s', ''),
            'name' => input('post.name/s', ''),
            'fullname' => input('post.fullname/s', ''),
            'path' => input('post.path/s', ''),
            'icon' => input('post.icon/s', ''),
            'auth_data' => input('post.auth_data/s', ''),
            'auth_mode' => input('post.auth_mode/d', 0),
            'level' => input('post.level/d', 1),
            'sort' => input('post.sort/d', 0),
            'status' => input('post.status/d', 1),
            'admin_only' => input('post.admin_only/d', 0),
            'merchant_visible' => input('post.merchant_visible/d', 1),
            'tuanzhang_visible' => input('post.tuanzhang_visible/d', 1),
            'platform_limit' => input('post.platform_limit/s', ''),
        ];
        
        // 验证必填字段
        if(empty($data['name'])) {
            return json(['status' => 0, 'msg' => '菜单名称不能为空']);
        }
        if(empty($data['key'])) {
            // 自动生成key
            $data['key'] = 'menu_' . time() . rand(100, 999);
        }
        
        // 检查key是否重复
        $existKey = Db::name('admin_menu')
            ->where('key', $data['key'])
            ->where('id', '<>', $id)
            ->find();
        if($existKey) {
            return json(['status' => 0, 'msg' => '菜单标识已存在']);
        }
        
        if($id) {
            // 编辑
            $data['updatetime'] = time();
            Db::name('admin_menu')->where('id', $id)->update($data);
            \app\admin\common\System::plog('编辑菜单：' . $data['name']);
            return json(['status' => 1, 'msg' => '编辑成功']);
        } else {
            // 新增
            $data['createtime'] = time();
            $data['updatetime'] = time();
            $newId = Db::name('admin_menu')->insertGetId($data);
            \app\admin\common\System::plog('新增菜单：' . $data['name']);
            return json(['status' => 1, 'msg' => '新增成功', 'data' => ['id' => $newId]]);
        }
    }
    
    /**
     * 删除菜单
     */
    public function del()
    {
        $ids = input('post.ids/a', []);
        if(empty($ids)) {
            $ids = [input('post.id/d', 0)];
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $ids = array_filter($ids, function($id){
            return $id > 0;
        });
        
        if(empty($ids)) {
            return json(['status' => 0, 'msg' => '请选择要删除的菜单']);
        }

        // 批量删除时允许选中父级菜单，自动带上所有下级菜单。
        $deleteIds = $this->collectMenuDescendantIds($ids);
        if(empty($deleteIds)) {
            return json(['status' => 0, 'msg' => '未找到可删除的菜单']);
        }

        // 获取菜单名称用于日志
        $menus = Db::name('admin_menu')->where('id', 'in', $deleteIds)->column('name');
        if(empty($menus)) {
            return json(['status' => 0, 'msg' => '未找到可删除的菜单']);
        }

        Db::startTrans();
        try{
            Db::name('admin_menu')->where('id', 'in', $deleteIds)->delete();
            \app\admin\common\MenuDatabase::clearCache();
            Db::commit();
        }catch(\Throwable $e){
            Db::rollback();
            return json(['status' => 0, 'msg' => '删除失败：' . $e->getMessage()]);
        }

        \app\admin\common\System::plog('删除菜单：' . implode(',', $menus));
        
        return json(['status' => 1, 'msg' => '删除成功', 'data' => ['deleted' => count($menus)]]);
    }

    /**
     * 收集菜单及全部下级菜单ID
     */
    private function collectMenuDescendantIds($ids)
    {
        $allIds = [];
        $queue = $ids;

        while(!empty($queue)){
            $current = [];
            foreach($queue as $id){
                $id = intval($id);
                if($id > 0 && !isset($allIds[$id])){
                    $current[] = $id;
                    $allIds[$id] = true;
                }
            }

            if(empty($current)){
                break;
            }

            $queue = Db::name('admin_menu')->where('pid', 'in', $current)->column('id');
        }

        return array_keys($allIds);
    }
    
    /**
     * 修改状态
     */
    public function setStatus()
    {
        $id = input('post.id/d', 0);
        $status = input('post.status/d', 0);
        
        if(!$id) {
            return json(['status' => 0, 'msg' => '参数错误']);
        }
        
        Db::name('admin_menu')->where('id', $id)->update([
            'status' => $status,
            'updatetime' => time()
        ]);
        
        $menu = Db::name('admin_menu')->where('id', $id)->find();
        \app\admin\common\System::plog(($status ? '启用' : '禁用') . '菜单：' . $menu['name']);
        
        return json(['status' => 1, 'msg' => '操作成功']);
    }
    
    /**
     * 修改排序
     */
    public function setSort()
    {
        $id = input('post.id/d', 0);
        $sort = input('post.sort/d', 0);
        
        if(!$id) {
            return json(['status' => 0, 'msg' => '参数错误']);
        }
        
        Db::name('admin_menu')->where('id', $id)->update([
            'sort' => $sort,
            'updatetime' => time()
        ]);
        
        return json(['status' => 1, 'msg' => '操作成功']);
    }
    
    /**
     * 获取父级菜单列表（用于下拉选择）
     */
    public function getParentList()
    {
        $list = Db::name('admin_menu')
            ->where('pid', 0)
            ->where('status', 1)
            ->order('sort asc, id asc')
            ->field('id, name, key')
            ->select()
            ->toArray();
        
        // 添加顶级选项
        array_unshift($list, ['id' => 0, 'name' => '顶级菜单', 'key' => 'root']);
        
        return json([
            'status' => 1,
            'msg' => '获取成功',
            'data' => $list
        ]);
    }
    
    /**
     * 批量修改排序
     */
    public function batchSort()
    {
        $items = input('post.items/a', []);
        if(empty($items)) {
            return json(['status' => 0, 'msg' => '参数错误']);
        }
        
        foreach($items as $item) {
            if(isset($item['id']) && isset($item['sort'])) {
                Db::name('admin_menu')->where('id', $item['id'])->update([
                    'sort' => intval($item['sort']),
                    'updatetime' => time()
                ]);
            }
        }
        
        return json(['status' => 1, 'msg' => '排序更新成功']);
    }

    /**
     * 导入基础版默认菜单
     */
    public function importDefaultMenus()
    {
        try{
            $result = \app\admin\common\AdminMenuDefaultImporter::importBaseDefaults();
            \app\admin\common\System::plog(
                '导入基础版默认菜单：新增' . intval($result['inserted'] ?? 0)
                . '条，更新' . intval($result['updated'] ?? 0)
                . '条，停用旧菜单' . intval($result['disabled'] ?? 0) . '条'
            );
            return json([
                'status' => 1,
                'msg' => '默认菜单导入成功',
                'data' => $result,
            ]);
        }catch(\Throwable $e){
            return json(['status' => 0, 'msg' => '导入失败：' . $e->getMessage()]);
        }
    }
    
    /**
     * 构建树形结构
     */
    private function buildTree($list, $pid = 0)
    {
        $tree = [];
        foreach($list as $item) {
            if($item['pid'] == $pid) {
                $children = $this->buildTree($list, $item['id']);
                if($children) {
                    $item['children'] = $children;
                }
                $tree[] = $item;
            }
        }
        return $tree;
    }
    
    /**
     * 获取菜单设置
     */
    public function getSettings()
    {
        // 获取菜单模式配置
        $config = Db::name('admin_system_config')
            ->where('key', 'menu_use_database')
            ->find();
        
        $menuUseDatabase = $config ? intval($config['value']) : 0;
        
        // 获取数据库菜单数量
        $menuCount = Db::name('admin_menu')->count();
        
        return json([
            'status' => 1,
            'msg' => '获取成功',
            'data' => [
                'menu_use_database' => $menuUseDatabase,
                'menu_count' => $menuCount
            ]
        ]);
    }
    
    /**
     * 保存菜单设置
     */
    public function saveSettings()
    {
        $menuUseDatabase = input('post.menu_use_database/d', 0);
        $tableFields = array_flip(Db::name('admin_system_config')->getTableFields());
        
        // 检查配置是否存在
        $config = Db::name('admin_system_config')
            ->where('key', 'menu_use_database')
            ->find();
        
        if($config) {
            // 更新
            Db::name('admin_system_config')
                ->where('key', 'menu_use_database')
                ->update(array_intersect_key([
                    'value' => $menuUseDatabase,
                    'updatetime' => time()
                ], $tableFields));
        } else {
            // 新增
            Db::name('admin_system_config')->insert(array_intersect_key([
                'key' => 'menu_use_database',
                'value' => $menuUseDatabase,
                'type' => 'switch',
                'description' => '是否使用数据库菜单模式',
                'create_uid' => 0,
                'createtime' => time(),
                'updatetime' => time()
            ], $tableFields));
        }
        
        \app\admin\common\System::plog('修改菜单设置：' . ($menuUseDatabase ? '数据库模式' : '文件模式'));
        
        return json(['status' => 1, 'msg' => '保存成功']);
    }
    
    /**
     * 清除菜单缓存
     */
    public function clearCache()
    {
        // 清除 runtime 目录下的缓存文件
        $runtimePath = root_path() . 'runtime/';
        
        // 删除缓存文件
        $cacheFiles = glob($runtimePath . 'cache/*');
        foreach($cacheFiles as $file) {
            if(is_file($file)) {
                @unlink($file);
            }
        }
        
        // 清除 ThinkPHP 缓存
        \think\facade\Cache::clear();
        
        \app\admin\common\System::plog('清除菜单缓存');
        
        return json(['status' => 1, 'msg' => '缓存已清除']);
    }
}
