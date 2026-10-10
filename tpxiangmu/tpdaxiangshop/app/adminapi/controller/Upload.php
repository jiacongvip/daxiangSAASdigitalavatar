<?php
/**
 * 图片上传管理 API (Vue前端版)
 * 参考：app/admin/controller/Upload.php
 * 格式：app/adminapi/controller/BackstageApi.php
 */
namespace app\adminapi\controller;
use think\facade\Db;

class Upload extends Common
{
    private const PROXY_IMAGE_MAX_BYTES = 15 * 1024 * 1024;

    /**
     * 上传文件
     */
    public function uploadFile()
    {
        $this->prepareLargeUploadRuntime();

        $file = request()->file('file');
        if (!$file) {
            return $this->error('请选择文件');
        }

        $category = trim((string)input('post.category', ''));

        try {
            // 获取文件大小 - 使用安全的方式
            $fileSize = 0;
            try {
                // 优先使用 getSize() 方法
                $fileSize = $file->getSize();
            } catch (\Exception $e) {
                // 如果 getSize() 失败，尝试从临时文件路径获取
                $tmpPath = $file->getPathname();
                if ($tmpPath && file_exists($tmpPath)) {
                    $fileSize = filesize($tmpPath);
                } else {
                    // 如果临时文件不存在，记录日志并继续（后续会再次检查）
                    \think\facade\Log::write('无法获取上传文件大小: ' . $e->getMessage());
                }
            }

            // 检查存储用量限制
            if ($fileSize > 0) {
                $storageCheck = $this->checkStorageLimit($fileSize);
                if($storageCheck['status'] == 0){
                    return $this->error($storageCheck['msg']);
                }
            }

            // 获取远程存储配置
            $remote = Db::name('sysset')->where('name', 'remote')->value('value');
            $remote = json_decode($remote, true);
            $remote = is_array($remote) ? $remote : [];

            // 检查单文件大小限制
            if ($fileSize > 0) {
                $maxSizeMb = 0;
                // 优先读 sysset.remote.max_upload_size（单位 MB）
                if (!empty($remote['max_upload_size']) && intval($remote['max_upload_size']) > 0) {
                    $maxSizeMb = intval($remote['max_upload_size']);
                }
                // 兜底 filem_set.max_size（单位 KB）
                if ($maxSizeMb <= 0) {
                    $maxSizeMb = $this->getFilemMaxSizeMb();
                }
                if ($maxSizeMb > 0 && $fileSize > $maxSizeMb * 1024 * 1024) {
                    return $this->error("文件大小超过限制（最大 {$maxSizeMb}MB）");
                }
            }
            $maxwidth = !empty($remote['thumb']) ? intval($remote['thumb_width'] ?? 0) : 0;
            $maxheight = !empty($remote['thumb']) ? intval($remote['thumb_height'] ?? 0) : 0;

            // 验证文件类型
            $allowedExtensions = $this->getAllowedUploadExtensions($category);
            validate(['file' => ['fileExt:' . implode(',', $allowedExtensions)]])->check(['file' => $file]);
            $extension = strtolower($file->getOriginalExtension());

            // 尝试使用upload磁盘，如果失败则使用传统方式
            try {
                $savename = \think\facade\Filesystem::disk('upload')->putFile('' . aid, $file);
                $filepath = 'upload/' . str_replace("\\", '/', $savename);
            } catch (\Exception $diskException) {
                // 如果磁盘方式失败，使用传统的文件保存方式
                \think\facade\Log::write('upload磁盘不可用，使用传统方式: ' . $diskException->getMessage());

                // 创建上传目录
                $uploadDir = 'upload/' . aid . '/' . date('Ymd');
                $fullUploadDir = $this->getPrimaryUploadDirectoryPath($uploadDir);
                if (!is_dir($fullUploadDir)) {
                    mk_dir($fullUploadDir, 0755, true);
                }

                // 生成文件名
                $filename = date('His') . rand(1000, 9999) . '.' . $extension;
                $filepath = $uploadDir . '/' . $filename;

                // 移动文件
                if (!$file->move($fullUploadDir, $filename)) {
                    throw new \Exception('文件保存失败');
                }

                $savename = aid . '/' . date('Ymd') . '/' . $filename;
            }

            // 统一以 public/upload 为主存储路径，再兼容历史 upload 路径读取
            $realFilePath = $this->resolveExistingUploadFilePath($filepath);
            $this->ensureLocalReadableMirror($filepath, $realFilePath);
            $mediaDuration = 0.0;
            // 音频时长限制依赖追爆插件，未安装/未授权时跳过，避免普通上传报错
            if ($this->isZhuibaoagentMediaLimitReady()
                && \app\plugin\zhuibaoagent\common\ZhuibaoMediaLimitService::isAudioExtension($extension)
            ) {
                $maxDurationSec = $this->resolveRequestedAudioMaxDurationSec();
                try {
                    $mediaDuration = \app\plugin\zhuibaoagent\common\ZhuibaoMediaLimitService::assertLocalMediaDurationWithinLimit($realFilePath, $maxDurationSec, '音频文件');
                } catch (\RuntimeException $e) {
                    $this->cleanupRejectedUploadFile($filepath, $realFilePath);
                    return $this->error($e->getMessage());
                }
            }

            // 允许前端传入maxwidth和maxheight覆盖配置
            $maxwidth = input('post.maxwidth') ? input('post.maxwidth') : $maxwidth;
            $maxheight = input('post.maxheight') ? input('post.maxheight') : $maxheight;

            $rinfo = [];
            $rinfo['extension'] = strtolower($file->getOriginalExtension());
            $rinfo['name'] = $file->getOriginalName();
            // 记录文件大小（字节）- 使用安全的方式
            if ($fileSize > 0) {
                $rinfo['size'] = $fileSize;
            } else {
                // 如果之前没有获取到，从实际保存的文件获取
                if (file_exists($realFilePath)) {
                    $rinfo['size'] = filesize($realFilePath);
                } else {
                    $rinfo['size'] = 0;
                }
            }
            if ($mediaDuration > 0) {
                $rinfo['duration_sec'] = round($mediaDuration, 2);
            }

            // 处理图片尺寸
            if (in_array($rinfo['extension'], array('jpg', 'jpeg', 'png', 'bmp', 'gif'))) {
                // 检查文件是否存在并获取图片尺寸
                if (file_exists($realFilePath)) {
                    $size = getimagesize($realFilePath);
                    $rinfo['width'] = $size[0];
                    $rinfo['height'] = $size[1];
                } else {
                    $rinfo['width'] = 0;
                    $rinfo['height'] = 0;
                }

                // 生成缩略图
                if ($maxwidth > 0 && $maxheight > 0) {
                    if ($rinfo['width'] > $maxwidth || $rinfo['height'] > $maxheight) {
                        $image = \think\Image::open($realFilePath);
                        $thumbFilepath = substr($filepath, 0, strlen($filepath) - strlen($rinfo['extension']) - 1) . '_thumb.' . $rinfo['extension'];
                        $realThumbFilePath = $this->getPrimaryUploadFilePath($thumbFilepath);
                        $image->thumb($maxwidth, $maxheight)->save($realThumbFilePath, null, 100);
                        $size = getimagesize($realThumbFilePath);
                        $rinfo['width'] = $size[0];
                        $rinfo['height'] = $size[1];
                        $filepath = $thumbFilepath; // 使用缩略图路径
                        $realFilePath = $realThumbFilePath; // 更新实际文件路径
                    }
                }
            }

            $rinfo['url'] = $this->buildPublicFileUrl($filepath);

            // 特殊文件类型处理
            if (in_array($rinfo['extension'], ['crt', 'cer', 'p12', 'pfx'], true)) {
                $rinfo['url'] = $filepath;
            }

            $skipRemoteUpload = $this->shouldSkipRemoteUpload($rinfo['extension'], intval($rinfo['size'] ?? 0), $category);

            // 上传到云存储（复用Pic类）。大模型先本地入库，避免同步转存 OSS 导致 502。
            if (!in_array($rinfo['extension'], array('pem', 'xls', 'xlsx', 'csv', 'crt', 'cer', 'p12', 'pfx')) && !$skipRemoteUpload) {
                $uploadedUrl = \app\admin\common\Pic::uploadoss($rinfo['url'], false, $realFilePath);
                $ossError = \app\admin\common\Pic::getLastError();
                if ($ossError !== '') {
                    $this->cleanupRejectedUploadFile($filepath, $realFilePath);
                    return $this->error($ossError);
                }
                if ($uploadedUrl) {
                    $rinfo['url'] = $uploadedUrl;
                }
            } elseif ($skipRemoteUpload) {
                \think\facade\Log::write('大模型上传已跳过同步转存OSS: ' . $rinfo['name'] . ', size=' . intval($rinfo['size'] ?? 0));
                $rinfo['remote_skipped'] = 1;
            }

            $rinfo['url'] = $this->normalizeMediaUrl($rinfo['url']);

            if (!in_array($rinfo['extension'], array('pem', 'crt', 'cer', 'p12', 'pfx'))) {
                // 记录到数据库
                $gid = input('post.gid', 0);
                $rinfo['id'] = Db::name('admin_upload')->insertGetId([
                    'aid' => aid,
                    'uid' => uid,
                    'name' => $rinfo['name'],
                    'dir' => date('Ymd'),
                    'url' => $rinfo['url'],
                    'type' => $rinfo['extension'],
                    'width' => $rinfo['width'] ?? 0,
                    'height' => $rinfo['height'] ?? 0,
                    'size' => $rinfo['size'],
                    'createtime' => time(),
                    'gid' => $gid,
                    'platform' => 'vue',
                    'merchant_id' => bid ? bid : 0  // 保存商户ID
                ]);
            }

            return $this->success($rinfo, '上传成功');
        } catch (\think\exception\ValidateException $e) {
            return $this->error($e->getMessage());
        } catch (\Exception $e) {
            return $this->error('上传失败：' . $e->getMessage());
        }
    }

    /**
     * 获取素材中心容量统计
     * 返回：已用容量、容量上限、各类型素材数量、子商户数量
     */
    public function getStorageStat()
    {
        $merchant_id = bid ? bid : 0;

        // 当前账号(或商户)已使用容量（字节）
        $usedSize = 0 + Db::name('admin_upload')
            ->where('aid', aid)
            ->where('merchant_id', $merchant_id)
            ->where('isdel', 0)
            ->sum('size');

        // 文件总数
        $fileCount = Db::name('admin_upload')
            ->where('aid', aid)
            ->where('merchant_id', $merchant_id)
            ->where('isdel', 0)
            ->where('type', '<>', 'pem')
            ->count();

        // 容量上限（单位 MB），与 checkStorageLimit 口径保持一致
        $storageLimit = 0;
        if (bid > 0) {
            $userType = Db::name('admin_user')
                ->where('aid', aid)
                ->where('bid', bid)
                ->where('id', uid)
                ->value('type');
            if ($userType == 'tuanzhang') {
                $storageLimit = Db::name('tuanzhang')->where('aid', aid)->where('id', bid)->value('storage_limit');
            } else {
                $storageLimit = Db::name('business')->where('aid', aid)->where('id', bid)->value('storage_limit');
            }
        } else {
            $storageLimit = Db::name('admin')->where('id', aid)->value('storage_limit');
        }
        $storageLimit = intval($storageLimit);

        // 子商户数量（仅平台后台统计）
        $merchantCount = 0;
        if (bid <= 0 && $this->tableExists('business')) {
            $merchantCount = Db::name('business')->where('aid', aid)->count();
        }

        // 各分类素材数量
        $imageTypes = ['jpg', 'jpeg', 'png', 'bmp', 'gif', 'webp', 'svg'];
        $videoTypes = ['mp4', 'mov', 'm4v', 'avi', 'mkv', 'webm', 'ogg', 'ogv', 'wmv', 'flv', '3gp', 'mpeg', 'mpg'];
        $audioTypes = ['mp3', 'wav', 'm4a', 'aac', 'flac', 'oga', 'ogg', 'opus', 'amr', 'wma', 'weba'];

        $countByTypes = function (array $types) use ($merchant_id) {
            return Db::name('admin_upload')
                ->where('aid', aid)
                ->where('merchant_id', $merchant_id)
                ->where('isdel', 0)
                ->where('type', 'in', $types)
                ->count();
        };

        return $this->success([
            'used_size' => $usedSize,
            'used_mb' => round($usedSize / 1024 / 1024, 2),
            'storage_limit_mb' => $storageLimit,
            'file_count' => $fileCount,
            'merchant_count' => $merchantCount,
            'image_count' => $countByTypes($imageTypes),
            'video_count' => $countByTypes($videoTypes),
            'audio_count' => $countByTypes($audioTypes),
        ], '获取成功');
    }

    /**
     * 获取上传配置（前端用于文件大小校验）
     */
    public function getUploadConfig()
    {
        $maxSizeMb = 0;

        // 优先从 sysset.remote 的 max_upload_size（单位：MB）读取
        $remote = Db::name('sysset')->where('name', 'remote')->value('value');
        $remote = $remote ? json_decode($remote, true) : [];
        if (!empty($remote['max_upload_size']) && intval($remote['max_upload_size']) > 0) {
            $maxSizeMb = intval($remote['max_upload_size']);
        }

        // 兜底：如果 remote 没配，读 filem_set.max_size（单位：KB）
        if ($maxSizeMb <= 0) {
            $maxSizeMb = $this->getFilemMaxSizeMb();
        }

        return $this->success([
            'max_size_mb' => $maxSizeMb,
        ]);
    }

    /**
     * 图片同源代理
     * 用于前端 canvas 裁剪 OSS/CDN 图片，避免目标存储未配置 CORS 时浏览器拦截。
     */
    public function proxyImage()
    {
        $url = trim((string)input('get.url', ''));
        if ($url === '') {
            return $this->error('图片地址不能为空');
        }

        $url = $this->normalizeMediaUrl($url);
        if (!$this->isSafeRemoteImageUrl($url)) {
            return $this->error('图片地址不允许访问');
        }

        try {
            $body = $this->fetchRemoteImage($url);
            if ($body === '') {
                return $this->error('图片内容为空');
            }

            if (strlen($body) > self::PROXY_IMAGE_MAX_BYTES) {
                return $this->error('图片文件过大');
            }

            $imageInfo = @getimagesizefromstring($body);
            if (!$imageInfo || empty($imageInfo['mime'])) {
                return $this->error('远程文件不是有效图片');
            }

            $mime = strtolower((string)$imageInfo['mime']);
            $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp'];
            if (!in_array($mime, $allowedMimes, true)) {
                return $this->error('不支持的图片格式');
            }

            return response($body, 200, [
                'Content-Type' => $mime,
                'Content-Length' => (string)strlen($body),
                'Cache-Control' => 'private, max-age=300',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        } catch (\Throwable $e) {
            \think\facade\Log::write('图片代理失败: ' . $e->getMessage() . ' url=' . $url);
            return $this->error('图片读取失败');
        }
    }

    private function getFilemMaxSizeMb(): float
    {
        if (!$this->tableExists('filem_set')) {
            return 0;
        }

        $set = Db::name('filem_set')
            ->where('aid', $this->aid)
            ->where('bid', $this->bid ? $this->bid : 0)
            ->find();

        if ($set && !empty($set['max_size']) && intval($set['max_size']) > 0) {
            return round(intval($set['max_size']) / 1024, 2);
        }

        return 0;
    }

    private function buildPublicFileUrl($path): string
    {
        $base = defined('PRE_URL') && PRE_URL !== '' ? PRE_URL : request()->domain();
        return $this->normalizeMediaUrl(rtrim((string)$base, '/') . '/' . ltrim((string)$path, '/'));
    }

    private function normalizeMediaUrl($url): string
    {
        $url = trim((string)$url);
        if ($url === '') {
            return '';
        }

        if (strpos($url, 'http://') === 0 || strpos($url, 'https://') === 0) {
            $parts = parse_url($url);
            if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
                return preg_replace('#(?<!:)/{2,}#', '/', $url);
            }

            $normalized = $parts['scheme'] . '://' . $parts['host'];
            if (!empty($parts['port'])) {
                $normalized .= ':' . $parts['port'];
            }

            $path = isset($parts['path']) ? preg_replace('#/+#', '/', $parts['path']) : '';
            if ($path !== '') {
                $normalized .= '/' . ltrim($path, '/');
            }
            if (isset($parts['query'])) {
                $normalized .= '?' . $parts['query'];
            }
            if (isset($parts['fragment'])) {
                $normalized .= '#' . $parts['fragment'];
            }

            return $normalized;
        }

        return preg_replace('#/+#', '/', $url);
    }

    private function prepareLargeUploadRuntime()
    {
        @set_time_limit(0);
        @ini_set('max_execution_time', '0');
        @ini_set('max_input_time', '600');
        @ini_set('default_socket_timeout', '600');
        @ini_set('memory_limit', '1024M');
    }

    private function shouldSkipRemoteUpload($extension, $fileSize, $category = '')
    {
        $extension = strtolower((string)$extension);
        $category = trim((string)$category);
        $modelTypes = ['glb', 'gltf', 'bin'];
        $isModel = $category === 'model' || in_array($extension, $modelTypes, true);
        return $isModel && intval($fileSize) >= 20 * 1024 * 1024;
    }

    /**
     * 追爆音频时长限制是否可用：类文件存在 + 站点已授权
     */
    private function isZhuibaoagentMediaLimitReady(): bool
    {
        if (!class_exists('\\app\\plugin\\zhuibaoagent\\common\\ZhuibaoMediaLimitService')) {
            return false;
        }
        if (!class_exists('\\app\\admin\\common\\PluginBusinessAuth')) {
            return false;
        }
        $aid = defined('aid') ? intval(constant('aid')) : 0;
        if ($aid <= 0) {
            return false;
        }
        try {
            return \app\admin\common\PluginBusinessAuth::isAidPluginEnabled($aid, 'zhuibaoagent');
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function resolveRequestedAudioMaxDurationSec(): int
    {
        $agentKey = trim(strval(input('post.agent_key', input('post.zhuibao_agent_key', ''))));
        if ($agentKey !== '' && $this->isZhuibaoagentMediaLimitReady()) {
            $aid = defined('aid') ? intval(constant('aid')) : 0;
            $limits = \app\plugin\zhuibaoagent\common\ZhuibaoMediaLimitService::getAgentLimitConfig($aid, $agentKey);
            return min(86400, max(0, intval($limits['audio_max_duration_sec'] ?? 0)));
        }

        $values = [
            input('post.audio_max_duration_sec', null),
            input('post.max_duration_sec', null),
            input('post.audio_limit_seconds', null),
        ];
        foreach ($values as $value) {
            if ($value === null || $value === '') {
                continue;
            }
            return min(86400, max(0, intval($value)));
        }
        return 0;
    }

    private function cleanupRejectedUploadFile($relativePath, $realFilePath): void
    {
        $targets = [
            $realFilePath,
            $this->getPrimaryUploadFilePath($relativePath),
            $this->getLegacyUploadFilePath($relativePath),
        ];
        foreach (array_unique(array_filter($targets)) as $target) {
            if (is_file($target)) {
                @unlink($target);
            }
        }
    }

    private function isSafeRemoteImageUrl(string $url): bool
    {
        $parts = parse_url($url);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }

        $scheme = strtolower((string)$parts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        $host = strtolower(trim((string)$parts['host'], " \t\n\r\0\x0B[]"));
        if ($host === '' || in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return !$this->isPrivateProxyIp($host);
        }

        $resolvedIps = [];
        $ipv4List = @gethostbynamel($host);
        if (is_array($ipv4List)) {
            $resolvedIps = array_merge($resolvedIps, $ipv4List);
        }
        $ipv6Records = @dns_get_record($host, DNS_AAAA);
        if (is_array($ipv6Records)) {
            foreach ($ipv6Records as $record) {
                if (!empty($record['ipv6'])) {
                    $resolvedIps[] = $record['ipv6'];
                }
            }
        }

        if (!empty($resolvedIps)) {
            foreach ($resolvedIps as $ip) {
                if ($this->isPrivateProxyIp($ip)) {
                    return false;
                }
            }
        }

        return true;
    }

    private function isPrivateProxyIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
    }

    private function fetchRemoteImage(string $url): string
    {
        if (!function_exists('curl_init')) {
            throw new \RuntimeException('服务器未安装 curl 扩展');
        }

        $body = '';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADER => false,
            CURLOPT_USERAGENT => 'Tiantianshan-Image-Proxy/1.0',
            CURLOPT_HTTPHEADER => ['Accept: image/avif,image/webp,image/apng,image/*,*/*;q=0.8'],
            CURLOPT_HEADERFUNCTION => function ($curl, $header) {
                $length = strlen($header);
                $parts = explode(':', $header, 2);
                if (count($parts) === 2) {
                    $name = strtolower(trim($parts[0]));
                    $value = trim($parts[1]);
                    if ($name === 'content-length' && intval($value) > self::PROXY_IMAGE_MAX_BYTES) {
                        return 0;
                    }
                }
                return $length;
            },
            CURLOPT_WRITEFUNCTION => function ($curl, $chunk) use (&$body) {
                if (strlen($body) + strlen($chunk) > self::PROXY_IMAGE_MAX_BYTES) {
                    return 0;
                }
                $body .= $chunk;
                return strlen($chunk);
            },
        ]);

        $ok = curl_exec($ch);
        $error = curl_error($ch);
        $status = intval(curl_getinfo($ch, CURLINFO_RESPONSE_CODE));
        curl_close($ch);

        if ($ok === false) {
            if (strlen($body) >= self::PROXY_IMAGE_MAX_BYTES) {
                throw new \RuntimeException('图片文件过大');
            }
            throw new \RuntimeException($error ?: '远程请求失败');
        }
        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException('远程请求状态异常：' . $status);
        }

        return $body;
    }

    /**
     * 获取文件列表（浏览器）
     */
    public function getFileList()
    {
        $page = input('post.page', 1);
        $limit = input('post.limit', 28);
        $gid = input('post.gid', -1);
        $keyword = input('post.keyword', '');
        $sort = input('post.sort', 2);
        $category = input('post.category', '');

        $where = [
            ['aid', '=', aid],
            ['uid', '=', uid],
            ['isdel', '=', 0],
            ['type', '<>', 'pem']
        ];

        $categoryWhere = $this->buildCategoryWhere($category);
        if (!empty($categoryWhere)) {
            $where[] = $categoryWhere;
        }

        // 分组筛选
        if ($gid != -1) {
            $where[] = ['gid', '=', $gid];
        }

        // 关键词搜索
        if ($keyword != '') {
            $where[] = ['name', 'like', '%' . $keyword . '%'];
        }

        // 排序
        $orderby = 'createtime desc';
        if ($sort == 1) {
            $orderby = 'createtime asc';
        } elseif ($sort == 3) {
            $orderby = 'name asc';
        } elseif ($sort == 4) {
            $orderby = 'name desc';
        }

        $count = Db::name('admin_upload')
            ->where($where)
            ->count();

        $list = Db::name('admin_upload')
            ->field('id,name,url,type,size,createtime,width,height,dir,gid')
            ->where($where)
            ->order($orderby)
            ->page($page, $limit)
            ->select()
            ->toArray();

        foreach ($list as $index => $item) {
            $list[$index]['url'] = $this->normalizeMediaUrl($item['url'] ?? '');
        }

        return $this->success([
            'list' => $list,
            'total' => $count,
            'page' => $page,
            'limit' => $limit,
            'totalPage' => ceil($count / $limit)
        ], '获取成功');
    }

    /**
     * 删除文件
     */
    public function deleteFile()
    {
        $ids = input('post.ids');
        if (empty($ids)) {
            return $this->error('请选择要删除的文件');
        }

        if (is_string($ids)) {
            $ids = explode(',', $ids);
        }

        try {
            $list = Db::name('admin_upload')
                ->where('aid', aid)
                ->where('uid', uid)
                ->where('id', 'in', $ids)
                ->select()
                ->toArray();

            Db::name('admin_upload')
                ->where('aid', aid)
                ->where('uid', uid)
                ->where('id', 'in', $ids)
                ->delete();

            // 删除云存储文件（复用Pic类）
            foreach ($list as $item) {
                \app\admin\common\Pic::deletepic($item['url']);
            }

            return $this->success([], '删除成功');
        } catch (\Exception $e) {
            return $this->error('删除失败：' . $e->getMessage());
        }
    }

    /**
     * 获取分组列表
     */
    public function getGroupList()
    {
        $list = Db::name('admin_upload_group')
            ->where('aid', aid)
            ->where('uid', uid)
            ->where('pid', 0)
            ->order('sort desc,createtime desc')
            ->select()
            ->toArray();

        // 获取子分组
        foreach ($list as $k => $item) {
            $children = Db::name('admin_upload_group')
                ->where('aid', aid)
                ->where('uid', uid)
                ->where('pid', $item['id'])
                ->order('sort desc,createtime desc')
                ->select()
                ->toArray();
            $list[$k]['children'] = $children ?: [];
        }

        return $this->success($list, '获取成功');
    }

    /**
     * 保存分组（新增/编辑）
     */
    public function saveGroup()
    {
        $id = input('post.id', 0);
        $name = input('post.name', '');
        $pid = input('post.pid', 0);

        if (empty($name)) {
            return $this->error('分组名称不能为空');
        }

        try {
            if ($id > 0) {
                // 编辑
                Db::name('admin_upload_group')
                    ->where('aid', aid)
                    ->where('uid', uid)
                    ->where('id', $id)
                    ->update([
                        'name' => $name,
                        'pid' => $pid
                    ]);
                return $this->success(['id' => $id], '修改成功');
            } else {
                // 新增
                $data = [
                    'aid' => aid,
                    'uid' => uid,
                    'name' => $name,
                    'pid' => $pid,
                    'createtime' => time()
                ];
                $id = Db::name('admin_upload_group')->insertGetId($data);
                return $this->success(['id' => $id], '创建成功');
            }
        } catch (\Exception $e) {
            return $this->error('操作失败：' . $e->getMessage());
        }
    }

    /**
     * 删除分组
     */
    public function deleteGroup()
    {
        $id = input('post.id', 0);
        if ($id <= 0) {
            return $this->error('参数错误');
        }

        try {
            // 检查是否有子分组
            $hasChildren = Db::name('admin_upload_group')
                ->where('aid', aid)
                ->where('uid', uid)
                ->where('pid', $id)
                ->count();

            if ($hasChildren > 0) {
                return $this->error('请先删除子分组');
            }

            // 检查是否有文件
            $hasFiles = Db::name('admin_upload')
                ->where('aid', aid)
                ->where('uid', uid)
                ->where('gid', $id)
                ->count();

            if ($hasFiles > 0) {
                return $this->error('该分组下还有文件，请先移动或删除文件');
            }

            Db::name('admin_upload_group')
                ->where('aid', aid)
                ->where('uid', uid)
                ->where('id', $id)
                ->delete();

            return $this->success([], '删除成功');
        } catch (\Exception $e) {
            return $this->error('删除失败：' . $e->getMessage());
        }
    }

    /**
     * 移动文件到分组
     */
    public function moveFiles()
    {
        $ids = input('post.ids');
        $gid = input('post.gid', 0);

        if (empty($ids)) {
            return $this->error('请选择要移动的文件');
        }

        if (is_string($ids)) {
            $ids = explode(',', $ids);
        }

        try {
            Db::name('admin_upload')
                ->where('aid', aid)
                ->where('uid', uid)
                ->where('id', 'in', $ids)
                ->update(['gid' => $gid]);

            return $this->success([], '移动成功');
        } catch (\Exception $e) {
            return $this->error('移动失败：' . $e->getMessage());
        }
    }

    /**
     * 编辑分组
     */
    public function editGroup()
    {
        $id = input('post.id', 0);
        $name = input('post.name', '');

        if ($id <= 0) {
            return $this->error('参数错误');
        }

        if (empty($name)) {
            return $this->error('分组名称不能为空');
        }

        try {
            Db::name('admin_upload_group')
                ->where('aid', aid)
                ->where('uid', uid)
                ->where('id', $id)
                ->update(['name' => $name]);

            return $this->success([], '修改成功');
        } catch (\Exception $e) {
            return $this->error('修改失败：' . $e->getMessage());
        }
    }

    /**
     * 批量删除文件
     */
    public function batchDelete()
    {
        return $this->deleteFile();
    }

    /**
     * 根据素材分类构建文件类型筛选
     */
    private function buildCategoryWhere($category)
    {
        $category = trim((string)$category);
        if ($category === '' || $category === 'all') {
            return [];
        }

        $imageTypes = ['jpg', 'jpeg', 'png', 'bmp', 'gif', 'webp', 'svg'];
        $videoTypes = ['mp4', 'mov', 'm4v', 'avi', 'mkv', 'webm', 'ogg', 'ogv', 'wmv', 'flv', '3gp', 'mpeg', 'mpg'];
        $audioTypes = ['mp3', 'wav', 'm4a', 'aac', 'flac', 'oga', 'ogg', 'opus', 'amr', 'wma', 'webm', 'weba'];
        $modelTypes = ['glb', 'gltf', 'bin'];

        if ($category === 'image') {
            return ['type', 'in', $imageTypes];
        }
        if ($category === 'video') {
            return ['type', 'in', $videoTypes];
        }
        if ($category === 'audio') {
            return ['type', 'in', $audioTypes];
        }
        if ($category === 'model') {
            return ['type', 'in', $modelTypes];
        }
        if ($category === 'file') {
            $excludeTypes = array_merge($imageTypes, $videoTypes, $audioTypes, $modelTypes, ['pem', 'crt', 'cer', 'p12', 'pfx']);
            return ['type', 'not in', $excludeTypes];
        }

        return [];
    }

    /**
     * 根据上传分类返回允许的后缀列表
     */
    private function getAllowedUploadExtensions($category)
    {
        $imageTypes = ['jpg', 'jpeg', 'png', 'bmp', 'gif', 'webp', 'svg'];
        $videoTypes = ['mp4', 'mov', 'm4v', 'avi', 'mkv', 'webm', 'ogg', 'ogv', 'wmv', 'flv', '3gp', 'mpeg', 'mpg'];
        $audioTypes = ['mp3', 'wav', 'm4a', 'aac', 'flac', 'oga', 'ogg', 'opus', 'amr', 'wma', 'webm', 'weba'];
        $modelTypes = ['glb', 'gltf', 'bin'];
        $fileTypes = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'zip', 'rar', '7z', 'csv', 'json', 'pem', 'crt', 'cer', 'p12', 'pfx', 'apk', 'wgt'];

        $category = trim((string)$category);
        if ($category === 'image') {
            return $imageTypes;
        }
        if ($category === 'video') {
            return $videoTypes;
        }
        if ($category === 'audio') {
            return $audioTypes;
        }
        if ($category === 'model') {
            return $modelTypes;
        }
        if ($category === 'file') {
            return $fileTypes;
        }
        if ($category === 'all') {
            return array_values(array_unique(array_merge($imageTypes, $videoTypes, $audioTypes, $modelTypes, $fileTypes)));
        }

        $configTypes = array_filter(array_map('trim', explode(',', (string)config('app.upload_type'))));
        if (!empty($configTypes)) {
            return array_values(array_unique($configTypes));
        }

        return array_values(array_unique(array_merge($imageTypes, $videoTypes, $audioTypes, $modelTypes, $fileTypes)));
    }

    private function shouldMirrorLocalOnlyFile($extension)
    {
        return in_array(strtolower((string)$extension), ['pem', 'crt', 'cer', 'p12', 'pfx', 'glb', 'gltf', 'bin'], true);
    }

    private function getPrimaryUploadDirectoryPath($relativePath)
    {
        $relativePath = $this->normalizeUploadRelativePath($relativePath);
        return rtrim(app()->getRootPath(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    }

    private function getPrimaryUploadFilePath($relativePath)
    {
        return $this->getPrimaryUploadDirectoryPath($relativePath);
    }

    private function getLegacyUploadFilePath($relativePath)
    {
        $relativePath = $this->normalizeUploadRelativePath($relativePath);
        return rtrim(app()->getRootPath(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    }

    private function resolveExistingUploadFilePath($relativePath)
    {
        $primaryPath = $this->getPrimaryUploadFilePath($relativePath);
        if (is_file($primaryPath)) {
            return $primaryPath;
        }

        $legacyPath = $this->getLegacyUploadFilePath($relativePath);
        if (is_file($legacyPath)) {
            return $legacyPath;
        }

        return $primaryPath;
    }

    private function ensureLocalReadableMirror($relativePath, $sourcePath)
    {
        if (!$this->shouldMirrorLocalOnlyFile(pathinfo((string)$relativePath, PATHINFO_EXTENSION))) {
            return;
        }
        if (!$sourcePath || !is_file($sourcePath)) {
            return;
        }

        $targets = [
            $this->getPrimaryUploadFilePath($relativePath),
            $this->getLegacyUploadFilePath($relativePath),
        ];
        foreach ($targets as $targetPath) {
            if ($targetPath === $sourcePath || is_file($targetPath)) {
                continue;
            }
            $targetDir = dirname($targetPath);
            if (!is_dir($targetDir)) {
                mk_dir($targetDir, 0755, true);
            }
            @copy($sourcePath, $targetPath);
        }
    }

    private function normalizeUploadRelativePath($relativePath)
    {
        $relativePath = str_replace('\\', '/', trim((string)$relativePath));
        $relativePath = ltrim($relativePath, '/');
        if (strpos($relativePath, 'public/') === 0) {
            $relativePath = substr($relativePath, 7);
        }
        return $relativePath;
    }
    
    /**
     * 检查存储用量限制
     */
    private function checkStorageLimit($fileSize){
    	// 获取当前已使用的存储空间
    	$merchant_id = bid ? bid : 0;
    	$used_storage = 0 + Db::name('admin_upload')
    		->where('aid', aid)
    		->where('merchant_id', $merchant_id)
    		->where('isdel', 0)
    		->sum('size');
    	
    	// 获取存储上限配置
    	$storage_limit = 0;
    	
    	if(bid > 0){
    		// 团长或商户后台
    		$user_type = Db::name('admin_user')
    			->where('aid', aid)
    			->where('bid', bid)
    			->where('id', uid)
    			->value('type');
    		
    		if($user_type == 'tuanzhang'){
    			// 团长
    			$storage_limit = Db::name('tuanzhang')
    				->where('aid', aid)
    				->where('id', bid)
    				->value('storage_limit');
    		} else {
    			// 商户
    			$storage_limit = Db::name('business')
    				->where('aid', aid)
    				->where('id', bid)
    				->value('storage_limit');
    		}
    	} else {
    		// 平台后台
    		$storage_limit = Db::name('admin')
    			->where('id', aid)
    			->value('storage_limit');
    	}
    	
    	// 如果没有设置上限或上限为0，则不限制
    	if(!$storage_limit || $storage_limit <= 0){
    		return ['status' => 1];
    	}
    	
    	// 转换为字节（数据库存储单位为MB）
    	$storage_limit_bytes = $storage_limit * 1024 * 1024;
    	
    	// 检查上传后是否会超过限制
    	if(($used_storage + $fileSize) > $storage_limit_bytes){
    		$used_mb = round($used_storage / 1024 / 1024, 2);
    		return [
    			'status' => 0,
    			'msg' => "存储空间不足！当前已使用 {$used_mb}MB，限额 {$storage_limit}MB。请联系管理员扩容或清理文件后再试。"
    		];
    	}
    	
    	return ['status' => 1];
    }
}
