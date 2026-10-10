<?php
/**
 * 插件管理 API（Vue3 管理后台专用）
 */
namespace app\adminapi\controller;
use app\admin\common\PluginCategoryRegistry;
use think\facade\Db;

class PluginManagerApi extends Common
{
    public function initialize(){
        parent::initialize();
        $role = \app\admin\common\ConsoleAuth::getConsoleRole($this->user ?: []);
        // 只允许总控台访问；渠道管理员/站点账号都不能拉取全量插件或安装卸载
        if($role !== \app\admin\common\ConsoleAuth::ROLE_SUPER) {
            echo json_encode(['status' => 0, 'msg' => '无访问权限'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    /**
     * 获取插件列表
     */
    public function getList(){
        try {
            $plugins = $this->scanPlugins();
            
            // 获取已安装的插件状态和版本
            $installedPlugins = [];
            $installedVersions = [];
            $useVueStatus = [];
            $useVueVersionStatus = [];
            if($this->checkTableExists('admin_plugin_menu')){
                // 聚合插件状态，避免多 aid 行导致状态取值不稳定
                $menuRows = Db::name('admin_plugin_menu')
                    ->field('plugin_name, MAX(status) as status')
                    ->group('plugin_name')
                    ->select()
                    ->toArray();
                foreach($menuRows as $menuRow){
                    $name = $menuRow['plugin_name'] ?? '';
                    if($name !== ''){
                        $installedPlugins[$name] = intval($menuRow['status'] ?? 1);
                    }
                }

                try{
                    if($this->checkColumnExists('admin_plugin_menu', 'use_vue')){
                        $useVueRows = Db::name('admin_plugin_menu')
                            ->field('plugin_name, MAX(use_vue) as use_vue')
                            ->group('plugin_name')
                            ->select()
                            ->toArray();
                        foreach($useVueRows as $useVueRow){
                            $name = $useVueRow['plugin_name'] ?? '';
                            if($name !== ''){
                                $useVueStatus[$name] = intval($useVueRow['use_vue'] ?? 0);
                            }
                        }
                    }
                }catch(\Exception $e){
                    // 字段不存在时忽略
                }
            }
            
            // 获取已安装插件的版本信息（总控台全局，不区分商户）
            if($this->checkTableExists('admin_plugin_version')){
                $installedVersions = Db::name('admin_plugin_version')
                    ->column('version', 'plugin_name');

                try{
                    if($this->checkColumnExists('admin_plugin_version', 'use_vue')){
                        $useVueVersionStatus = Db::name('admin_plugin_version')
                            ->column('use_vue', 'plugin_name');
                    }
                }catch(\Exception $e){
                    // 字段不存在时忽略
                }
            }
            
            foreach($plugins as &$plugin){
                $pluginName = $plugin['name'];
                $hasMenuRecord = array_key_exists($pluginName, $installedPlugins);
                $hasVersionRecord = array_key_exists($pluginName, $installedVersions);
                if($hasMenuRecord || $hasVersionRecord){
                    $plugin['installed'] = 1;
                    $plugin['status'] = intval($installedPlugins[$pluginName] ?? 1);
                    $plugin['use_vue'] = intval($useVueStatus[$pluginName] ?? ($useVueVersionStatus[$pluginName] ?? ($plugin['use_vue'] ?? 0)));
                    $plugin['supports_vue'] = $this->pluginSupportsVue($plugin);
                    if(!$plugin['supports_vue']){
                        $plugin['use_vue'] = 0;
                    }
                    
                    // 检查是否需要升级
                    $installedVersion = $installedVersions[$pluginName] ?? '1.0.0';
                    $currentVersion = $plugin['version'] ?? '1.0.0';
                    
                    $plugin['installed_version'] = $installedVersion;
                    $plugin['need_upgrade'] = version_compare($currentVersion, $installedVersion, '>');
                }else{
                    $plugin['installed'] = 0;
                    $plugin['status'] = 0;
                    $plugin['use_vue'] = 0;
                    $plugin['supports_vue'] = $this->pluginSupportsVue($plugin);
                    $plugin['installed_version'] = '';
                    $plugin['need_upgrade'] = false;
                }
            }
            
            return json([
                'status' => 1,
                'msg' => '获取成功',
                'data' => [
                    'list' => $plugins,
                    'total' => count($plugins)
                ]
            ]);
        } catch(\Exception $e) {
            return json(['status' => 0, 'msg' => '获取失败：' . $e->getMessage()]);
        }
    }

    /**
     * 安装插件
     */
    public function install(){
        $pluginName = input('post.plugin_name');
        if(empty($pluginName)){
            return json(['status' => 0, 'msg' => '插件名称不能为空']);
        }
        
        try{
            // 确保版本表存在
            $this->ensureVersionTableExists();
            
            // 使用PluginMenu类注册菜单
            \app\admin\common\PluginMenu::registerFromConfig($pluginName);
            
            // 执行插件的install方法
            $pluginClass = "\\app\\plugin\\{$pluginName}\\Plugin";
            if(class_exists($pluginClass)){
                $plugin = new $pluginClass();
                if(method_exists($plugin, 'install')){
                    $result = $plugin->install();
                    // 如果插件安装返回错误，抛出异常
                    if(is_array($result) && isset($result['status']) && $result['status'] == 0){
                        throw new \Exception($result['msg'] ?? '插件安装失败');
                    }
                }
            }
            
            // 记录插件版本
            $pluginInfo = $this->getPluginInfo($pluginName);
            $version = $pluginInfo['version'] ?? '1.0.0';
            $this->savePluginVersion($pluginName, $version);

            // ✅ 安装后同步 use_vue（否则站点后台会因为 admin_plugin_version.use_vue=0 而走 iframe，导致 Vue 插件侧边栏/路由不生效）
            $useVueFromConfig = $this->pluginSupportsVue($pluginInfo) ? intval($pluginInfo['use_vue'] ?? 0) : 0;
            $this->syncUseVueFlag($pluginName, $useVueFromConfig);
            
            // 确保在 admin_plugin_menu 表中有记录（即使没有菜单配置）
            // 插件安装后，需要为所有商户创建菜单记录
            if($this->checkTableExists('admin_plugin_menu')){
                // 获取所有站点 aid（注意：admin_set.id 不是业务 aid）
                $allAids = Db::name('admin_set')
                    ->where('aid', '>', 0)
                    ->column('aid');
                
                // 如果没有商户，至少为 aid=0 创建一条记录
                if(empty($allAids)){
                    $allAids = [0];
                }
                
                // 为每个商户创建菜单记录（如果还没有）
                $pid = $this->getOrCreatePluginParentMenu();
                foreach($allAids as $aid){
                    $exists = Db::name('admin_plugin_menu')
                        ->where('aid', $aid)
                        ->where('plugin_name', $pluginName)
                        ->count();
                    
                    if($exists == 0){
                        Db::name('admin_plugin_menu')->insert([
                            'aid' => $aid,
                            'pid' => $pid,
                            'plugin_name' => $pluginName,
                            'key' => '',
                            'name' => $pluginInfo['title'] ?? $pluginName,
                            'path' => '',
                            'icon' => $pluginInfo['icon'] ?? '',
                            'sort' => 0,
                            'status' => 1,
                            'createtime' => time()
                        ]);
                    }
                }
            }
            
            // 自动导入插件教程到帮助中心
            $this->seedPluginTutorials($pluginName);

            return json(['status' => 1, 'msg' => '插件安装成功']);
        }catch(\Exception $e){
            return json(['status' => 0, 'msg' => '安装失败：' . $e->getMessage()]);
        }
    }

    /**
     * 执行插件声明的默认数据同步动作。
     * 插件统一通过 initial_data.action=initializeData 声明安全的数据初始化入口。
     */
    public function initializeData(){
        $pluginName = trim((string)input('post.plugin_name/s', ''));
        if($pluginName === ''){
            return json(['status' => 0, 'msg' => '插件名称不能为空']);
        }

        if(!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $pluginName)){
            return json(['status' => 0, 'msg' => '插件名称格式不正确']);
        }

        $pluginInfo = $this->getPluginInfo($pluginName);
        if(empty($pluginInfo)){
            return json(['status' => 0, 'msg' => '插件配置不存在']);
        }

        $initialData = $pluginInfo['initial_data'] ?? [];
        if(!is_array($initialData) || intval($initialData['enabled'] ?? 0) !== 1){
            return json(['status' => 0, 'msg' => '该插件未提供默认数据同步功能']);
        }

        $action = trim((string)($initialData['action'] ?? 'initializeData'));
        // 生命周期方法不能通过默认数据入口调用，避免配置错误触发安装/卸载等动作。
        if($action !== 'initializeData'){
            return json(['status' => 0, 'msg' => '该插件的默认数据同步动作未被支持']);
        }

        if(!$this->isPluginInstalled($pluginName)){
            return json(['status' => 0, 'msg' => '插件未安装，请先安装插件']);
        }

        try{
            $pluginClass = "\\app\\plugin\\{$pluginName}\\Plugin";
            if(!class_exists($pluginClass)){
                return json(['status' => 0, 'msg' => '插件实现不存在']);
            }

            $plugin = new $pluginClass();
            if(!method_exists($plugin, $action)){
                return json(['status' => 0, 'msg' => '该插件未实现默认数据同步功能']);
            }

            $result = $plugin->{$action}();
            if(!is_array($result)){
                return json(['status' => 0, 'msg' => '默认数据同步返回结果无效']);
            }

            if(intval($result['status'] ?? 0) === 1){
                $pluginTitle = trim((string)($pluginInfo['title'] ?? $pluginName));
                \app\admin\common\System::plog($pluginTitle . '插件同步默认数据');
            }

            return json($result);
        }catch(\Throwable $e){
            return json(['status' => 0, 'msg' => '同步失败：' . $e->getMessage()]);
        }
    }

    /**
     * 导入插件压缩包并安装/同步
     */
    public function importPackage(){
        $file = request()->file('file');
        if(!$file){
            return json(['status' => 0, 'msg' => '请选择插件 ZIP 文件']);
        }

        $tmpRoot = '';
        $stagingRoot = '';
        $targetRoot = '';
        $backupRoot = '';
        $wasInstalled = false;
        $targetChanged = false;
        $setupSucceeded = false;

        try{
            $extension = strtolower((string)$file->getOriginalExtension());
            if($extension !== 'zip'){
                return json(['status' => 0, 'msg' => '只支持导入 .zip 插件包']);
            }

            $runtimeRoot = $this->getRuntimeRootPath();
            $tmpRoot = $runtimeRoot . 'plugin_import/' . date('YmdHis') . '_' . mt_rand(1000, 9999) . '/';
            $uploadDir = $tmpRoot . 'upload/';
            $extractDir = $tmpRoot . 'extract/';
            $this->ensureDir($uploadDir);
            $this->ensureDir($extractDir);

            $zipName = 'plugin.zip';
            if(!$file->move($uploadDir, $zipName)){
                throw new \Exception('插件包保存失败');
            }

            $zipPath = $uploadDir . $zipName;
            $this->safeExtractZip($zipPath, $extractDir);

            $pluginRoot = $this->findPluginRootInExtractedDir($extractDir);
            $pluginInfo = $this->readPluginConfig($pluginRoot);
            $pluginName = $this->resolveImportedPluginName($pluginInfo, $pluginRoot);

            $pluginBasePath = $this->getPluginBasePath();
            $this->ensureDir($pluginBasePath);

            $targetRoot = $pluginBasePath . $pluginName;
            $stagingRoot = $pluginBasePath . '.importing_' . $pluginName . '_' . date('YmdHis') . '_' . mt_rand(1000, 9999);
            $wasInstalled = $this->isPluginInstalled($pluginName);
            $installedVersion = $this->getInstalledVersion($pluginName);

            $this->copyDir($pluginRoot, $stagingRoot);
            if(!file_exists($stagingRoot . '/plugin.json')){
                throw new \Exception('插件包缺少 plugin.json');
            }

            if(is_dir($targetRoot)){
                $backupDir = $runtimeRoot . 'plugin_import_backup/';
                $this->ensureDir($backupDir);
                $backupRoot = $backupDir . $pluginName . '_' . date('YmdHis') . '_' . mt_rand(1000, 9999);
                if(!rename($targetRoot, $backupRoot)){
                    throw new \Exception('备份原插件目录失败');
                }
            }

            if(!rename($stagingRoot, $targetRoot)){
                if($backupRoot !== '' && is_dir($backupRoot)){
                    rename($backupRoot, $targetRoot);
                    $backupRoot = '';
                }
                throw new \Exception('写入插件目录失败');
            }
            $targetChanged = true;
            $this->invalidatePhpFiles($targetRoot);

            $setupResult = $this->runImportedPluginSetup($pluginName, $wasInstalled, $installedVersion);
            $setupSucceeded = true;

            if($backupRoot !== '' && is_dir($backupRoot)){
                $this->removeDir($backupRoot);
            }

            return json([
                'status' => 1,
                'msg' => $setupResult['msg'],
                'data' => [
                    'plugin_name' => $pluginName,
                    'title' => $setupResult['title'],
                    'version' => $setupResult['version'],
                    'action' => $setupResult['action'],
                    'was_installed' => $wasInstalled ? 1 : 0
                ]
            ]);
        }catch(\Exception $e){
            if($targetChanged && !$setupSucceeded){
                if($targetRoot !== '' && is_dir($targetRoot)){
                    $this->removeDir($targetRoot);
                }
                if($backupRoot !== '' && is_dir($backupRoot)){
                    rename($backupRoot, $targetRoot);
                }
            }

            return json(['status' => 0, 'msg' => '导入失败：' . $e->getMessage()]);
        }finally{
            if($stagingRoot !== '' && is_dir($stagingRoot)){
                $this->removeDir($stagingRoot);
            }
            if($tmpRoot !== '' && is_dir($tmpRoot)){
                $this->removeDir($tmpRoot);
            }
        }
    }

    /**
     * 安装/升级后同步 use_vue 到 admin_plugin_menu / admin_plugin_version
     */
    private function syncUseVueFlag(string $pluginName, int $useVue): void
    {
        try {
            // admin_plugin_menu.use_vue
            if ($this->checkTableExists('admin_plugin_menu') && $this->checkColumnExists('admin_plugin_menu', 'use_vue')) {
                $prefix = config('database.connections.mysql.prefix');
                $sql = "UPDATE `{$prefix}admin_plugin_menu` SET `use_vue` = ? WHERE `plugin_name` = ?";
                Db::execute($sql, [$useVue, $pluginName]);
            }

            // admin_plugin_version.use_vue
            if ($this->checkTableExists('admin_plugin_version') && $this->checkColumnExists('admin_plugin_version', 'use_vue')) {
                $versionTable = Db::name('admin_plugin_version');
                $exists = $versionTable->where('plugin_name', $pluginName)->find();
                if ($exists) {
                    $versionTable->where('plugin_name', $pluginName)->update(['use_vue' => $useVue]);
                } else {
                    $versionTable->insert([
                        'plugin_name' => $pluginName,
                        'version' => '1.0.0',
                        'use_vue' => $useVue,
                        'createtime' => time()
                    ]);
                }
            }
        } catch (\Exception $e) {
            // 同步失败不影响安装主流程
        }
    }
    
    /**
     * 卸载插件
     */
    public function uninstall(){
        $pluginName = input('post.plugin_name');
        if(empty($pluginName)){
            return json(['status' => 0, 'msg' => '插件名称不能为空']);
        }
        
        try{
            // 执行插件的uninstall方法
            $pluginClass = "\\app\\plugin\\{$pluginName}\\Plugin";
            if(class_exists($pluginClass)){
                $plugin = new $pluginClass();
                if(method_exists($plugin, 'uninstall')){
                    $plugin->uninstall();
                }
            }

            // 清理插件相关数据（避免“已卸载但授权/统计仍在”）
            if($this->checkTableExists('admin_plugin_license')){
                Db::name('admin_plugin_license')
                    ->where('plugin_name', $pluginName)
                    ->delete();
            }

            if($this->checkTableExists('admin_plugin_menu')){
                // 删除插件菜单（admin_plugin_menu 为全局表）
                Db::name('admin_plugin_menu')
                    ->where('plugin_name', $pluginName)
                    ->delete();
            }

            if($this->checkTableExists('admin_plugin_version')){
                Db::name('admin_plugin_version')
                    ->where('plugin_name', $pluginName)
                    ->delete();
            }

            // 清理插件教程数据
            $this->cleanPluginTutorials($pluginName);

            // 清除缓存
            \think\facade\Cache::clear();
            
            return json(['status' => 1, 'msg' => '插件卸载成功']);
        }catch(\Exception $e){
            return json(['status' => 0, 'msg' => '卸载失败：' . $e->getMessage()]);
        }
    }
    
    /**
     * 启用插件
     * 禁用入口已移除，保留该接口用于恢复历史已禁用插件。
     */
    public function toggleStatus(){
        $pluginName = input('post.plugin_name');
        $status = input('post.status/d', 1);
        
        if(empty($pluginName)){
            return json(['status' => 0, 'msg' => '插件名称不能为空']);
        }

        if(intval($status) !== 1){
            return json(['status' => 0, 'msg' => '插件禁用功能已移除']);
        }
        
        try{
            Db::name('admin_plugin_menu')
                ->where('plugin_name', $pluginName)
                ->update(['status' => 1]);

            \think\facade\Cache::clear();
            
            return json(['status' => 1, 'msg' => '插件已启用']);
        }catch(\Exception $e){
            return json(['status' => 0, 'msg' => '操作失败：' . $e->getMessage()]);
        }
    }
    
    /**
     * 切换插件页面类型（Vue3 / 旧版HTML）
     */
    public function toggleUseVue(){
        $pluginName = input('post.plugin_name');
        $useVue = input('post.use_vue/d', 0);
        
        if(empty($pluginName)){
            return json(['status' => 0, 'msg' => '插件名称不能为空']);
        }

        $pluginInfo = $this->getPluginInfo($pluginName);
        if($useVue === 1 && !$this->pluginSupportsVue($pluginInfo)){
            return json(['status' => 0, 'msg' => '该插件当前只支持旧版 HTML 页面，不能切换到 Vue 页面']);
        }
        
        try{
            if($this->checkTableExists('admin_plugin_menu') && $this->checkColumnExists('admin_plugin_menu', 'use_vue')){
                $prefix = config('database.connections.mysql.prefix');
                $sql = "UPDATE `{$prefix}admin_plugin_menu` SET `use_vue` = ? WHERE `plugin_name` = ?";
                Db::execute($sql, [$useVue, $pluginName]);
            }

            // 同步 admin_plugin_version 表，供站点后台识别
            if($this->checkTableExists('admin_plugin_version') && $this->checkColumnExists('admin_plugin_version', 'use_vue')){
                $versionTable = Db::name('admin_plugin_version');
                $exists = $versionTable->where('plugin_name', $pluginName)->find();
                if($exists){
                    $versionTable->where('plugin_name', $pluginName)->update([
                        'use_vue' => $useVue,
                        'version' => $pluginInfo['version'] ?? ($exists['version'] ?? '1.0.0'),
                        'updatetime' => time()
                    ]);
                }else{
                    $versionTable->insert([
                        'plugin_name' => $pluginName,
                        'version' => $pluginInfo['version'] ?? '1.0.0',
                        'use_vue' => $useVue,
                        'createtime' => time(),
                        'updatetime' => time()
                    ]);
                }
            }

            \think\facade\Cache::clear();
            
            $msg = $useVue == 1 ? '已切换为 Vue3 页面' : '已切换为旧版 HTML 页面';
            return json(['status' => 1, 'msg' => $msg]);
        }catch(\Exception $e){
            return json(['status' => 0, 'msg' => '切换失败：' . $e->getMessage()]);
        }
    }
    
    /**
     * 升级插件
     */
    public function upgrade(){
        $pluginName = input('post.plugin_name');
        if(empty($pluginName)){
            return json(['status' => 0, 'msg' => '插件名称不能为空']);
        }
        
        try{
            // 确保版本表存在
            $this->ensureVersionTableExists();
            
            // 检查插件是否已安装
            $isInstalled = false;
            if($this->checkTableExists('admin_plugin_menu')){
                $isInstalled = Db::name('admin_plugin_menu')
                    ->where('plugin_name', $pluginName)
                    ->count() > 0;
            }
            
            if(!$isInstalled){
                return json(['status' => 0, 'msg' => '插件未安装，请先安装插件']);
            }
            
            // 获取当前安装的版本
            $installedVersion = $this->getInstalledVersion($pluginName);
            if(empty($installedVersion)){
                $installedVersion = '1.0.0';
                $this->savePluginVersion($pluginName, $installedVersion);
            }
            
            // 获取插件配置中的版本
            $pluginInfo = $this->getPluginInfo($pluginName);
            $newVersion = $pluginInfo['version'] ?? '1.0.0';
            
            // 检查版本
            if(!version_compare($newVersion, $installedVersion, '>')){
                return json(['status' => 0, 'msg' => '当前已是最新版本']);
            }
            
            // 执行升级
            $pluginClass = "\\app\\plugin\\{$pluginName}\\Plugin";
            if(class_exists($pluginClass)){
                $plugin = new $pluginClass();
                
                if(method_exists($plugin, 'upgrade')){
                    $result = $plugin->upgrade($installedVersion, $newVersion);
                    if(isset($result['status']) && $result['status'] == 0){
                        return json(['status' => 0, 'msg' => $result['msg'] ?? '升级失败']);
                    }
                }
            }
            
            // 更新菜单（如果菜单有变化）
            \app\admin\common\PluginMenu::registerFromConfig($pluginName);

            // 升级后同步 use_vue，避免历史记录保留旧值
            $useVueFromConfig = $this->pluginSupportsVue($pluginInfo) ? intval($pluginInfo['use_vue'] ?? 0) : 0;
            $this->syncUseVueFlag($pluginName, $useVueFromConfig);
            
            // 对于没有菜单配置的插件（如地址库），需要手动创建菜单记录
            $pluginInfo = $this->getPluginInfo($pluginName);
            $menus = $pluginInfo['menus'] ?? $pluginInfo['menu'] ?? [];
            if(empty($menus)){
                // 获取所有站点 aid（注意：admin_set.id 不是业务 aid）
                $allAids = Db::name('admin_set')
                    ->where('aid', '>', 0)
                    ->column('aid');
                
                // 如果没有商户，至少为 aid=0 创建一条记录
                if(empty($allAids)){
                    $allAids = [0];
                }
                
                // 为每个商户创建菜单记录（如果还没有）
                $parentMenu = Db::name('admin_menu')
                    ->where('key', 'plugin')
                    ->where('status', 1)
                    ->find();
                $pid = $parentMenu ? $parentMenu['id'] : 0;
                
                foreach($allAids as $aid){
                    $exists = Db::name('admin_plugin_menu')
                        ->where('aid', $aid)
                        ->where('plugin_name', $pluginName)
                        ->count();
                    
                    if($exists == 0){
                        Db::name('admin_plugin_menu')->insert([
                            'aid' => $aid,
                            'pid' => $pid,
                            'plugin_name' => $pluginName,
                            'key' => '',
                            'name' => $pluginInfo['title'] ?? $pluginName,
                            'path' => '',
                            'icon' => $pluginInfo['icon'] ?? '',
                            'sort' => 0,
                            'status' => 1,
                            'createtime' => time()
                        ]);
                    }
                }
            }
            
            // 更新版本记录
            $this->savePluginVersion($pluginName, $newVersion);
            
            // 清除缓存
            \think\facade\Cache::clear();
            
            return json([
                'status' => 1,
                'msg' => "插件升级成功！版本: {$installedVersion} → {$newVersion}"
            ]);
        }catch(\Exception $e){
            return json(['status' => 0, 'msg' => '升级失败：' . $e->getMessage()]);
        }
    }
    
    // ==================== 私有方法 ====================

    private function getPluginBasePath(): string
    {
        return rtrim(app()->getRootPath(), '/\\') . '/app/plugin/';
    }

    private function getRuntimeRootPath(): string
    {
        return rtrim(app()->getRootPath(), '/\\') . '/runtime/';
    }

    private function ensureDir(string $dir): void
    {
        if(is_dir($dir)){
            return;
        }

        if(!mkdir($dir, 0755, true) && !is_dir($dir)){
            throw new \Exception('创建目录失败：' . $dir);
        }
    }

    private function safeExtractZip(string $zipPath, string $targetDir): void
    {
        if(!class_exists('\\ZipArchive')){
            throw new \Exception('当前 PHP 环境未启用 ZipArchive，无法解压插件包');
        }

        $zip = new \ZipArchive();
        $openResult = $zip->open($zipPath);
        if($openResult !== true){
            throw new \Exception('插件包打开失败');
        }

        for($i = 0; $i < $zip->numFiles; $i++){
            $entryName = $zip->getNameIndex($i);
            $normalized = str_replace('\\', '/', (string)$entryName);
            if($normalized === '' || strpos($normalized, "\0") !== false){
                $zip->close();
                throw new \Exception('插件包内存在非法文件名');
            }

            if($normalized[0] === '/' || preg_match('#^[a-zA-Z]:/#', $normalized) || preg_match('#(^|/)\.\.(/|$)#', $normalized)){
                $zip->close();
                throw new \Exception('插件包内存在非法路径：' . $entryName);
            }
        }

        if(!$zip->extractTo($targetDir)){
            $zip->close();
            throw new \Exception('插件包解压失败');
        }

        $zip->close();
    }

    private function findPluginRootInExtractedDir(string $extractDir): string
    {
        $matches = [];
        $queue = [[$extractDir, 0]];

        while(!empty($queue)){
            [$currentDir, $depth] = array_shift($queue);
            if(file_exists($currentDir . '/plugin.json')){
                $matches[] = $currentDir;
                continue;
            }

            if($depth >= 4){
                continue;
            }

            $items = scandir($currentDir);
            if($items === false){
                continue;
            }

            foreach($items as $item){
                if($item === '.' || $item === '..' || $item === '__MACOSX'){
                    continue;
                }

                $path = $currentDir . '/' . $item;
                if(is_dir($path) && !is_link($path)){
                    $queue[] = [$path, $depth + 1];
                }
            }
        }

        if(empty($matches)){
            throw new \Exception('插件包内未找到 plugin.json');
        }

        if(count($matches) > 1){
            throw new \Exception('插件包内发现多个 plugin.json，请只上传单个插件包');
        }

        return $matches[0];
    }

    private function readPluginConfig(string $pluginRoot): array
    {
        $configFile = $pluginRoot . '/plugin.json';
        if(!file_exists($configFile)){
            throw new \Exception('插件包缺少 plugin.json');
        }

        $content = file_get_contents($configFile);
        $config = json_decode($content ?: '', true);
        if(!is_array($config)){
            throw new \Exception('plugin.json 格式不正确');
        }

        return $config;
    }

    private function resolveImportedPluginName(array $pluginInfo, string $pluginRoot): string
    {
        $pluginName = trim((string)($pluginInfo['name'] ?? basename($pluginRoot)));
        if($pluginName === ''){
            throw new \Exception('plugin.json 缺少 name');
        }

        if(!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $pluginName)){
            throw new \Exception('插件标识只能使用字母、数字、下划线，并且必须以字母开头');
        }

        return $pluginName;
    }

    private function copyDir(string $source, string $target): void
    {
        if(!is_dir($source)){
            throw new \Exception('源插件目录不存在');
        }

        $this->ensureDir($target);
        $items = scandir($source);
        if($items === false){
            throw new \Exception('读取源插件目录失败');
        }

        foreach($items as $item){
            if($item === '.' || $item === '..'){
                continue;
            }

            $from = $source . '/' . $item;
            $to = $target . '/' . $item;

            if(is_link($from)){
                throw new \Exception('插件包不支持软链接文件');
            }

            if(is_dir($from)){
                $this->copyDir($from, $to);
            }elseif(is_file($from)){
                if(!copy($from, $to)){
                    throw new \Exception('复制插件文件失败：' . $item);
                }
            }
        }
    }

    private function removeDir(string $path): void
    {
        if($path === '' || $path === '/' || !file_exists($path)){
            return;
        }

        if(is_link($path) || is_file($path)){
            @unlink($path);
            return;
        }

        $items = scandir($path);
        if($items !== false){
            foreach($items as $item){
                if($item === '.' || $item === '..'){
                    continue;
                }
                $this->removeDir($path . '/' . $item);
            }
        }

        @rmdir($path);
    }

    private function invalidatePhpFiles(string $dir): void
    {
        if(!function_exists('opcache_invalidate') || !is_dir($dir)){
            return;
        }

        $items = scandir($dir);
        if($items === false){
            return;
        }

        foreach($items as $item){
            if($item === '.' || $item === '..'){
                continue;
            }

            $path = $dir . '/' . $item;
            if(is_dir($path) && !is_link($path)){
                $this->invalidatePhpFiles($path);
            }elseif(is_file($path) && strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'php'){
                @opcache_invalidate($path, true);
            }
        }
    }

    private function isPluginInstalled(string $pluginName): bool
    {
        try{
            if($this->checkTableExists('admin_plugin_version')){
                $versionExists = Db::name('admin_plugin_version')
                    ->where('plugin_name', $pluginName)
                    ->count() > 0;
                if($versionExists){
                    return true;
                }
            }

            if($this->checkTableExists('admin_plugin_menu')){
                return Db::name('admin_plugin_menu')
                    ->where('plugin_name', $pluginName)
                    ->count() > 0;
            }
        }catch(\Exception $e){
            return false;
        }

        return false;
    }

    private function runImportedPluginSetup(string $pluginName, bool $wasInstalled, string $installedVersion): array
    {
        $this->ensureVersionTableExists();
        $pluginInfo = $this->getPluginInfo($pluginName);
        if(empty($pluginInfo)){
            throw new \Exception('读取插件配置失败');
        }

        $title = $pluginInfo['title'] ?? $pluginName;
        $newVersion = $pluginInfo['version'] ?? '1.0.0';
        $pluginClass = "\\app\\plugin\\{$pluginName}\\Plugin";

        if($wasInstalled){
            if($installedVersion === ''){
                $installedVersion = '1.0.0';
            }

            if(class_exists($pluginClass) && version_compare($newVersion, $installedVersion, '>')){
                $plugin = new $pluginClass();
                if(method_exists($plugin, 'upgrade')){
                    $result = $plugin->upgrade($installedVersion, $newVersion);
                    if(is_array($result) && isset($result['status']) && intval($result['status']) === 0){
                        throw new \Exception($result['msg'] ?? '插件升级失败');
                    }
                }
            }

            \app\admin\common\PluginMenu::registerFromConfig($pluginName);
            $this->savePluginVersion($pluginName, $newVersion);

            $useVueFromConfig = $this->pluginSupportsVue($pluginInfo) ? intval($pluginInfo['use_vue'] ?? 0) : 0;
            $this->syncUseVueFlag($pluginName, $useVueFromConfig);
            $this->ensurePluginMenuRecords($pluginName, $pluginInfo, $useVueFromConfig);

            \think\facade\Cache::clear();

            $this->seedPluginTutorials($pluginName);

            $action = version_compare($newVersion, $installedVersion, '>') ? 'upgraded' : 'synced';
            $msg = $action === 'upgraded'
                ? "插件导入并升级成功：{$title} v{$installedVersion} → v{$newVersion}"
                : "插件导入成功：{$title} v{$newVersion}，已覆盖文件并同步菜单";

            return [
                'action' => $action,
                'title' => $title,
                'version' => $newVersion,
                'msg' => $msg
            ];
        }

        \app\admin\common\PluginMenu::registerFromConfig($pluginName);

        if(class_exists($pluginClass)){
            $plugin = new $pluginClass();
            if(method_exists($plugin, 'install')){
                $result = $plugin->install();
                if(is_array($result) && isset($result['status']) && intval($result['status']) === 0){
                    throw new \Exception($result['msg'] ?? '插件安装失败');
                }
            }
        }

        $this->savePluginVersion($pluginName, $newVersion);

        $useVueFromConfig = $this->pluginSupportsVue($pluginInfo) ? intval($pluginInfo['use_vue'] ?? 0) : 0;
        $this->syncUseVueFlag($pluginName, $useVueFromConfig);
        $this->ensurePluginMenuRecords($pluginName, $pluginInfo, $useVueFromConfig);
        $this->seedPluginTutorials($pluginName);

        \think\facade\Cache::clear();

        return [
            'action' => 'installed',
            'title' => $title,
            'version' => $newVersion,
            'msg' => "插件导入并安装成功：{$title} v{$newVersion}"
        ];
    }

    private function ensurePluginMenuRecords(string $pluginName, array $pluginInfo, int $useVue): void
    {
        if(!$this->checkTableExists('admin_plugin_menu')){
            return;
        }

        if($this->checkTableExists('admin_set')){
            $allAids = Db::name('admin_set')
                ->where('aid', '>', 0)
                ->column('aid');
        }else{
            $allAids = [];
        }

        if(empty($allAids)){
            $allAids = [0];
        }

        $pid = $this->getOrCreatePluginParentMenu();
        $hasUseVueColumn = $this->checkColumnExists('admin_plugin_menu', 'use_vue');

        foreach($allAids as $aid){
            $count = intval(Db::name('admin_plugin_menu')
                ->where('aid', $aid)
                ->where('plugin_name', $pluginName)
                ->count());

            // 已有完整菜单树则跳过；缺失或仅父级残桩时走 registerFromConfig 全量注册
            if($count > 1){
                continue;
            }

            try {
                \app\admin\common\PluginMenu::registerFromConfig($pluginName, intval($aid));
            } catch (\Throwable $e) {
                if($count > 0){
                    continue;
                }

                $record = [
                    'aid' => $aid,
                    'pid' => $pid,
                    'plugin_name' => $pluginName,
                    'key' => '',
                    'name' => $pluginInfo['title'] ?? $pluginName,
                    'path' => '',
                    'icon' => $pluginInfo['icon'] ?? '',
                    'sort' => 0,
                    'status' => 1,
                    'createtime' => time()
                ];

                if($hasUseVueColumn){
                    $record['use_vue'] = $useVue;
                }

                Db::name('admin_plugin_menu')->insert($record);
            }
        }
    }
    
    /**
     * 扫描插件目录
     */
    private function scanPlugins(){
        $pluginPath = app()->getRootPath() . 'app/plugin/';
        $plugins = [];
        
        if(!is_dir($pluginPath)){
            return $plugins;
        }
        
        $dirs = scandir($pluginPath);
        foreach($dirs as $dir){
            if($dir == '.' || $dir == '..') continue;
            
            $dirPath = $pluginPath . $dir;
            if(!is_dir($dirPath)) continue;
            
            $configFile = $dirPath . '/plugin.json';
            if(file_exists($configFile)){
                $content = file_get_contents($configFile);
                $config = json_decode($content, true);
                if($config && is_array($config)){
                    if(!PluginCategoryRegistry::isScannablePluginDirectory($dir, $config)){
                        continue;
                    }
                    if(!isset($config['name'])) $config['name'] = $dir;
                    if(!isset($config['title'])) $config['title'] = $dir;
                    if(!isset($config['description'])) $config['description'] = '';
                    if(!isset($config['version'])) $config['version'] = '1.0.0';
                    if(!isset($config['author'])) $config['author'] = '';

                    $config = PluginCategoryRegistry::normalizePluginConfig($config, $dir);
                    $plugins[] = $config;
                }
            }
        }
        
        return $plugins;
    }
    
    /**
     * 获取插件信息
     */
    private function getPluginInfo($pluginName){
        $configFile = app()->getRootPath() . "app/plugin/{$pluginName}/plugin.json";
        if(!file_exists($configFile)){
            return [];
        }
        
        $content = file_get_contents($configFile);
        $config = json_decode($content, true);
        if(!$config || !is_array($config)){
            return [];
        }

        return PluginCategoryRegistry::normalizePluginConfig($config, $pluginName);
    }

    private function pluginSupportsVue(array $pluginInfo): int
    {
        if(array_key_exists('supports_vue', $pluginInfo)){
            return intval($pluginInfo['supports_vue']) === 1 ? 1 : 0;
        }

        return 1;
    }
    
    /**
     * 获取已安装的插件版本
     */
    private function getInstalledVersion($pluginName){
        try{
            if(!$this->checkTableExists('admin_plugin_version')){
                return '';
            }
            
            $version = Db::name('admin_plugin_version')
                ->where('plugin_name', $pluginName)
                ->value('version');
            
            return $version ?: '';
        }catch(\Exception $e){
            return '';
        }
    }
    
    /**
     * 保存插件版本
     */
    private function savePluginVersion($pluginName, $version){
        try{
            $exists = Db::name('admin_plugin_version')
                ->where('plugin_name', $pluginName)
                ->find();
            
            if($exists){
                Db::name('admin_plugin_version')
                    ->where('plugin_name', $pluginName)
                    ->update([
                        'version' => $version,
                        'updatetime' => time()
                    ]);
            }else{
                Db::name('admin_plugin_version')->insert([
                    'plugin_name' => $pluginName,
                    'version' => $version,
                    'createtime' => time(),
                    'updatetime' => time()
                ]);
            }
        }catch(\Exception $e){
            // 忽略错误
        }
    }
    
    /**
     * 检查数据表是否存在
     */
    private function checkTableExists($tableName){
        try{
            $prefix = config('database.connections.mysql.prefix');
            $fullTableName = $prefix . $tableName;
            $result = Db::query("SHOW TABLES LIKE '{$fullTableName}'");
            return !empty($result);
        }catch(\Exception $e){
            return false;
        }
    }
    
    /**
     * 确保版本表存在
     */
    private function ensureVersionTableExists(){
        try{
            if(!$this->checkTableExists('admin_plugin_version')){
                $this->createVersionTable();
            }
        }catch(\Exception $e){
            // 忽略错误
        }
    }
    
    /**
     * 创建插件版本表
     */
    private function createVersionTable(){
        try{
            $prefix = config('database.connections.mysql.prefix');
            $sql = "CREATE TABLE IF NOT EXISTS `{$prefix}admin_plugin_version` (
              `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '主键ID',
              `plugin_name` varchar(50) NOT NULL DEFAULT '' COMMENT '插件名称',
              `version` varchar(20) NOT NULL DEFAULT '1.0.0' COMMENT '插件版本',
              `createtime` int(11) NOT NULL DEFAULT '0' COMMENT '创建时间',
              `updatetime` int(11) NOT NULL DEFAULT '0' COMMENT '更新时间',
              PRIMARY KEY (`id`),
              UNIQUE KEY `plugin_name` (`plugin_name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='插件版本表（总控台全局）'";
            
            Db::execute($sql);
            return true;
        }catch(\Exception $e){
            throw new \Exception("创建插件版本表失败: " . $e->getMessage());
        }
    }
    
    /**
     * 检查字段是否存在
     */
    private function checkColumnExists($tableName, $columnName){
        try{
            $prefix = config('database.connections.mysql.prefix');
            $fullTableName = $prefix . $tableName;
            $result = Db::query("SHOW COLUMNS FROM `{$fullTableName}` LIKE '{$columnName}'");
            return !empty($result);
        }catch(\Exception $e){
            return false;
        }
    }
    
    /**
     * 获取或创建插件父菜单ID
     */
    private function getOrCreatePluginParentMenu(){
        // 先从 admin_menu 表中查找"插件管理"菜单
        $parentMenu = Db::name('admin_menu')
            ->where('key', 'plugin')
            ->where('status', 1)
            ->find();

        if($parentMenu){
            return $parentMenu['id'];
        }

        // 如果不存在，创建一个"插件管理"菜单
        $menuId = Db::name('admin_menu')->insertGetId([
            'pid' => 0,
            'key' => 'plugin',
            'name' => '插件管理',
            'path' => '',
            'icon' => 'puzzle-piece',
            'sort' => 999,
            'status' => 1,
            'admin_only' => 0,
            'merchant_visible' => 1,
            'tuanzhang_visible' => 0,
            'createtime' => time()
        ]);

        return $menuId;
    }

    /**
     * 自动导入插件教程到帮助中心
     * 读取 app/plugin/{name}/database/tutorials.json，幂等写入 help_center_category + help_center_item
     */
    private function seedPluginTutorials(string $pluginName): void
    {
        (new \app\admin\common\HelpCenterTutorialSyncService())->syncPlugin($pluginName);
    }

    /**
     * 卸载时清理插件教程数据
     */
    private function cleanPluginTutorials(string $pluginName): void
    {
        try {
            if (!$this->checkTableExists('help_center_item') || !$this->checkTableExists('help_center_category')) {
                return;
            }

            // 删除该插件的教程项目及其进度
            $itemIds = Db::name('help_center_item')
                ->where('plugin_name', $pluginName)
                ->column('id');

            if (!empty($itemIds)) {
                Db::name('help_center_item')->where('id', 'in', $itemIds)->delete();
                if ($this->checkTableExists('help_center_progress')) {
                    Db::name('help_center_progress')->where('item_id', 'in', $itemIds)->delete();
                }
            }

            // 删除该插件独占的分类（仅删除 plugin_name 精确匹配的）
            Db::name('help_center_category')
                ->where('plugin_name', $pluginName)
                ->delete();

        } catch (\Exception $e) {
            \think\facade\Log::warning('cleanPluginTutorials failed for ' . $pluginName . ': ' . $e->getMessage());
        }
    }
}
