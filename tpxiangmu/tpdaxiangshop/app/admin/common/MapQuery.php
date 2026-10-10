<?php

namespace app\admin\common;

use think\facade\Db;

/**
 * 地图 Web 服务。逆地理、地点搜索等 HTTP 调用走这里，便于接站点额度。
 * 站点 admin_set 里有自己的 Key 时不扣平台次数。前端 JS SDK 展示地图不走这里。
 */
class MapQuery
{
    public const QUOTA_KEY = 'map';
    public const FALLBACK_QQ_KEY = 'ABLBZ-4BIKU-GFTVB-BK7IK-OLQ35-QCBFF';

    public static function resolve(int $aid = 0): array
    {
        $aid = self::aid($aid);
        $site = self::siteKeys($aid);
        $platform = self::platformKeys();
        $prefer = strtolower(trim((string)($site['map_type'] ?: $platform['map_type'] ?: 'qq')));
        if (!in_array($prefer, ['qq', 'gaode', 'baidu', 'tianditu'], true)) {
            $prefer = 'qq';
        }
        $order = [$prefer];
        foreach (['qq', 'gaode', 'baidu', 'tianditu'] as $provider) {
            if (!in_array($provider, $order, true)) {
                $order[] = $provider;
            }
        }
        foreach ($order as $provider) {
            $siteKey = self::keyOf($site, $provider);
            $platKey = self::keyOf($platform, $provider);
            if ($siteKey !== '') {
                return [
                    'provider' => $provider,
                    'key' => $siteKey,
                    'sk' => $provider === 'gaode' ? $site['gaode_sk'] : '',
                    'own' => true,
                    'aid' => $aid,
                ];
            }
            if ($platKey !== '') {
                return [
                    'provider' => $provider,
                    'key' => $platKey,
                    'sk' => $provider === 'gaode' ? $platform['gaode_sk'] : '',
                    'own' => false,
                    'aid' => $aid,
                ];
            }
        }
        return ['provider' => 'qq', 'key' => self::FALLBACK_QQ_KEY, 'sk' => '', 'own' => false, 'aid' => $aid];
    }

    public static function reverseGeocode($latitude, $longitude, string $source = 'reverse', $keyOverride = '', int $aid = 0): array
    {
        $latitude = trim((string)$latitude);
        $longitude = trim((string)$longitude);
        if ($latitude === '' || $longitude === '' || !is_numeric($latitude) || !is_numeric($longitude)) {
            return ['ok' => false, 'error' => '请输入坐标'];
        }
        $aid = self::aid($aid);
        $keyOverride = trim((string)$keyOverride);
        if ($keyOverride !== '') {
            $cred = [
                'provider' => 'qq',
                'key' => $keyOverride,
                'own' => self::isOwnCredential($keyOverride, $aid),
                'aid' => $aid,
            ];
        } else {
            $cred = self::resolve($aid);
        }
        $url = self::buildReverseUrl($cred, $latitude, $longitude);
        $fetched = self::getJson($url, [
            'aid' => $aid,
            'source' => $source,
            'biz_no' => $latitude . ',' . $longitude,
            'own_credential' => !empty($cred['own']),
            'cache_biz' => 'rev:' . round((float)$latitude, 4) . ',' . round((float)$longitude, 4),
        ]);
        if (!$fetched['ok']) {
            return ['ok' => false, 'error' => $fetched['error'] ?: '获取地址失败'];
        }
        $parsed = self::parseReverse($cred['provider'], $fetched['json']);
        if (!$parsed) {
            return ['ok' => false, 'error' => '获取地址失败'];
        }
        $parsed['ok'] = true;
        return $parsed;
    }

    public static function getLocationInfo($latitude, $longitude, $key = '')
    {
        $info = self::reverseGeocode($latitude, $longitude, 'getLocationInfo', $key);
        if (empty($info['ok'])) {
            return false;
        }
        return [
            'province' => $info['province'] ?? '',
            'city' => $info['city'] ?? '',
            'district' => $info['district'] ?? '',
        ];
    }

    public static function placeSearch($keyword, $latitude, $longitude, $page = 1, int $aid = 0): array
    {
        $keyword = trim((string)$keyword);
        $latitude = trim((string)$latitude);
        $longitude = trim((string)$longitude);
        $page = max(1, intval($page));
        if ($keyword === '' || $latitude === '' || $longitude === '') {
            return ['ok' => false, 'msg' => '缺少必要参数', 'list' => []];
        }
        $aid = self::aid($aid);
        $cred = self::resolve($aid);
        $url = self::buildPlaceSearchUrl($cred, $keyword, $latitude, $longitude, $page);
        $fetched = self::getJson($url, [
            'aid' => $aid,
            'source' => 'place_search',
            'biz_no' => $keyword,
            'own_credential' => !empty($cred['own']),
            'cache_biz' => 'ps:' . md5($cred['provider'] . '|' . $keyword . '|' . round((float)$latitude, 4) . '|' . round((float)$longitude, 4) . '|' . $page),
        ]);
        if (!$fetched['ok']) {
            return ['ok' => false, 'msg' => $fetched['error'] ?: '地图服务请求失败', 'list' => []];
        }
        $parsed = self::parsePlaceSearch($cred['provider'], $fetched['json']);
        if (!empty($parsed['error'])) {
            return ['ok' => false, 'msg' => $parsed['error'], 'list' => []];
        }
        return ['ok' => true, 'msg' => '', 'list' => $parsed['list']];
    }

    /**
     * 统一 HTTP 入口。options: aid, source, biz_no, own_credential, cache_biz
     */
    public static function getJson(string $url, array $options = []): array
    {
        $aid = self::aid(intval($options['aid'] ?? 0));
        $settings = SiteQuota::getTypeSettings(self::QUOTA_KEY);
        $safeUrl = self::stripKey($url);
        $bizNo = trim((string)($options['biz_no'] ?? $safeUrl));
        $cacheBiz = trim((string)($options['cache_biz'] ?? ('url:' . md5($safeUrl))));
        $cacheKey = SiteQuota::cacheKey(self::QUOTA_KEY, $aid, $cacheBiz);
        $source = trim((string)($options['source'] ?? 'map'));
        if (array_key_exists('own_credential', $options)) {
            $own = !empty($options['own_credential']);
        } else {
            $own = self::urlUsesSiteKey($url, $aid);
        }
        $started = (int)round(microtime(true) * 1000);
        $gate = SiteQuota::beginCall(self::QUOTA_KEY, $aid, [
            'cache_key' => $cacheKey,
            'skip_bill' => $own,
            'source' => $source,
            'biz_no' => $bizNo,
        ]);
        if (intval($gate['status'] ?? 0) !== 1) {
            $msg = (string)($gate['msg'] ?? '地图调用次数不足，请联系平台充值');
            SiteQuota::finishCall(self::QUOTA_KEY, $aid, $gate, [
                'success' => false,
                'biz_no' => $bizNo,
                'source' => $source,
                'error_msg' => $msg,
                'duration' => (int)round(microtime(true) * 1000) - $started,
            ]);
            return ['ok' => false, 'error' => $msg, 'json' => [], 'quota' => true];
        }
        if (!empty($gate['cache_hit']) && is_string($gate['cached'] ?? null) && $gate['cached'] !== '') {
            SiteQuota::finishCall(self::QUOTA_KEY, $aid, $gate, [
                'success' => true,
                'biz_no' => $bizNo,
                'source' => $source,
                'cache_key' => $cacheKey,
            ]);
            $json = json_decode($gate['cached'], true);
            return ['ok' => true, 'error' => '', 'json' => is_array($json) ? $json : [], 'http_code' => 200];
        }

        $http = self::httpGet(self::maybeSignGaode($url, $aid));
        $httpCode = intval($http['http_code'] ?? 0);
        $body = is_string($http['body'] ?? null) ? $http['body'] : '';
        $success = $httpCode === 200 && $body !== '';
        $json = $success ? (json_decode($body, true) ?: []) : [];
        $cacheTtl = 0;
        if ($success && intval($settings['cache_ttl']) > 0) {
            $cacheTtl = intval($settings['cache_ttl']);
        }
        SiteQuota::finishCall(self::QUOTA_KEY, $aid, $gate, [
            'success' => $success,
            'http_code' => $httpCode,
            'biz_no' => $bizNo,
            'source' => $source,
            'cache_key' => $cacheKey,
            'cache_value' => $success ? $body : null,
            'cache_ttl' => $cacheTtl,
            'duration' => (int)round(microtime(true) * 1000) - $started,
            'error_msg' => $success ? '' : ($httpCode ? ('HTTP ' . $httpCode) : '地图接口无响应'),
            'extra' => ['own_credential' => $own ? 1 : 0],
        ]);
        if (!$success) {
            return ['ok' => false, 'error' => '地图服务请求失败', 'json' => [], 'http_code' => $httpCode];
        }
        return ['ok' => true, 'error' => '', 'json' => is_array($json) ? $json : [], 'http_code' => $httpCode];
    }

    public static function isOwnCredential(string $key, int $aid = 0): bool
    {
        $key = trim($key);
        if ($key === '') {
            return false;
        }
        $site = self::siteKeys(self::aid($aid));
        return $key === $site['qq_key']
            || $key === $site['gaode_key']
            || $key === $site['baidu_key']
            || $key === $site['tianditu_key'];
    }

    public static function buildReverseUrl(array $cred, $latitude, $longitude): string
    {
        $key = rawurlencode((string)($cred['key'] ?? ''));
        $provider = $cred['provider'] ?? 'qq';
        if ($provider === 'gaode') {
            return "https://restapi.amap.com/v3/geocode/regeo?key={$key}&location={$longitude},{$latitude}&radius=1000&extensions=all";
        }
        if ($provider === 'baidu') {
            return "http://api.map.baidu.com/reverse_geocoding/v3/?ak={$key}&output=json&coordtype=wgs84ll&location={$latitude},{$longitude}";
        }
        if ($provider === 'tianditu') {
            $post = rawurlencode(json_encode([
                'lon' => strval($longitude),
                'lat' => strval($latitude),
                'ver' => 1,
            ], JSON_UNESCAPED_UNICODE));
            return "https://api.tianditu.gov.cn/geocoder?postStr={$post}&type=geocode&tk={$key}";
        }
        return "https://apis.map.qq.com/ws/geocoder/v1/?key={$key}&location={$latitude},{$longitude}";
    }

    public static function parseReverse(string $provider, array $result): array
    {
        if ($provider === 'gaode') {
            if (strval($result['status'] ?? '') !== '1') {
                return [];
            }
            $comp = $result['regeocode']['addressComponent'] ?? [];
            $province = trim((string)($comp['province'] ?? ''));
            $city = $comp['city'] ?? '';
            if (is_array($city)) {
                $city = '';
            }
            $city = trim((string)$city);
            if ($city === '') {
                $city = $province;
            }
            return [
                'address' => trim((string)($result['regeocode']['formatted_address'] ?? '')),
                'province' => $province,
                'city' => $city,
                'district' => trim((string)($comp['district'] ?? '')),
            ];
        }
        if ($provider === 'baidu') {
            if (intval($result['status'] ?? -1) !== 0) {
                return [];
            }
            $comp = $result['result']['addressComponent'] ?? [];
            return [
                'address' => trim((string)($result['result']['formatted_address'] ?? '')),
                'province' => trim((string)($comp['province'] ?? '')),
                'city' => trim((string)($comp['city'] ?? '')),
                'district' => trim((string)($comp['district'] ?? '')),
            ];
        }
        if ($provider === 'tianditu') {
            if (strval($result['status'] ?? '') !== '0' && intval($result['status'] ?? -1) !== 0) {
                return [];
            }
            $comp = $result['result']['addressComponent'] ?? [];
            $city = trim((string)($comp['city'] ?? ''));
            $province = trim((string)($comp['province'] ?? ''));
            if ($city === '') {
                $city = $province;
            }
            return [
                'address' => trim((string)($result['result']['formatted_address'] ?? '')),
                'province' => $province,
                'city' => $city,
                'district' => trim((string)($comp['county'] ?? $comp['district'] ?? '')),
            ];
        }
        if (intval($result['status'] ?? -1) !== 0) {
            return [];
        }
        $comp = $result['result']['address_component'] ?? [];
        return [
            'address' => trim((string)($result['result']['address'] ?? '')),
            'province' => trim((string)($comp['province'] ?? '')),
            'city' => trim((string)($comp['city'] ?? '')),
            'district' => trim((string)($comp['district'] ?? '')),
        ];
    }

    private static function buildPlaceSearchUrl(array $cred, string $keyword, $latitude, $longitude, int $page): string
    {
        $key = rawurlencode((string)($cred['key'] ?? ''));
        $encodedKeyword = rawurlencode($keyword);
        $provider = $cred['provider'] ?? 'qq';
        if ($provider === 'gaode') {
            return "https://restapi.amap.com/v3/place/around?key={$key}&location={$longitude},{$latitude}&keywords={$encodedKeyword}&radius=5000&offset=10&page={$page}&extensions=all";
        }
        if ($provider === 'baidu') {
            $pageNum = $page - 1;
            return "http://api.map.baidu.com/place/v2/search?ak={$key}&query={$encodedKeyword}&location={$latitude},{$longitude}&radius=5000&output=json&page_size=10&page_num={$pageNum}";
        }
        if ($provider === 'tianditu') {
            $start = ($page - 1) * 10;
            $post = rawurlencode(json_encode([
                'keyWord' => $keyword,
                'queryType' => 3,
                'start' => $start,
                'count' => 10,
                'pointLonlat' => $longitude . ',' . $latitude,
                'queryRadius' => 5000,
            ], JSON_UNESCAPED_UNICODE));
            return "https://api.tianditu.gov.cn/v2/search?postStr={$post}&type=query&tk={$key}";
        }
        return "https://apis.map.qq.com/ws/place/v1/search?key={$key}&keyword={$encodedKeyword}&boundary=nearby({$latitude},{$longitude},5000)&page_size=10&page_index={$page}";
    }

    private static function parsePlaceSearch(string $provider, array $result): array
    {
        $list = [];
        if ($provider === 'gaode') {
            if (strval($result['status'] ?? '') === '1' && !empty($result['pois'])) {
                foreach ($result['pois'] as $poi) {
                    $location = explode(',', (string)($poi['location'] ?? ''));
                    $list[] = [
                        'name' => $poi['name'] ?? '',
                        'address' => $poi['address'] ?? '',
                        'latitude' => $location[1] ?? '',
                        'longitude' => $location[0] ?? '',
                        'distance' => round(floatval($poi['distance'] ?? 0)) . 'm',
                        'district' => $poi['adname'] ?? '',
                        'street' => $poi['street'] ?? '',
                    ];
                }
                return ['list' => $list];
            }
            return ['list' => [], 'error' => trim((string)($result['info'] ?? ''))];
        }
        if ($provider === 'baidu') {
            if (intval($result['status'] ?? -1) === 0 && !empty($result['results'])) {
                foreach ($result['results'] as $poi) {
                    $list[] = [
                        'name' => $poi['name'] ?? '',
                        'address' => $poi['address'] ?? '',
                        'latitude' => $poi['location']['lat'] ?? '',
                        'longitude' => $poi['location']['lng'] ?? '',
                        'distance' => round(floatval($poi['detail_info']['distance'] ?? 0)) . 'm',
                        'district' => $poi['area'] ?? '',
                        'street' => $poi['street_id'] ?? '',
                    ];
                }
                return ['list' => $list];
            }
            return ['list' => [], 'error' => trim((string)($result['message'] ?? ''))];
        }
        if ($provider === 'tianditu') {
            $pois = $result['pois'] ?? $result['result']['pois'] ?? [];
            if ((strval($result['status'] ?? '') === '0' || intval($result['status'] ?? -1) === 0) && is_array($pois)) {
                foreach ($pois as $poi) {
                    $lonlat = explode(',', (string)($poi['lonlat'] ?? $poi['pointLonlat'] ?? ''));
                    $list[] = [
                        'name' => $poi['name'] ?? '',
                        'address' => $poi['address'] ?? '',
                        'latitude' => $lonlat[1] ?? '',
                        'longitude' => $lonlat[0] ?? '',
                        'distance' => '',
                        'district' => $poi['county'] ?? '',
                        'street' => '',
                    ];
                }
                return ['list' => $list];
            }
            return ['list' => [], 'error' => trim((string)($result['msg'] ?? $result['message'] ?? ''))];
        }
        if (intval($result['status'] ?? -1) === 0 && !empty($result['data'])) {
            foreach ($result['data'] as $poi) {
                $list[] = [
                    'name' => $poi['title'] ?? '',
                    'address' => $poi['address'] ?? '',
                    'latitude' => $poi['location']['lat'] ?? '',
                    'longitude' => $poi['location']['lng'] ?? '',
                    'distance' => round(floatval($poi['_distance'] ?? 0)) . 'm',
                    'district' => $poi['ad_info']['district'] ?? '',
                    'street' => $poi['street'] ?? '',
                ];
            }
            return ['list' => $list];
        }
        return ['list' => [], 'error' => trim((string)($result['message'] ?? ''))];
    }

    /**
     * H5 前台地图 Key 与后台选点、服务端逆地理用的 qq_key 分开存放。
     */
    public static function ensureH5MapKeyColumn(): bool
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        $ready = false;
        try {
            $prefix = config('database.connections.mysql.prefix');
            if (!$prefix) {
                $prefix = 'ddwx_';
            }
            $table = $prefix . 'admin_set';
            $exists = Db::query("SHOW COLUMNS FROM `{$table}` LIKE 'h5_qq_map_key'");
            if ($exists) {
                $ready = true;
                return true;
            }
            Db::execute("ALTER TABLE `{$table}` ADD COLUMN `h5_qq_map_key` varchar(255) NOT NULL DEFAULT '' COMMENT 'H5前台腾讯地图Key，与后台选点Key分开'");
            $ready = true;
        } catch (\Throwable $e) {
            $ready = false;
        }
        return $ready;
    }

    public static function hasSiteKey(int $aid = 0): bool
    {
        $aid = self::aid($aid);
        $keys = self::siteKeys($aid);
        foreach (['qq', 'gaode', 'baidu', 'tianditu'] as $provider) {
            if (self::keyOf($keys, $provider) !== '') {
                return true;
            }
        }
        return false;
    }

    private static function aid(int $aid = 0): int
    {
        if ($aid > 0) {
            return $aid;
        }
        return defined('aid') ? intval(aid) : 0;
    }

    private static function keyOf(array $keys, string $provider): string
    {
        if ($provider === 'gaode') {
            return trim((string)($keys['gaode_key'] ?? $keys['gaode'] ?? ''));
        }
        if ($provider === 'baidu') {
            return trim((string)($keys['baidu_key'] ?? $keys['baidu'] ?? ''));
        }
        if ($provider === 'tianditu') {
            return trim((string)($keys['tianditu_key'] ?? $keys['tianditu'] ?? ''));
        }
        return trim((string)($keys['qq_key'] ?? $keys['qq'] ?? ''));
    }

    private static function siteKeys(int $aid): array
    {
        $empty = [
            'map_type' => 'qq',
            'qq_key' => '',
            'gaode_key' => '',
            'gaode_sk' => '',
            'baidu_key' => '',
            'tianditu_key' => '',
        ];
        if ($aid <= 0) {
            return $empty;
        }
        try {
            $site = Db::name('admin_set')
                ->where('aid', $aid)
                ->field('map_type,qq_key,gaode_key,gaode_sk,baidu_key,tianditu_key')
                ->find() ?: [];
        } catch (\Throwable $e) {
            try {
                $site = Db::name('admin_set')
                    ->where('aid', $aid)
                    ->field('map_type,qq_key,gaode_key,baidu_key,tianditu_key')
                    ->find() ?: [];
            } catch (\Throwable $e2) {
                return $empty;
            }
        }
        return [
            'map_type' => strval($site['map_type'] ?? 'qq'),
            'qq_key' => trim((string)($site['qq_key'] ?? '')),
            'gaode_key' => trim((string)($site['gaode_key'] ?? '')),
            'gaode_sk' => trim((string)($site['gaode_sk'] ?? '')),
            'baidu_key' => trim((string)($site['baidu_key'] ?? '')),
            'tianditu_key' => trim((string)($site['tianditu_key'] ?? '')),
        ];
    }

    private static function platformKeys(): array
    {
        $keys = [
            'map_type' => 'qq',
            'qq' => '',
            'gaode' => '',
            'gaode_sk' => '',
            'baidu' => '',
            'tianditu' => '',
            'qq_key' => '',
            'gaode_key' => '',
            'baidu_key' => '',
            'tianditu_key' => '',
        ];
        try {
            $webinfoValue = Db::name('sysset')->where('name', 'webinfo')->value('value');
            $webinfo = $webinfoValue ? json_decode($webinfoValue, true) : [];
            if (is_array($webinfo)) {
                $keys['map_type'] = strtolower(trim((string)($webinfo['map_type'] ?? 'qq'))) ?: 'qq';
                $keys['qq'] = trim((string)($webinfo['qq_key'] ?? '')) ?: trim((string)($webinfo['map_qq_key'] ?? ''));
                $keys['gaode'] = trim((string)($webinfo['gaode_key'] ?? '')) ?: trim((string)($webinfo['map_gaode_key'] ?? ''));
                $keys['gaode_sk'] = trim((string)($webinfo['gaode_sk'] ?? ''));
                $keys['baidu'] = trim((string)($webinfo['baidu_key'] ?? ''));
                $keys['tianditu'] = trim((string)($webinfo['tianditu_key'] ?? ''));
                $keys['qq_key'] = $keys['qq'];
                $keys['gaode_key'] = $keys['gaode'];
                $keys['baidu_key'] = $keys['baidu'];
                $keys['tianditu_key'] = $keys['tianditu'];
            }
        } catch (\Throwable $e) {
        }
        return $keys;
    }

    private static function maybeSignGaode(string $url, int $aid): string
    {
        if (stripos($url, 'restapi.amap.com') === false || stripos($url, 'sig=') !== false) {
            return $url;
        }
        $sk = '';
        if (preg_match('/[?&]key=([^&]+)/i', $url, $m)) {
            $key = rawurldecode($m[1]);
            $site = self::siteKeys($aid);
            if ($key !== '' && $key === $site['gaode_key']) {
                $sk = $site['gaode_sk'];
            } else {
                $platform = self::platformKeys();
                if ($key !== '' && $key === $platform['gaode']) {
                    $sk = $platform['gaode_sk'];
                }
            }
        }
        if ($sk === '') {
            return $url;
        }
        $parts = parse_url($url);
        $path = (string)($parts['path'] ?? '');
        parse_str((string)($parts['query'] ?? ''), $params);
        unset($params['sig']);
        ksort($params);
        $pairs = [];
        foreach ($params as $name => $value) {
            $pairs[] = $name . '=' . $value;
        }
        $sig = md5($path . '?' . implode('&', $pairs) . $sk);
        return $url . (strpos($url, '?') !== false ? '&' : '?') . 'sig=' . $sig;
    }

    private static function urlUsesSiteKey(string $url, int $aid): bool
    {
        if (!preg_match('/(?:[?&](?:key|ak|tk)=)([^&]+)/i', $url, $m)) {
            return false;
        }
        return self::isOwnCredential(rawurldecode($m[1]), $aid);
    }

    private static function stripKey(string $url): string
    {
        return preg_replace('/(?:[?&](?:key|ak|tk)=)[^&]+/i', '', $url) ?: $url;
    }

    private static function httpGet(string $url): array
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        $body = curl_exec($curl);
        $httpCode = intval(curl_getinfo($curl, CURLINFO_HTTP_CODE));
        curl_close($curl);
        return [
            'body' => is_string($body) ? $body : '',
            'http_code' => $httpCode,
        ];
    }
}
