<?php

namespace app\admin\common;

class PluginCategoryRegistry
{
    private const CATEGORY_LABELS = [
        'ai' => 'AI智能',
        'content' => '内容运营',
        'marketing' => '营销活动',
        'trade' => '交易订单',
        'industry' => '行业应用',
        'member' => '分销成长',
        'system' => '系统工具',
        'other' => '未分类',
    ];

    private const CATEGORY_ALIASES = [
        'ai' => 'ai',
        'ai智能' => 'ai',
        '智能' => 'ai',
        'content' => 'content',
        '内容运营' => 'content',
        '内容知识' => 'content',
        '内容' => 'content',
        'marketing' => 'marketing',
        '营销活动' => 'marketing',
        '营销' => 'marketing',
        'trade' => 'trade',
        '交易订单' => 'trade',
        '交易' => 'trade',
        'industry' => 'industry',
        '行业应用' => 'industry',
        '行业' => 'industry',
        'member' => 'member',
        '分销成长' => 'member',
        '分销' => 'member',
        'system' => 'system',
        '系统工具' => 'system',
        '系统' => 'system',
        'other' => 'other',
        '未分类' => 'other',
    ];

    private const EXACT_PLUGIN_GROUPS = [
        'ai' => [
            'aitouchshare', 'aliyun-ocr', 'arscene', 'coze', 'eduadvisor', 'geooptimizer',
            'maosuanming', 'model3d', 'panorama', 'psytest', 'websiteaistudio', 'wuxing',
            'zhuibaoagent',
        ],
        'content' => [
            'article', 'daihuobiji', 'dailyjinju', 'danshujiang', 'kecheng', 'live',
            'luntan', 'messagenotify', 'mingpian', 'shortvideo', 'websitemanage',
            'xiaohongshu',
        ],
        'marketing' => [
            'activity', 'allianceactivity', 'collage', 'dashang', 'hbtk', 'holiday', 'kanjia',
            'levelreward', 'luckycollage', 'lipin', 'lipin2', 'paidui', 'redpacket', 'rewardlimit',
            'scorehuang', 'seckill', 'sign', 'sweepstakes', 'task', 'teamlottery', 'tenstar',
            'timedlottery', 'toupiao', 'tuangou', 'videoreward', 'ywyma', 'jietiquanfan',
        ],
        'trade' => [
            'cashier', 'daojia', 'ershou', 'express100', 'hotel', 'jishou', 'maidan',
            'maiyatian', 'peisong', 'periodicservice', 'restaurant', 'shoumai', 'theater',
            'venues', 'warehouse', 'yunkucungoods', 'yuyue',
        ],
        'industry' => [
            'cityagent', 'citytelbook', 'evaluation', 'exam', 'fangyuan', 'form', 'huizhan',
            'property', 'salescrm', 'shangxiang', 'shejian', 'shiftmaster', 'tiaojie',
            'tuozhancrm', 'workorder', 'zhaopin',
        ],
        'member' => [
            'bpv', 'chuangyezhi', 'cycle', 'deposit', 'equitypool', 'fenhongdian',
            'fenqifuli', 'gongxianzhi', 'hgb', 'jiedianmanage', 'jietikaohe', 'jifenchi',
            'jingtaitouzi', 'jiuxingcore', 'liandong', 'marketperformancereward', 'matrixreward', 'tuandui',
            'tuozhanyuan', 'yuefenhong', 'zhoufenhong', 'zichanfenqi',
        ],
        'system' => [
            'address', 'area', 'clientauth', 'contract', 'cpcnpay', 'daolaos', 'filem',
            'performancecache', 'renzheng', 'shanpay', 'testdataclear', 'testdatafactory',
            'zhenxin',
        ],
    ];

    private const KEYWORD_RULES = [
        'ai' => ['ai', '智能', 'ocr', 'coze', '五行', '测评', '测算', '追爆', '数字人'],
        'content' => ['文章', '课程', '知识付费', '论坛', '短视频', '小红书', '官网', '内容', '直播', '消息', '名片'],
        'marketing' => ['营销', '活动', '抽奖', '签到', '投票', '红包', '礼包', '激励', '返利', '奖励'],
        'trade' => ['收银', '订单', '交易', '寄售', '二手', '酒店', '到家', '配送', '预约', '仓库', '演出', '场馆'],
        'industry' => ['招聘', '房源', '物业', '会展', '考试', '表单', '工单', '调解', 'crm', '场景'],
        'member' => ['分红', '积分', '团队', '股权', '创业', '贡献值', '资产', '业绩', '拓展员', '投资'],
        'system' => ['支付', '认证', '授权', '文件', '缓存', '测试数据', '开放平台', '地址库', '多城市', '合同'],
    ];

    public static function normalizePluginConfig(array $config, string $pluginName = ''): array
    {
        $resolvedName = trim($pluginName !== '' ? $pluginName : strval($config['name'] ?? ''));
        if ($resolvedName !== '' && !isset($config['name'])) {
            $config['name'] = $resolvedName;
        }

        list($categoryKey, $categoryName) = self::resolveCategory($resolvedName, $config);
        $config['category_key'] = $categoryKey;
        $config['category_name'] = $categoryName;

        return $config;
    }

    public static function isScannablePluginDirectory(string $dir, array $config = []): bool
    {
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $dir)) {
            return false;
        }
        $name = trim((string) ($config['name'] ?? ''));
        return $name === '' || $name === $dir;
    }

    public static function resolveCategory(string $pluginName, array $config = []): array
    {
        list($explicitKey, $explicitName) = self::resolveExplicitCategory($config);
        if ($explicitKey !== '') {
            return [$explicitKey, $explicitName];
        }

        $exactMap = self::getExactPluginMap();
        if ($pluginName !== '' && isset($exactMap[$pluginName])) {
            $categoryKey = $exactMap[$pluginName];
            return [$categoryKey, self::getCategoryLabel($categoryKey)];
        }

        $keywordKey = self::resolveByKeywords($pluginName, $config);
        if ($keywordKey !== '') {
            return [$keywordKey, self::getCategoryLabel($keywordKey)];
        }

        return ['other', self::getCategoryLabel('other')];
    }

    public static function getCategoryLabel(string $categoryKey): string
    {
        $categoryKey = trim($categoryKey);
        if ($categoryKey === '') {
            return self::CATEGORY_LABELS['other'];
        }

        return self::CATEGORY_LABELS[$categoryKey] ?? $categoryKey;
    }

    private static function resolveExplicitCategory(array $config): array
    {
        $rawKey = trim(strval($config['category_key'] ?? ''));
        $rawCategory = trim(strval($config['category'] ?? ''));
        $rawName = trim(strval($config['category_name'] ?? ''));

        $probe = $rawKey !== '' ? $rawKey : ($rawCategory !== '' ? $rawCategory : $rawName);
        if ($probe === '') {
            return ['', ''];
        }

        $normalizedKey = self::normalizeCategoryKey($probe);
        if ($normalizedKey !== '') {
            return [$normalizedKey, $rawName !== '' ? $rawName : self::getCategoryLabel($normalizedKey)];
        }

        return [$probe, $rawName !== '' ? $rawName : $probe];
    }

    private static function normalizeCategoryKey(string $rawKey): string
    {
        $rawKey = trim($rawKey);
        if ($rawKey === '') {
            return '';
        }

        $lowerKey = strtolower($rawKey);
        if (isset(self::CATEGORY_ALIASES[$lowerKey])) {
            return self::CATEGORY_ALIASES[$lowerKey];
        }

        if (isset(self::CATEGORY_ALIASES[$rawKey])) {
            return self::CATEGORY_ALIASES[$rawKey];
        }

        return '';
    }

    private static function resolveByKeywords(string $pluginName, array $config): string
    {
        $parts = [
            $pluginName,
            strval($config['title'] ?? ''),
            strval($config['description'] ?? ''),
        ];
        $haystack = strtolower(implode(' ', array_filter($parts)));

        foreach (self::KEYWORD_RULES as $categoryKey => $keywords) {
            foreach ($keywords as $keyword) {
                if ($keyword === '') {
                    continue;
                }
                if (strpos($haystack, strtolower($keyword)) !== false) {
                    return $categoryKey;
                }
            }
        }

        return '';
    }

    private static function getExactPluginMap(): array
    {
        $map = [];
        foreach (self::EXACT_PLUGIN_GROUPS as $categoryKey => $pluginNames) {
            foreach ($pluginNames as $pluginName) {
                $map[$pluginName] = $categoryKey;
            }
        }

        return $map;
    }
}
