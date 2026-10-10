<?php
/**
 * Vue前端菜单API
 * 用于为Vue前端提供动态菜单数据
 */

namespace app\adminapi\controller;

use app\admin\common\Menu;
use app\admin\common\MenuDatabase;
use think\facade\Db;

class VueMenu extends Common
{
    /**
     * 获取Vue前端菜单
     * 根据当前登录用户返回有权限的菜单列表
     */
    public function getMenuList()
    {
        try {
            // 进入 SaaS 站点后必须按当前站点账号加载菜单，不能沿用控制台 admin 身份。
            $uid = intval(session('ADMIN_UID') ?? 0);
            $aid = $this->resolveMenuAid($uid, intval(session('ADMIN_AID') ?? 0));

            // 检查是否使用数据库菜单模式
            $config = Db::name('admin_system_config')
                ->where('key', 'menu_use_database')
                ->find();
            $useDatabase = $config && ($config['value'] == '1' || $config['value'] == 1);

            // 根据配置选择菜单数据源
            if($useDatabase && class_exists('\\app\\admin\\common\\MenuDatabase')) {
                // 使用数据库菜单
                $menuData = MenuDatabase::getdata($aid, $uid);
            } else {
                // 使用文件菜单
                $menuData = Menu::getdata($aid, $uid);
            }
            $menuData = $this->ensureDesignerExtraMenus($menuData);

            // 转换为Vue路由格式。权限和排序仍读取数据库菜单，展示文案按当前站点自定义币种名称动态替换。
            $vueMenus = $this->convertToVueMenu($menuData, intval($aid));

            $result = [
                'menus' => $vueMenus,
                'menuCount' => count($vueMenus),
                'aid' => intval($aid),
                'uid' => intval($uid),
            ];

            return json([
                'status' => 1,
                'msg' => '获取成功',
                'data' => $result,
            ])->header([
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
                'Pragma' => 'no-cache',
            ]);

        } catch (\Throwable $e) {
            // 数据库菜单异常时仍返回正式文件菜单，避免前端落入开发期的简化兜底菜单。
            try {
                $uid = intval(session('ADMIN_UID') ?? 0);
                $aid = $this->resolveMenuAid($uid, intval(session('ADMIN_AID') ?? 0));
                $menuData = $this->ensureDesignerExtraMenus(Menu::getdata($aid, $uid));
                $vueMenus = $this->convertToVueMenu($menuData, $aid);
                return $this->success([
                    'menus' => $vueMenus,
                    'menuCount' => count($vueMenus),
                    'fallback' => 'file_menu',
                ], '数据库菜单读取异常，已使用默认菜单');
            } catch (\Throwable $fallbackError) {
                return $this->error('获取失败：' . $e->getMessage());
            }
        }
    }

    private function resolveMenuAid(int $uid, int $sessionAid = 0): int
    {
        if ($uid > 0) {
            try {
                $userAid = intval(Db::name('admin_user')->where('id', $uid)->value('aid'));
                if ($userAid > 0) {
                    return $userAid;
                }
            } catch (\Throwable $e) {
            }
        }
        if ($sessionAid > 0) {
            return $sessionAid;
        }
        return intval($this->aid ?: 0);
    }

    /**
     * 个人中心、分销中心、商品页设计从侧栏收进页面设计。
     * 文件菜单和数据库菜单都在这里去掉，避免旧数据继续出现在导航里。
     */
    private function ensureDesignerExtraMenus($menuData)
    {
        if (!is_array($menuData)) {
            return $menuData;
        }

        $hiddenPaths = [
            'DesignerPage/member' => true,
            'DesignerPage/commission' => true,
            'DesignerPage/goods' => true,
        ];
        $hiddenVuePaths = [
            'designer-page/member' => true,
            'designer-page/commission' => true,
            'designer-page/goods' => true,
        ];

        return $this->stripDesignerSceneMenus($menuData, $hiddenPaths, $hiddenVuePaths);
    }

    private function stripDesignerSceneMenus($menuData, array $hiddenPaths, array $hiddenVuePaths)
    {
        if (!is_array($menuData)) {
            return $menuData;
        }
        // 一级菜单的数组 key（如 plugin、plugin_item_xxx）会作为前端菜单 key，不能重排
        $isList = array_keys($menuData) === range(0, count($menuData) - 1);
        $menuData = array_filter($menuData, function ($menu) use ($hiddenPaths, $hiddenVuePaths) {
            if (!is_array($menu)) {
                return true;
            }
            $path = trim((string)($menu['path'] ?? ''));
            $vuePath = trim((string)($menu['vue_path'] ?? ''));
            return empty($hiddenPaths[$path]) && empty($hiddenVuePaths[$vuePath]);
        });
        if ($isList) {
            $menuData = array_values($menuData);
        }
        foreach ($menuData as &$menu) {
            if (!empty($menu['child']) && is_array($menu['child'])) {
                $menu['child'] = $this->stripDesignerSceneMenus($menu['child'], $hiddenPaths, $hiddenVuePaths);
            }
        }
        unset($menu);
        return $menuData;
    }

    /**
     * 将Menu.php的菜单格式转换为Vue路由格式
     * @param array $menuData 原始菜单数据
     * @return array Vue菜单数组
     */
    private function convertToVueMenu($menuData, int $aid = 0)
    {
        $vueMenus = [];
        $textMap = $this->getMenuTextReplacementMap($aid);
        $menuData = Menu::unifyYingxiaoPluginMenus(is_array($menuData) ? $menuData : [], $aid);
        
        // 图标映射表 - 将后端icon映射到Ant Design Vue图标
        $iconMap = [
            'my-icon-shop' => 'ShoppingOutlined',
            'my-icon-business' => 'ShopOutlined',
            'my-icon-member' => 'UserOutlined',
            'my-icon-finance' => 'AccountBookOutlined',
            'my-icon-yingxiao' => 'GiftOutlined',
            'my-icon-marketing' => 'GiftOutlined',
            'my-icon-kuozhan' => 'AppstoreOutlined',
            'my-icon-sheji' => 'BuildOutlined',
            'my-icon-pingtai' => 'CloudOutlined',
            'my-icon-sysset' => 'SettingOutlined',
            'my-icon-restaurant' => 'CoffeeOutlined',
            'my-icon-hotel' => 'HomeOutlined',
            'my-icon-interface' => 'AppstoreOutlined',
            'my-icon-component' => 'BuildOutlined',
            'my-icon-home' => 'HomeOutlined',
            'default' => 'AppstoreOutlined'
        ];
        
        // 路径映射表 - 将后端路径映射到Vue路由路径
        $pathMap = [
            'Member/index' => 'member',
            'Member/charts' => 'member/charts',  // 会员关系图
            'ShopProduct/index' => 'goods',
            'ShopOrder/index' => 'order',
            'ShopCategory/index' => 'category',
            'MemberLevel/index' => 'member-level',
            'MemberLevel/applyorder' => 'member-level/apply-orders',
            'MemberTag/index' => 'member-tag',
            'MemberManage/source' => 'member-manage',
            'RegisterForm/index' => 'register-form',
            'MemberPoster/index' => 'member-poster',
            // 财务管理
            'Money/moneylog' => 'money/moneylog',
            'Score/scorelog' => 'score/scorelog',
            'ScoreHuang/scorelog' => 'scorehuang/scorehuanglog',
            'XiaofeibLog/index' => 'xiaofeib/xiaofeiblog',
            'Money/membertransfer' => 'money/member-transfer',
            'Score/zhuanscorelog' => 'score/zhuanscorelog',
            'Money/rechargelog' => 'money/rechargelog',
            'Money/withdrawlog' => 'money/withdrawlog',
            'Money/zhuangzhang' => 'money/zhuangzhang',
            'Money/chuangyelog' => 'money/chuangyelog',
            'Money/heijifenlog' => 'money/heijifenlog',
            'Money/zijifhlog' => 'money/zijifhlog',
            'Money/giveset' => 'money/giveset',
            'Money/fxfhclog' => 'money/fxfhclog',
            'Money/syclog' => 'money/syclog',
            'Hexiao/index' => 'hexiao/index',
            'Invoice/index' => 'invoice/index',
            'Maidan/index' => 'maidan/index',
            'Payorder/index' => 'payorder/index',
            'BusinessMoney/memberindex' => 'business-money/memberindex',
            'Commission/record' => 'commission/record',
            'Commission/commissionlog' => 'commission/commissionlog',
            'Commission/withdrawlog' => 'commission/withdrawlog',
            'Commission/withdrawlogcpcn' => 'commission/withdrawlog-cpcn',
            'Commission/fenhonglog' => 'commission/fenhonglog',
            'Payorder/tongjiyue' => 'payorder/tongjiyue',
            'Payorder/caiwu' => 'payorder/caiwu',
            'Payorder/fahuomingxi' => 'payorder/fahuomingxi',
            'Membercaiwu/index' => 'membercaiwu/index',
            'Coupon/index' => 'coupon/index',
            'Manjian/set' => 'manjian/set',
            'RandomDiscount/set' => 'random-discount/set',
            'Cuxiao/index' => 'cuxiao',
            'Cashback/index' => 'cashback',
            'CollageSet/index' => 'collage-set',
            'CollageCategory/index' => 'collage-category',
            'CollageProduct/index' => 'collage-product',
            'CollageOrder/index' => 'collage-order',
            'CollageTeam/index' => 'collage-team',
            'CollageComment/index' => 'collage-comment',
            'CollagePoster/index' => 'collage-poster',
            'KanjiaProduct/index' => 'kanjia-product',
            'KanjiaSet/index' => 'kanjia-set',
            'KanjiaOrder/index' => 'kanjia-order',
            'KanjiaPoster/index' => 'kanjia-poster',
            'KanjiaCode/codelist' => 'kanjia-code',
            'SeckillProduct/index' => 'seckill-product',
            'Member/registerGive' => 'marketing/register-give',
            // 幸运拼团只走插件入口，营销菜单不再挂这些路径
            'LuckyCollageProduct/index' => '/plugin/luckycollage/product',
            'LuckyCollageOrder/index' => '/plugin/luckycollage/order',
            'LuckyCollageTeam/index' => '/plugin/luckycollage/team',
            'LuckyCollageComment/index' => '/plugin/luckycollage/comment',
            'LuckyCollageCategory/index' => '/plugin/luckycollage/category',
            'LuckyCollagePoster/index' => '/plugin/luckycollage/poster',
            'LuckyCollageJiqiren/index' => '/plugin/luckycollage/jiqiren',
            'LuckyCollageSet/index' => '/plugin/luckycollage/settings',
            // 插件路由（兜底映射：即使 admin_plugin_menu 里还没写入 vue_path，也能跳转）
            'HbtkActivity/index' => '/plugin/hbtk/activity-list',
            'HbtkActivity/record' => '/plugin/hbtk/record',
            'HbtkActivity/winrecord' => '/plugin/hbtk/win-record',
            'HbtkActivity/tuijianrecord' => '/plugin/hbtk/tuijian-record',
            'HbtkActivity/sharelog' => '/plugin/hbtk/share-log',
            'HbtkActivity/fanlilog' => '/plugin/hbtk/fanli-log',
            'HbtkActivity/viewlog' => '/plugin/hbtk/view-log',
            'HbtkPoster/index' => '/plugin/hbtk/poster',
            'Evaluation/index' => '/plugin/evaluation/project',
            'EvaluationOrganization/index' => '/plugin/evaluation/organization',
            'EvaluationOrgPage/index' => '/plugin/evaluation/org-page',
            'EvaluationAggregation/index' => '/plugin/evaluation/aggregation',
            'EvaluationAggregationGroup/index' => '/plugin/evaluation/aggregation-group',
            'EvaluationSet/index' => '/plugin/evaluation/settings',
            'RedpacketActivity/index' => '/plugin/redpacket/activity',
            'RedpacketActivity/statistics' => '/plugin/redpacket/statistics',
            'RedpacketActivity/purchaseRecords' => '/plugin/redpacket/purchase-records',
            'RedpacketActivity/checkinRecords' => '/plugin/redpacket/checkin-records',
            'RedpacketActivity/claimRecords' => '/plugin/redpacket/claim-records',
            'TaskActivity/index' => '/plugin/task/activity',
            'TaskActivity/statistics' => '/plugin/task/statistics',
            'TaskActivity/userRecords' => '/plugin/task/user-records',
            'TaskActivity/rewardRecords' => '/plugin/task/reward-records',
            'DesignerVirtualUser/index' => 'designer-virtual-user',
        ];
        
        $convertNode = function (array $menu, string $menuKey, int $order, bool $isRoot = false, string $parentKey = '') use (&$convertNode, $textMap, $pathMap, $iconMap) {
            if (!isset($menu['name']) || (!$isRoot && isset($menu['hide']) && $menu['hide'])) {
                return null;
            }

            $vueMenu = [
                'key' => $menuKey,
                'name' => $this->replaceMenuText($menu['name'], $textMap),
                'order' => $order,
            ];

            if ($isRoot) {
                $vueMenu['fullname'] = $this->replaceMenuText($menu['fullname'] ?? $menu['name'], $textMap);
                $vueMenu['icon'] = $this->mapIcon($menu['icon'] ?? '', $iconMap);
                $vueMenu['children'] = [];
            } elseif (isset($menu['icon']) && !empty($menu['icon'])) {
                $vueMenu['icon'] = $this->mapIcon($menu['icon'], $iconMap);
            }

            if ((isset($menu['path']) && $menu['path']) || !empty($menu['vue_path'])) {
                $vuePath = $this->resolveMenuPath($menu, $pathMap);
                if ($vuePath !== '') {
                    $vueMenu['path'] = $vuePath;
                }
                if (isset($menu['path']) && $menu['path']) {
                    $vueMenu['originalPath'] = $menu['path'];
                }
            }

            $hasChildrenField = $isRoot || array_key_exists('child', $menu) || array_key_exists('children', $menu);
            $children = $menu['child'] ?? $menu['children'] ?? [];
            if ($hasChildrenField && is_array($children)) {
                $vueMenu['children'] = [];
                foreach ($children as $childIndex => $child) {
                    if (!is_array($child) || !isset($child['name'])) {
                        continue;
                    }

                    $rawChildKey = $child['key'] ?? '';
                    $childKey = $rawChildKey !== ''
                        ? (string)$rawChildKey
                        : $menuKey . '_' . $childIndex;
                    $childMenu = $convertNode($child, $childKey, intval($childIndex), false, $menuKey);
                    if ($childMenu !== null) {
                        $vueMenu['children'][] = $childMenu;
                    }
                }
            }

            return $vueMenu;
        };

        $index = 0;
        foreach ($menuData as $key => $menu) {
            if (!is_array($menu) || !isset($menu['name'])) {
                continue;
            }

            // 一级菜单 key：优先使用菜单本身的 key，否则使用数组 key。
            $rawMenuKey = $menu['key'] ?? $key;
            $rawMenuKey = is_string($rawMenuKey) ? $rawMenuKey : (string)$rawMenuKey;
            $menuKey = ctype_digit($rawMenuKey) ? ('group_' . $rawMenuKey) : $rawMenuKey;
            $vueMenu = $convertNode($menu, $menuKey, $index++, true);
            if ($vueMenu !== null) {
                $vueMenus[] = $vueMenu;
            }
        }

        return $vueMenus;
    }

    /**
     * 优先使用菜单配置中的 vue_path，其次回退到 pathMap/convertPath
     * @param array $menuItem 菜单项
     * @param array $pathMap 路径映射表
     * @return string
     */
    private function resolveMenuPath(array $menuItem, array $pathMap): string
    {
        if (!empty($menuItem['path'])) {
            $businessPath = $this->resolveBusinessShowtypePath((string)$menuItem['path']);
            if ($businessPath !== '') {
                return $businessPath;
            }
        }

        if (!empty($menuItem['vue_path'])) {
            return trim((string)$menuItem['vue_path']);
        }

        if (!empty($menuItem['path'])) {
            $path = (string)$menuItem['path'];
            return $pathMap[$path] ?? $this->convertPath($path);
        }

        return '';
    }

    /**
     * 旧版 HTML 菜单用 showtype=2 区分多商户上下文。Vue 路由不能去参后复用营销路径，
     * 否则菜单选中会跳到总后台营销模块。
     */
    private function resolveBusinessShowtypePath(string $path): string
    {
        if (!preg_match('/(?:^|[?&])showtype=2(?:$|&)/', $path)) {
            return '';
        }

        $basePath = preg_split('/[?&]/', $path, 2)[0] ?? $path;
        $businessPathMap = [
            'ShopProduct/index' => 'shop-product',
            'ShopOrder/index' => 'order?showtype=2',
            'BusinessComment/index' => 'business-comment',
            'CollageProduct/index' => 'business/collage-product',
            'KanjiaProduct/index' => 'business/kanjia-product',
            'SeckillProduct/index' => 'business/seckill-product',
            'TuangouProduct/index' => 'business/tuangou-product',
            'YuyueList/index' => 'business/yuyue-list',
            'CycleProduct/index' => 'business/cycle-product',
            'Article/index' => 'business/article',
            'Shortvideo/index' => 'business/shortvideo',
            'Form/index' => 'business/form',
        ];

        return $businessPathMap[$basePath] ?? '';
    }

    /**
     * 左侧菜单从数据库读取权限和排序，但名称需要跟随后台“自定义文字”设置。
     * 这里只改接口展示文案，不回写 admin_menu，避免影响权限配置和菜单管理。
     */
    private function getMenuTextReplacementMap(int $aid): array
    {
        $textset = $this->loadMenuTextset($aid);
        $defaults = \app\admin\common\System::getDefaultTextsetConfig();
        $groups = [
            '余额' => ['余额'],
            '积分' => ['积分'],
            '积分3' => ['积分3'],
            '佣金' => ['佣金'],
            '优惠券' => ['优惠券'],
            '会员' => ['会员'],
            '现金券' => ['现金券', '成就值', '购物积分', '补贴券'],
            '贡献值' => ['贡献值'],
            '配资' => ['配资'],
            '消费币' => ['消费币', '消费券'],
            '绿积分' => ['绿积分', '绿色积分', '利积分'],
            '黄积分' => ['黄积分', '金元宝', '黄金分', '黄积分'],
            '虚拟账号积分' => ['虚拟账号积分'],
            '创业值' => ['创业值'],
            '收益池' => ['收益池'],
        ];

        $replace = [];
        foreach ($groups as $key => $aliases) {
            $target = trim((string)($textset[$key] ?? $defaults[$key] ?? $key));
            if ($target === '') {
                continue;
            }

            $aliases[] = $key;
            if (!empty($defaults[$key])) {
                $aliases[] = (string)$defaults[$key];
            }
            if (!empty($textset[$key])) {
                $aliases[] = (string)$textset[$key];
            }

            foreach (array_unique(array_filter($aliases, 'strlen')) as $alias) {
                $alias = trim((string)$alias);
                if ($alias !== '' && $alias !== $target) {
                    $replace[$alias] = $target;
                }
            }
        }

        uksort($replace, function ($a, $b) {
            return strlen($b) <=> strlen($a);
        });

        return $replace;
    }

    private function loadMenuTextset(int $aid): array
    {
        try {
            if ($aid <= 0) {
                $aid = intval($this->aid ?: session('ADMIN_AID') ?: 0);
            }
            if ($aid <= 0) {
                return \app\admin\common\System::mergeDefaultTextsetConfig([]);
            }

            $textsetJson = Db::name('admin_set')->where('aid', $aid)->value('textset');
            $textset = json_decode((string)$textsetJson, true);
            return \app\admin\common\System::mergeDefaultTextsetConfig(is_array($textset) ? $textset : []);
        } catch (\Throwable $e) {
            return \app\admin\common\System::mergeDefaultTextsetConfig([]);
        }
    }

    private function replaceMenuText($text, array $replace): string
    {
        $text = (string)$text;
        if ($text === '' || empty($replace)) {
            return $text;
        }

        // 余额宝是独立业务名，不能因为“余额”改名而变成“xx宝”。
        $protected = [
            '余额宝' => '__MENU_TEXT_YUEBAO__',
        ];
        $text = strtr($text, $protected);
        $text = strtr($text, $replace);

        return strtr($text, array_flip($protected));
    }
    
    /**
     * 映射图标
     * @param string $icon 原始图标
     * @param array $iconMap 图标映射表
     * @return string Vue图标名称
     */
    private function mapIcon($icon, $iconMap)
    {
        $icon = trim((string)$icon);
        if ($icon === '') {
            return $iconMap['default'];
        }

        if (preg_match('/^[A-Za-z][A-Za-z0-9]+(Outlined|Filled|TwoTone)$/', $icon)) {
            return $icon;
        }

        // 提取图标类名
        if (preg_match('/my-icon-(\w+)/', $icon, $matches)) {
            $iconKey = 'my-icon-' . $matches[1];
            return $iconMap[$iconKey] ?? $iconMap['default'];
        }
        
        return $iconMap['default'];
    }
    
    /**
     * 转换路径 - 将后端路径转换为Vue路由路径
     * 例如: 
     * - ShopProduct/index -> shop-product
     * - MemberLevel/applyorder -> member-level/applyorder
     * @param string $path 原始路径
     * @return string Vue路由路径
     */
    private function convertPath($path)
    {
        // 去掉参数
        $path = explode('&', $path)[0];
        $path = explode('?', $path)[0];
        
        // 提取控制器和方法
        $parts = explode('/', $path);
        if (empty($parts[0])) {
            return 'unknown';
        }
        
        // 转换控制器名为kebab-case
        $controller = $parts[0];
        $controllerKebab = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $controller));
        
        // 如果有方法名且不是 'index'，则拼接方法名
        if (isset($parts[1]) && $parts[1] !== 'index') {
            $action = $parts[1];
            // 将方法名也转换为kebab-case
            $actionKebab = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $action));
            return $controllerKebab . '/' . $actionKebab;
        }
        
        return $controllerKebab;
    }
    
    /**
     * 获取用户权限列表
     * 返回当前用户有权限访问的所有路径
     */
    public function getPermissions()
    {
        try {
            $uid = intval(session('ADMIN_UID') ?? 0);
            $aid = $this->resolveMenuAid($uid, intval(session('ADMIN_AID') ?? 0));

            // 获取菜单数据
            $menuData = Menu::getdata($aid, $uid);

            // 提取所有有权限的路径
            $permissions = [];

            foreach ($menuData as $menu) {
                if (isset($menu['child']) && is_array($menu['child'])) {
                    foreach ($menu['child'] as $child) {
                        if (isset($child['authdata'])) {
                            // 分割authdata（可能包含多个路径，用逗号分隔）
                            $authPaths = explode(',', $child['authdata']);
                            foreach ($authPaths as $authPath) {
                                $permissions[] = trim($authPath);
                            }
                        }

                        // 处理三级菜单
                        if (isset($child['child']) && is_array($child['child'])) {
                            foreach ($child['child'] as $grandChild) {
                                if (isset($grandChild['authdata'])) {
                                    $authPaths = explode(',', $grandChild['authdata']);
                                    foreach ($authPaths as $authPath) {
                                        $permissions[] = trim($authPath);
                                    }
                                }
                            }
                        }
                    }
                }
            }

            // 去重
            $permissions = array_unique($permissions);

            return $this->success($permissions, '获取成功');

        } catch (\Exception $e) {
            return $this->error('获取失败：' . $e->getMessage());
        }
    }
}
