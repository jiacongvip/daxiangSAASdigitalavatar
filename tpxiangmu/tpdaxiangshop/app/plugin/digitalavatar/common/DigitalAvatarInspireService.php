<?php

namespace app\plugin\digitalavatar\common;

use app\admin\common\PluginMenu;
use think\facade\Db;
use think\facade\Log;

class DigitalAvatarInspireService
{
    private static $schemaReady = false;
    private static $menusReady = false;
    private static $seededAids = [];

    private const DEFAULT_CATEGORIES = [
        ['name' => '海影节', 'badge' => 'NEW', 'sort' => 10],
        ['name' => '变身写真', 'badge' => '', 'sort' => 20],
        ['name' => '搞怪整活', 'badge' => '', 'sort' => 30],
        ['name' => '风格转绘', 'badge' => '', 'sort' => 40],
        ['name' => '广告电商', 'badge' => '', 'sort' => 50],
        ['name' => '怦然心动', 'badge' => '', 'sort' => 60],
        ['name' => '萌宠来袭', 'badge' => '', 'sort' => 70],
        ['name' => 'Q版童趣', 'badge' => '', 'sort' => 80],
        ['name' => '炫酷场景', 'badge' => '', 'sort' => 90],
        ['name' => 'ASMR', 'badge' => '', 'sort' => 100],
        ['name' => '万圣节夜晚', 'badge' => '', 'sort' => 110],
    ];

    public static function ensureSchema(int $aid = 0): void
    {
        if (!self::$schemaReady) {
            self::$schemaReady = true;
            self::ensureCategoryTable();
            self::ensureInspireTable();
            self::ensureLikeTable();
            self::ensureCommentTable();
            self::ensureFavTable();
            self::ensureFollowTable();
            self::ensureUserCategoryTable();
            self::ensureInspireColumns();
            self::ensureCommentColumns();
        }
        if ($aid > 0) {
            self::seedCategories($aid);
            self::syncMenus($aid);
        }
    }

    public static function listCategories(int $aid, bool $activeOnly = false): array
    {
        self::ensureSchema($aid);
        $query = Db::name('digitalavatar_inspire_category')->where('aid', $aid);
        if ($activeOnly) {
            $query->where('status', 1);
        }
        $rows = $query->order('sort asc,id asc')->select()->toArray();
        return array_map([self::class, 'formatCategory'], $rows);
    }

    public static function saveCategory(int $aid, array $info): array
    {
        self::ensureSchema($aid);
        $id = intval($info['id'] ?? 0);
        $name = trim(strval($info['name'] ?? ''));
        if ($name === '') {
            throw new \RuntimeException('请填写分类名称');
        }
        $data = [
            'aid' => $aid,
            'name' => mb_substr($name, 0, 40),
            'badge' => mb_substr(trim(strval($info['badge'] ?? '')), 0, 12),
            'sort' => intval($info['sort'] ?? 0),
            'status' => intval($info['status'] ?? 1) === 1 ? 1 : 0,
            'update_time' => time(),
        ];
        if ($id > 0) {
            $exists = Db::name('digitalavatar_inspire_category')->where('aid', $aid)->where('id', $id)->find();
            if (!$exists) {
                throw new \RuntimeException('分类不存在');
            }
            Db::name('digitalavatar_inspire_category')->where('aid', $aid)->where('id', $id)->update($data);
        } else {
            $data['create_time'] = time();
            $id = intval(Db::name('digitalavatar_inspire_category')->insertGetId($data));
        }
        return self::formatCategory(Db::name('digitalavatar_inspire_category')->where('aid', $aid)->where('id', $id)->find() ?: []);
    }

    public static function deleteCategory(int $aid, int $id): void
    {
        self::ensureSchema($aid);
        if ($id <= 0) {
            throw new \RuntimeException('请传入分类ID');
        }
        $used = intval(Db::name('digitalavatar_inspire')->where('aid', $aid)->where('category_id', $id)->where('deleted_time', 0)->count());
        if ($used > 0) {
            throw new \RuntimeException('该分类下还有灵感，无法删除');
        }
        $affected = Db::name('digitalavatar_inspire_category')->where('aid', $aid)->where('id', $id)->delete();
        if ($affected <= 0) {
            throw new \RuntimeException('分类不存在');
        }
    }

    public static function portalFeed(int $aid, int $memberId, array $query): array
    {
        self::ensureSchema($aid);
        $page = max(1, intval($query['page'] ?? 1));
        $limit = max(1, min(50, intval($query['limit'] ?? 50)));
        $tab = trim(strval($query['tab'] ?? 'recommend'));
        $categoryId = intval($query['category_id'] ?? 0);
        $builder = Db::name('digitalavatar_inspire')
            ->where('aid', $aid)
            ->where('deleted_time', 0)
            ->where('status', 1);
        if ($tab === 'recommend' || ($tab === '' && $categoryId <= 0)) {
            $builder->where('recommended', 1);
        } elseif ($categoryId > 0) {
            $builder->where('category_id', $categoryId);
        }
        $total = intval((clone $builder)->count());
        $rows = $builder->page($page, $limit)->order('sort desc,id desc')->select()->toArray();
        $liked = self::likedMap($aid, $memberId, $rows);
        $list = [];
        foreach ($rows as $row) {
            $item = self::formatInspire($row, false, true);
            $item['liked'] = isset($liked[intval($row['id'])]) ? 1 : 0;
            $list[] = $item;
        }
        return [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'categories' => self::listCategories($aid, true),
        ];
    }

    public static function adminList(int $aid, array $query): array
    {
        self::ensureSchema($aid);
        $page = max(1, intval($query['page'] ?? 1));
        $limit = max(1, min(100, intval($query['limit'] ?? 20)));
        $builder = Db::name('digitalavatar_inspire')->where('aid', $aid)->where('deleted_time', 0);
        $status = trim(strval($query['status'] ?? ''));
        if ($status !== '' && is_numeric($status)) {
            $builder->where('status', intval($status));
        }
        $categoryId = intval($query['category_id'] ?? 0);
        if ($categoryId > 0) {
            $builder->where('category_id', $categoryId);
        }
        if (isset($query['recommended']) && $query['recommended'] !== '' && $query['recommended'] !== null) {
            $builder->where('recommended', intval($query['recommended']) === 1 ? 1 : 0);
        }
        $keyword = trim(strval($query['keyword'] ?? ''));
        if ($keyword !== '') {
            $builder->whereLike('title|prompt', '%' . $keyword . '%');
        }
        $total = intval((clone $builder)->count());
        $rows = $builder->page($page, $limit)->order('id desc')->select()->toArray();
        $list = array_map(function ($row) {
            return self::formatInspire($row, true);
        }, $rows);
        self::attachMembers($aid, $list);
        return [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'categories' => self::listCategories($aid, false),
        ];
    }

    public static function getDetail(int $aid, int $id, int $memberId = 0, bool $admin = false): array
    {
        self::ensureSchema($aid);
        $row = self::owned($aid, $id, false);
        if (!$admin && intval($row['status'] ?? 0) !== 1) {
            throw new \RuntimeException('灵感不存在');
        }
        $item = self::formatInspire($row, $admin);
        $wrap = [$item];
        self::attachMembers($aid, $wrap);
        $item = $wrap[0];
        if ($memberId > 0) {
            $liked = self::likedMap($aid, $memberId, [$row]);
            $item['liked'] = isset($liked[$id]) ? 1 : 0;
            $fav = self::favMap($aid, $memberId, [$row]);
            $item['favorited'] = isset($fav[$id]) ? 1 : 0;
            $authorId = intval($item['member_id'] ?? 0);
            $item['is_self'] = ($authorId > 0 && $authorId === $memberId) ? 1 : 0;
            $item['followed'] = ($authorId > 0 && $authorId !== $memberId && self::isFollowing($aid, $memberId, $authorId)) ? 1 : 0;
        } else {
            $item['liked'] = 0;
            $item['favorited'] = 0;
            $item['followed'] = 0;
            $item['is_self'] = 0;
        }
        try {
            $item['comment_count'] = intval(
                Db::name('digitalavatar_inspire_comment')
                    ->where('aid', $aid)
                    ->where('inspire_id', $id)
                    ->where('deleted_time', 0)
                    ->count()
            );
        } catch (\Throwable $e) {
            $item['comment_count'] = 0;
        }
        if (trim(strval($item['member_name'] ?? '')) === '') {
            $item['member_name'] = intval($item['member_id'] ?? 0) > 0 ? ('用户' . intval($item['member_id'])) : '官方';
        }
        return $item;
    }

    public static function shareFromTask(int $aid, int $bid, int $memberId, array $payload): array
    {
        self::ensureSchema($aid);
        $categoryId = intval($payload['category_id'] ?? 0);
        self::requireActiveCategory($aid, $categoryId);
        $taskId = intval($payload['task_id'] ?? 0);
        if ($taskId <= 0) {
            throw new \RuntimeException('请选择要分享的任务');
        }
        $task = DigitalAvatarTaskService::getDetail($aid, $taskId, $memberId);
        $cover = trim(strval($task['cover_url'] ?? ''));
        $video = trim(strval($task['video_url'] ?? ''));
        $audio = trim(strval($task['audio_url'] ?? ''));
        $mediaUrl = $video !== '' ? $video : ($audio !== '' ? $audio : $cover);
        if ($cover === '' && $mediaUrl === '') {
            throw new \RuntimeException('该任务还没有可分享的成品');
        }
        $mediaType = $video !== '' ? 'video' : ($audio !== '' ? 'audio' : 'image');
        $input = is_array($task['input'] ?? null) ? $task['input'] : [];
        $prompt = trim(strval($input['prompt'] ?? ($input['text'] ?? ($input['script'] ?? ($input['content'] ?? ($task['title'] ?? ''))))));
        $title = trim(strval($payload['title'] ?? ''));
        if ($title === '') {
            $title = mb_substr($prompt !== '' ? $prompt : strval($task['title'] ?? '灵感'), 0, 40);
        }
        $exists = Db::name('digitalavatar_inspire')
            ->where('aid', $aid)
            ->where('member_id', $memberId)
            ->where('task_id', $taskId)
            ->where('deleted_time', 0)
            ->find();
        if ($exists) {
            throw new \RuntimeException('这条任务已经分享过');
        }
        $userCategory = mb_substr(trim(strval($payload['user_category'] ?? '')), 0, 40);
        if ($userCategory === '') {
            throw new \RuntimeException('请填写我的分类');
        }
        try {
            self::saveUserCategory($aid, $memberId, ['name' => $userCategory]);
        } catch (\Throwable $e) {
        }
        $now = time();
        $id = intval(Db::name('digitalavatar_inspire')->insertGetId([
            'aid' => $aid,
            'bid' => $bid,
            'member_id' => $memberId,
            'category_id' => $categoryId,
            'task_id' => $taskId,
            'title' => mb_substr($title, 0, 120),
            'prompt' => mb_substr($prompt, 0, 4000),
            'cover_url' => mb_substr($cover !== '' ? $cover : $mediaUrl, 0, 500),
            'media_url' => mb_substr($mediaUrl, 0, 500),
            'media_type' => $mediaType,
            'duration_sec' => intval($task['duration_seconds'] ?? 0),
            'like_count' => 0,
            'recommended' => 0,
            'sort' => 0,
            'status' => 0,
            'audit_time' => 0,
            'audit_uid' => 0,
            'audit_remark' => '',
            'user_category' => $userCategory,
            'favorite_count' => 0,
            'remix_count' => 0,
            'extra_json' => json_encode(self::extraFromTask($task), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'deleted_time' => 0,
            'create_time' => $now,
            'update_time' => $now,
        ]));
        return self::formatInspire(Db::name('digitalavatar_inspire')->where('id', $id)->find() ?: [], true);
    }

    public static function saveInspire(int $aid, array $info, int $adminUid = 0): array
    {
        self::ensureSchema($aid);
        $id = intval($info['id'] ?? 0);
        $categoryId = intval($info['category_id'] ?? 0);
        self::requireCategory($aid, $categoryId);
        $cover = trim(strval($info['cover_url'] ?? ''));
        $mediaUrl = trim(strval($info['media_url'] ?? ''));
        if ($cover === '' && $mediaUrl === '') {
            throw new \RuntimeException('请上传封面或成品文件');
        }
        $mediaType = trim(strval($info['media_type'] ?? ''));
        if (!in_array($mediaType, ['image', 'video', 'audio'], true)) {
            $mediaType = self::guessMediaType($mediaUrl !== '' ? $mediaUrl : $cover);
        }
        $status = intval($info['status'] ?? 0);
        if (!in_array($status, [0, 1, 2], true)) {
            $status = 0;
        }
        $now = time();
        $data = [
            'aid' => $aid,
            'bid' => intval($info['bid'] ?? 0),
            'member_id' => intval($info['member_id'] ?? 0),
            'category_id' => $categoryId,
            'task_id' => intval($info['task_id'] ?? 0),
            'title' => mb_substr(trim(strval($info['title'] ?? '')), 0, 120),
            'prompt' => mb_substr(trim(strval($info['prompt'] ?? '')), 0, 4000),
            'cover_url' => mb_substr($cover !== '' ? $cover : $mediaUrl, 0, 500),
            'media_url' => mb_substr($mediaUrl !== '' ? $mediaUrl : $cover, 0, 500),
            'media_type' => $mediaType,
            'duration_sec' => max(0, intval($info['duration_sec'] ?? 0)),
            'like_count' => max(0, intval($info['like_count'] ?? 0)),
            'recommended' => intval($info['recommended'] ?? 0) === 1 ? 1 : 0,
            'sort' => intval($info['sort'] ?? 0),
            'status' => $status,
            'audit_remark' => mb_substr(trim(strval($info['audit_remark'] ?? '')), 0, 255),
            'update_time' => $now,
        ];
        if ($id > 0) {
            $exists = self::owned($aid, $id, true);
            unset($data['member_id'], $data['bid'], $data['task_id']);
            if (intval($exists['status'] ?? 0) !== $status) {
                $data['audit_time'] = $now;
                $data['audit_uid'] = $adminUid;
            }
            Db::name('digitalavatar_inspire')->where('aid', $aid)->where('id', $id)->update($data);
        } else {
            $data['audit_time'] = $status === 1 ? $now : 0;
            $data['audit_uid'] = $status === 1 ? $adminUid : 0;
            $data['create_time'] = $now;
            $data['deleted_time'] = 0;
            $data['extra_json'] = json_encode(is_array($info['extra'] ?? null) ? $info['extra'] : [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $id = intval(Db::name('digitalavatar_inspire')->insertGetId($data));
        }
        $item = self::formatInspire(Db::name('digitalavatar_inspire')->where('aid', $aid)->where('id', $id)->find() ?: [], true);
        $wrap = [$item];
        self::attachMembers($aid, $wrap);
        return $wrap[0];
    }

    public static function audit(int $aid, int $id, int $status, string $remark, int $adminUid): array
    {
        self::ensureSchema($aid);
        if (!in_array($status, [1, 2], true)) {
            throw new \RuntimeException('审核状态不正确');
        }
        self::owned($aid, $id, true);
        Db::name('digitalavatar_inspire')->where('aid', $aid)->where('id', $id)->update([
            'status' => $status,
            'audit_remark' => mb_substr(trim($remark), 0, 255),
            'audit_time' => time(),
            'audit_uid' => $adminUid,
            'update_time' => time(),
        ]);
        $item = self::formatInspire(self::owned($aid, $id, true), true);
        $wrap = [$item];
        self::attachMembers($aid, $wrap);
        return $wrap[0];
    }

    public static function deleteInspire(int $aid, int $id): void
    {
        self::ensureSchema($aid);
        self::owned($aid, $id, true);
        Db::name('digitalavatar_inspire')->where('aid', $aid)->where('id', $id)->update([
            'deleted_time' => time(),
            'update_time' => time(),
        ]);
    }

    public static function toggleLike(int $aid, int $memberId, int $id): array
    {
        self::ensureSchema($aid);
        if ($memberId <= 0) {
            throw new \RuntimeException('请先登录');
        }
        $row = self::owned($aid, $id, false);
        if (intval($row['status'] ?? 0) !== 1) {
            throw new \RuntimeException('灵感不存在');
        }
        $exists = Db::name('digitalavatar_inspire_like')
            ->where('aid', $aid)
            ->where('member_id', $memberId)
            ->where('inspire_id', $id)
            ->find();
        $now = time();
        if ($exists) {
            Db::name('digitalavatar_inspire_like')->where('id', intval($exists['id']))->delete();
            $liked = 0;
            $delta = -1;
        } else {
            Db::name('digitalavatar_inspire_like')->insert([
                'aid' => $aid,
                'member_id' => $memberId,
                'inspire_id' => $id,
                'create_time' => $now,
            ]);
            $liked = 1;
            $delta = 1;
        }
        $count = max(0, intval($row['like_count'] ?? 0) + $delta);
        Db::name('digitalavatar_inspire')->where('id', $id)->update([
            'like_count' => $count,
            'update_time' => $now,
        ]);
        return [
            'id' => $id,
            'liked' => $liked,
            'like_count' => $count,
        ];
    }

    public static function toggleFavorite(int $aid, int $memberId, int $id): array
    {
        self::ensureSchema($aid);
        if ($memberId <= 0) {
            throw new \RuntimeException('请先登录');
        }
        $row = self::owned($aid, $id, false);
        if (intval($row['status'] ?? 0) !== 1) {
            throw new \RuntimeException('灵感不存在');
        }
        $exists = Db::name('digitalavatar_inspire_fav')
            ->where('aid', $aid)
            ->where('member_id', $memberId)
            ->where('inspire_id', $id)
            ->find();
        $now = time();
        if ($exists) {
            Db::name('digitalavatar_inspire_fav')->where('id', intval($exists['id']))->delete();
            $favorited = 0;
            $delta = -1;
        } else {
            Db::name('digitalavatar_inspire_fav')->insert([
                'aid' => $aid,
                'member_id' => $memberId,
                'inspire_id' => $id,
                'create_time' => $now,
            ]);
            $favorited = 1;
            $delta = 1;
        }
        $count = max(0, intval($row['favorite_count'] ?? 0) + $delta);
        Db::name('digitalavatar_inspire')->where('id', $id)->update([
            'favorite_count' => $count,
            'update_time' => $now,
        ]);
        return [
            'id' => $id,
            'favorited' => $favorited,
            'favorite_count' => $count,
        ];
    }

    public static function toggleFollow(int $aid, int $memberId, int $targetId): array
    {
        self::ensureSchema($aid);
        if ($memberId <= 0) {
            throw new \RuntimeException('请先登录');
        }
        if ($targetId <= 0) {
            throw new \RuntimeException('用户不存在');
        }
        if ($targetId === $memberId) {
            throw new \RuntimeException('不能关注自己');
        }
        $target = Db::name('member')->where('aid', $aid)->where('id', $targetId)->field('id')->find();
        if (!$target) {
            throw new \RuntimeException('用户不存在');
        }
        $exists = Db::name('digitalavatar_inspire_follow')
            ->where('aid', $aid)
            ->where('member_id', $memberId)
            ->where('target_member_id', $targetId)
            ->find();
        $now = time();
        if ($exists) {
            Db::name('digitalavatar_inspire_follow')->where('id', intval($exists['id']))->delete();
            $followed = 0;
        } else {
            Db::name('digitalavatar_inspire_follow')->insert([
                'aid' => $aid,
                'member_id' => $memberId,
                'target_member_id' => $targetId,
                'create_time' => $now,
            ]);
            $followed = 1;
        }
        return [
            'member_id' => $targetId,
            'followed' => $followed,
            'fans' => self::followCount($aid, $targetId, true),
        ];
    }

    public static function recordRemix(int $aid, int $memberId, int $id): array
    {
        self::ensureSchema($aid);
        if ($memberId <= 0) {
            throw new \RuntimeException('请先登录');
        }
        $row = self::owned($aid, $id, false);
        $count = intval($row['remix_count'] ?? 0) + 1;
        Db::name('digitalavatar_inspire')->where('id', $id)->update([
            'remix_count' => $count,
            'update_time' => time(),
        ]);
        return [
            'id' => $id,
            'remix_count' => $count,
        ];
    }

    public static function memberHome(int $aid, int $viewerId, int $memberId, array $query): array
    {
        self::ensureSchema($aid);
        if ($memberId <= 0) {
            throw new \RuntimeException('用户不存在');
        }
        $member = Db::name('member')
            ->where('aid', $aid)
            ->where('id', $memberId)
            ->field('id,nickname,tel,headimg,yonghujianjie')
            ->find();
        if (!$member) {
            throw new \RuntimeException('用户不存在');
        }
        $page = max(1, intval($query['page'] ?? 1));
        $limit = max(1, min(50, intval($query['limit'] ?? 50)));
        $userCategory = trim(strval($query['user_category'] ?? ($query['category'] ?? '')));
        $sortKey = strtolower(trim(strval($query['sort'] ?? 'time_desc')));
        $timeAsc = in_array($sortKey, ['time_asc', 'asc', '1'], true);
        $orderSql = $timeAsc ? 'create_time asc,id asc' : 'create_time desc,id desc';
        $homeQuery = function () use ($aid, $memberId) {
            return Db::name('digitalavatar_inspire')
                ->where('aid', $aid)
                ->where('member_id', $memberId)
                ->where('deleted_time', 0)
                ->where('status', 1);
        };
        $listQuery = $homeQuery();
        if ($userCategory !== '') {
            $listQuery->where('user_category', $userCategory);
        }
        $total = intval((clone $listQuery)->count());
        $rows = $listQuery->page($page, $limit)->order($orderSql)->select()->toArray();
        $liked = self::likedMap($aid, $viewerId, $rows);
        $list = [];
        foreach ($rows as $row) {
            $item = self::formatInspire($row, false, true);
            $item['liked'] = isset($liked[intval($row['id'])]) ? 1 : 0;
            $list[] = $item;
        }
        $catRows = [];
        try {
            $catRows = $homeQuery()
                ->where('user_category', '<>', '')
                ->field('user_category, COUNT(*) AS num')
                ->group('user_category')
                ->order('num desc,user_category asc')
                ->select()
                ->toArray();
        } catch (\Throwable $e) {
            $catRows = [];
        }
        $categories = [];
        foreach ($catRows as $catRow) {
            $name = trim(strval($catRow['user_category'] ?? ''));
            if ($name === '') {
                continue;
            }
            $categories[] = [
                'name' => $name,
                'count' => intval($catRow['num'] ?? 0),
            ];
        }
        $likeTotal = 0;
        $remixTotal = 0;
        $workTotal = $total;
        try {
            $sumRow = Db::name('digitalavatar_inspire')
                ->where('aid', $aid)
                ->where('member_id', $memberId)
                ->where('deleted_time', 0)
                ->where('status', 1)
                ->field('SUM(like_count) AS like_total, SUM(remix_count) AS remix_total, COUNT(*) AS work_total')
                ->find();
            $likeTotal = intval($sumRow['like_total'] ?? 0);
            $remixTotal = intval($sumRow['remix_total'] ?? 0);
            $workTotal = intval($sumRow['work_total'] ?? 0);
        } catch (\Throwable $e) {
        }
        $fans = self::followCount($aid, $memberId, true);
        $follows = self::followCount($aid, $memberId, false);
        $name = trim(strval($member['nickname'] ?? ''));
        if ($name === '') {
            $name = '用户' . $memberId;
        }
        return [
            'member' => [
                'id' => $memberId,
                'nickname' => $name,
                'headimg' => strval($member['headimg'] ?? ''),
                'bio' => strval($member['yonghujianjie'] ?? ''),
                'tel' => strval($member['tel'] ?? ''),
            ],
            'stats' => [
                'fans' => $fans,
                'follows' => $follows,
                'likes' => $likeTotal,
                'remixes' => $remixTotal,
                'works' => $workTotal,
                'fans_text' => self::formatCompact($fans),
                'follows_text' => self::formatCompact($follows),
                'likes_text' => self::formatCompact($likeTotal),
                'remixes_text' => self::formatCompact($remixTotal),
            ],
            'followed' => ($viewerId > 0 && $viewerId !== $memberId && self::isFollowing($aid, $viewerId, $memberId)) ? 1 : 0,
            'is_self' => ($viewerId > 0 && $viewerId === $memberId) ? 1 : 0,
            'categories' => $categories,
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'user_category' => $userCategory,
            'sort' => $timeAsc ? 'time_asc' : 'time_desc',
        ];
    }

    public static function listUserCategories(int $aid, int $memberId): array
    {
        self::ensureSchema($aid);
        if ($memberId <= 0) {
            return [];
        }
        self::seedUserCategories($aid, $memberId);
        try {
            $rows = Db::name('digitalavatar_inspire_user_category')
                ->where('aid', $aid)
                ->where('member_id', $memberId)
                ->order('update_time desc,id desc')
                ->select()
                ->toArray();
        } catch (\Throwable $e) {
            return [];
        }
        $list = [];
        foreach ($rows as $row) {
            $name = trim(strval($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $list[] = [
                'id' => intval($row['id'] ?? 0),
                'name' => $name,
            ];
        }
        return $list;
    }

    public static function saveUserCategory(int $aid, int $memberId, array $info): array
    {
        self::ensureSchema($aid);
        if ($memberId <= 0) {
            throw new \RuntimeException('请先登录');
        }
        $id = intval($info['id'] ?? 0);
        $name = mb_substr(trim(strval($info['name'] ?? '')), 0, 40);
        if ($name === '') {
            throw new \RuntimeException('请填写分类名称');
        }
        $dup = Db::name('digitalavatar_inspire_user_category')
            ->where('aid', $aid)
            ->where('member_id', $memberId)
            ->where('name', $name);
        if ($id > 0) {
            $dup->where('id', '<>', $id);
        }
        $exists = $dup->find();
        if ($exists) {
            if ($id <= 0) {
                return [
                    'id' => intval($exists['id'] ?? 0),
                    'name' => strval($exists['name'] ?? $name),
                ];
            }
            throw new \RuntimeException('分类名称已存在');
        }
        $now = time();
        if ($id > 0) {
            $row = Db::name('digitalavatar_inspire_user_category')
                ->where('aid', $aid)
                ->where('member_id', $memberId)
                ->where('id', $id)
                ->find();
            if (!$row) {
                throw new \RuntimeException('分类不存在');
            }
            $old = trim(strval($row['name'] ?? ''));
            Db::name('digitalavatar_inspire_user_category')->where('id', $id)->update([
                'name' => $name,
                'update_time' => $now,
            ]);
            if ($old !== '' && $old !== $name) {
                Db::name('digitalavatar_inspire')->where('aid', $aid)->where('member_id', $memberId)->where('user_category', $old)->update([
                    'user_category' => $name,
                    'update_time' => $now,
                ]);
            }
        } else {
            $id = intval(Db::name('digitalavatar_inspire_user_category')->insertGetId([
                'aid' => $aid,
                'member_id' => $memberId,
                'name' => $name,
                'create_time' => $now,
                'update_time' => $now,
            ]));
        }
        return [
            'id' => $id,
            'name' => $name,
        ];
    }

    public static function deleteUserCategory(int $aid, int $memberId, int $id): array
    {
        self::ensureSchema($aid);
        $row = Db::name('digitalavatar_inspire_user_category')
            ->where('aid', $aid)
            ->where('member_id', $memberId)
            ->where('id', $id)
            ->find();
        if (!$row) {
            throw new \RuntimeException('分类不存在');
        }
        $name = trim(strval($row['name'] ?? ''));
        Db::name('digitalavatar_inspire_user_category')->where('id', $id)->delete();
        if ($name !== '') {
            Db::name('digitalavatar_inspire')->where('aid', $aid)->where('member_id', $memberId)->where('user_category', $name)->update([
                'user_category' => '',
                'update_time' => time(),
            ]);
        }
        return ['id' => $id];
    }

    public static function listComments(int $aid, int $inspireId, int $page = 1, int $limit = 10): array
    {
        self::ensureSchema($aid);
        self::owned($aid, $inspireId, false);
        $page = max(1, $page);
        $limit = max(1, min(50, $limit));
        $builder = Db::name('digitalavatar_inspire_comment')
            ->where('aid', $aid)
            ->where('inspire_id', $inspireId)
            ->where('deleted_time', 0)
            ->where('parent_id', 0);
        $total = intval((clone $builder)->count());
        $allCount = intval(
            Db::name('digitalavatar_inspire_comment')
                ->where('aid', $aid)
                ->where('inspire_id', $inspireId)
                ->where('deleted_time', 0)
                ->count()
        );
        $rows = $builder->page($page, $limit)->order('id desc')->select()->toArray();
        $list = [];
        $ids = [];
        foreach ($rows as $row) {
            $item = self::formatComment($row);
            $list[] = $item;
            $ids[] = intval($item['id']);
        }
        $replyMap = [];
        if ($ids) {
            $replyRows = Db::name('digitalavatar_inspire_comment')
                ->where('aid', $aid)
                ->where('inspire_id', $inspireId)
                ->where('deleted_time', 0)
                ->whereIn('parent_id', $ids)
                ->order('id asc')
                ->select()
                ->toArray();
            $replyList = [];
            foreach ($replyRows as $replyRow) {
                $replyList[] = self::formatComment($replyRow);
            }
            self::attachMembers($aid, $replyList);
            foreach ($replyList as $reply) {
                $parentId = intval($reply['parent_id'] ?? 0);
                if (!isset($replyMap[$parentId])) {
                    $replyMap[$parentId] = [];
                }
                $replyMap[$parentId][] = $reply;
            }
        }
        self::attachMembers($aid, $list);
        foreach ($list as &$item) {
            if (trim(strval($item['member_name'] ?? '')) === '') {
                $item['member_name'] = intval($item['member_id'] ?? 0) > 0 ? ('用户' . intval($item['member_id'])) : '用户';
            }
            $item['replies'] = $replyMap[intval($item['id'])] ?? [];
        }
        unset($item);
        return [
            'list' => $list,
            'total' => $total,
            'all_count' => $allCount,
            'page' => $page,
            'limit' => $limit,
        ];
    }

    public static function addComment(int $aid, int $memberId, int $inspireId, string $content, int $parentId = 0): array
    {
        self::ensureSchema($aid);
        if ($memberId <= 0) {
            throw new \RuntimeException('请先登录');
        }
        $content = trim($content);
        if ($content === '') {
            throw new \RuntimeException('请输入评论内容');
        }
        if (mb_strlen($content) > 500) {
            throw new \RuntimeException('评论最多 500 个字');
        }
        self::owned($aid, $inspireId, false);
        if ($parentId > 0) {
            $parent = Db::name('digitalavatar_inspire_comment')
                ->where('aid', $aid)
                ->where('id', $parentId)
                ->where('inspire_id', $inspireId)
                ->where('deleted_time', 0)
                ->find();
            if (!$parent) {
                throw new \RuntimeException('原评论不存在');
            }
            if (intval($parent['parent_id'] ?? 0) > 0) {
                $parentId = intval($parent['parent_id']);
            }
        } else {
            $parentId = 0;
        }
        $id = intval(Db::name('digitalavatar_inspire_comment')->insertGetId([
            'aid' => $aid,
            'inspire_id' => $inspireId,
            'member_id' => $memberId,
            'parent_id' => $parentId,
            'content' => mb_substr($content, 0, 500),
            'deleted_time' => 0,
            'create_time' => time(),
        ]));
        $item = self::formatComment(Db::name('digitalavatar_inspire_comment')->where('id', $id)->find() ?: []);
        $wrap = [$item];
        self::attachMembers($aid, $wrap);
        $item = $wrap[0];
        if (trim(strval($item['member_name'] ?? '')) === '') {
            $item['member_name'] = '用户' . $memberId;
        }
        $item['replies'] = [];
        $item['comment_count'] = intval(
            Db::name('digitalavatar_inspire_comment')
                ->where('aid', $aid)
                ->where('inspire_id', $inspireId)
                ->where('deleted_time', 0)
                ->count()
        );
        return $item;
    }

    public static function adminCommentList(int $aid, array $query): array
    {
        self::ensureSchema($aid);
        $page = max(1, intval($query['page'] ?? 1));
        $limit = max(1, min(100, intval($query['limit'] ?? 20)));
        $keyword = trim(strval($query['keyword'] ?? ''));
        $inspireId = intval($query['inspire_id'] ?? 0);
        $type = trim(strval($query['type'] ?? ''));
        $builder = Db::name('digitalavatar_inspire_comment')
            ->alias('c')
            ->leftJoin('digitalavatar_inspire i', 'i.id = c.inspire_id AND i.aid = c.aid')
            ->where('c.aid', $aid)
            ->where('c.deleted_time', 0);
        if ($inspireId > 0) {
            $builder->where('c.inspire_id', $inspireId);
        }
        if ($type === 'reply') {
            $builder->where('c.parent_id', '>', 0);
        } elseif ($type === 'comment') {
            $builder->where('c.parent_id', 0);
        }
        if ($keyword !== '') {
            $builder->where(function ($q) use ($keyword) {
                $q->whereLike('c.content', '%' . $keyword . '%')->whereOr('i.title', 'like', '%' . $keyword . '%');
            });
        }
        $total = intval((clone $builder)->count());
        $rows = $builder
            ->field('c.*, i.title as inspire_title, i.cover_url as inspire_cover')
            ->page($page, $limit)
            ->order('c.id desc')
            ->select()
            ->toArray();
        $list = [];
        $parentIds = [];
        foreach ($rows as $row) {
            $item = self::formatComment($row);
            $item['inspire_title'] = strval($row['inspire_title'] ?? '');
            $item['inspire_cover'] = strval($row['inspire_cover'] ?? '');
            $item['type_text'] = intval($item['parent_id'] ?? 0) > 0 ? '回复' : '评论';
            $list[] = $item;
            if (intval($item['parent_id'] ?? 0) > 0) {
                $parentIds[intval($item['parent_id'])] = 1;
            }
        }
        $parentMap = [];
        if ($parentIds) {
            $parents = Db::name('digitalavatar_inspire_comment')
                ->where('aid', $aid)
                ->whereIn('id', array_keys($parentIds))
                ->select()
                ->toArray();
            foreach ($parents as $parent) {
                $parentMap[intval($parent['id'] ?? 0)] = strval($parent['content'] ?? '');
            }
        }
        self::attachMembers($aid, $list);
        foreach ($list as &$item) {
            $item['parent_content'] = strval($parentMap[intval($item['parent_id'] ?? 0)] ?? '');
            if (trim(strval($item['member_name'] ?? '')) === '') {
                $item['member_name'] = intval($item['member_id'] ?? 0) > 0 ? ('用户' . intval($item['member_id'])) : '用户';
            }
        }
        unset($item);
        return [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ];
    }

    public static function saveComment(int $aid, array $info): array
    {
        self::ensureSchema($aid);
        $id = intval($info['id'] ?? 0);
        if ($id <= 0) {
            throw new \RuntimeException('请传入评论ID');
        }
        $row = Db::name('digitalavatar_inspire_comment')
            ->where('aid', $aid)
            ->where('id', $id)
            ->where('deleted_time', 0)
            ->find();
        if (!$row) {
            throw new \RuntimeException('评论不存在');
        }
        $content = mb_substr(trim(strval($info['content'] ?? '')), 0, 500);
        if ($content === '') {
            throw new \RuntimeException('请填写评论内容');
        }
        Db::name('digitalavatar_inspire_comment')->where('aid', $aid)->where('id', $id)->update([
            'content' => $content,
        ]);
        $item = self::formatComment(Db::name('digitalavatar_inspire_comment')->where('id', $id)->find() ?: []);
        $wrap = [$item];
        self::attachMembers($aid, $wrap);
        return $wrap[0];
    }

    public static function deleteComment(int $aid, int $id): array
    {
        self::ensureSchema($aid);
        if ($id <= 0) {
            throw new \RuntimeException('请传入评论ID');
        }
        $row = Db::name('digitalavatar_inspire_comment')
            ->where('aid', $aid)
            ->where('id', $id)
            ->where('deleted_time', 0)
            ->find();
        if (!$row) {
            throw new \RuntimeException('评论不存在');
        }
        $now = time();
        Db::name('digitalavatar_inspire_comment')->where('aid', $aid)->where('id', $id)->update([
            'deleted_time' => $now,
        ]);
        Db::name('digitalavatar_inspire_comment')
            ->where('aid', $aid)
            ->where('parent_id', $id)
            ->where('deleted_time', 0)
            ->update([
                'deleted_time' => $now,
            ]);
        return ['id' => $id];
    }

    public static function recentShareableTasks(int $aid, int $memberId, int $limit = 20): array
    {
        $limit = max(1, min(40, $limit));
        $rows = Db::name('digitalavatar_task')
            ->where('aid', $aid)
            ->where('member_id', $memberId)
            ->where('deleted_time', 0)
            ->where('status', 2)
            ->order('id desc')
            ->limit($limit)
            ->select()
            ->toArray();
        $sharedIds = [];
        if ($rows) {
            $taskIds = array_map(function ($row) {
                return intval($row['id'] ?? 0);
            }, $rows);
            $shared = Db::name('digitalavatar_inspire')
                ->where('aid', $aid)
                ->where('member_id', $memberId)
                ->where('deleted_time', 0)
                ->whereIn('task_id', $taskIds)
                ->column('task_id');
            foreach ((array) $shared as $taskId) {
                $sharedIds[intval($taskId)] = 1;
            }
        }
        $list = [];
        foreach ($rows as $row) {
            $task = DigitalAvatarTaskService::formatTask($row);
            $input = is_array($task['input'] ?? null) ? $task['input'] : [];
            $cover = trim(strval($task['cover_url'] ?? ''));
            $media = trim(strval($task['video_url'] ?? ($task['audio_url'] ?? $cover)));
            if ($cover === '' && $media === '') {
                continue;
            }
            $list[] = [
                'id' => intval($task['id'] ?? 0),
                'title' => strval($task['title'] ?? ''),
                'cover_url' => $cover !== '' ? $cover : $media,
                'media_type' => trim(strval($task['video_url'] ?? '')) !== '' ? 'video' : (trim(strval($task['audio_url'] ?? '')) !== '' ? 'audio' : 'image'),
                'shared' => isset($sharedIds[intval($task['id'] ?? 0)]) ? 1 : 0,
                'model_name' => strval($task['model_name'] ?? ($task['model_code'] ?? '')),
                'prompt' => mb_substr(trim(strval($input['prompt'] ?? ($input['text'] ?? ($input['script'] ?? ($input['content'] ?? ($task['title'] ?? '')))))), 0, 400),
                'metric_text' => strval($task['billing_quantity_text'] ?? ($task['metric_text'] ?? '')),
            ];
        }
        return $list;
    }

    private static function ensureCategoryTable(): void
    {
        $table = self::tableName('digitalavatar_inspire_category');
        if ($table === '' || self::tableExists($table)) {
            return;
        }
        try {
            Db::execute(
                "CREATE TABLE IF NOT EXISTS `" . $table . "` (
                  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                  `aid` int(11) NOT NULL DEFAULT '0',
                  `name` varchar(40) NOT NULL DEFAULT '',
                  `badge` varchar(12) NOT NULL DEFAULT '',
                  `sort` int(11) NOT NULL DEFAULT '0',
                  `status` tinyint(1) NOT NULL DEFAULT '1',
                  `create_time` int(11) NOT NULL DEFAULT '0',
                  `update_time` int(11) NOT NULL DEFAULT '0',
                  PRIMARY KEY (`id`),
                  KEY `idx_aid_sort` (`aid`,`status`,`sort`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='AI数字人灵感分类'"
            );
        } catch (\Throwable $e) {
            if (stripos($e->getMessage(), 'already exists') === false) {
                Log::error('digitalavatar inspire category table: ' . $e->getMessage());
            }
        }
    }

    private static function ensureInspireTable(): void
    {
        $table = self::tableName('digitalavatar_inspire');
        if ($table === '' || self::tableExists($table)) {
            return;
        }
        try {
            Db::execute(
                "CREATE TABLE IF NOT EXISTS `" . $table . "` (
                  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                  `aid` int(11) NOT NULL DEFAULT '0',
                  `bid` int(11) NOT NULL DEFAULT '0',
                  `member_id` int(11) NOT NULL DEFAULT '0',
                  `category_id` int(11) NOT NULL DEFAULT '0',
                  `task_id` int(11) NOT NULL DEFAULT '0',
                  `title` varchar(120) NOT NULL DEFAULT '',
                  `prompt` text,
                  `cover_url` varchar(500) NOT NULL DEFAULT '',
                  `media_url` varchar(500) NOT NULL DEFAULT '',
                  `media_type` varchar(20) NOT NULL DEFAULT 'image',
                  `duration_sec` int(11) NOT NULL DEFAULT '0',
                  `like_count` int(11) NOT NULL DEFAULT '0',
                  `recommended` tinyint(1) NOT NULL DEFAULT '0',
                  `sort` int(11) NOT NULL DEFAULT '0',
                  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0待审核 1通过 2拒绝',
                  `audit_time` int(11) NOT NULL DEFAULT '0',
                  `audit_uid` int(11) NOT NULL DEFAULT '0',
                  `audit_remark` varchar(255) NOT NULL DEFAULT '',
                  `extra_json` text,
                  `deleted_time` int(11) NOT NULL DEFAULT '0',
                  `create_time` int(11) NOT NULL DEFAULT '0',
                  `update_time` int(11) NOT NULL DEFAULT '0',
                  PRIMARY KEY (`id`),
                  KEY `idx_feed` (`aid`,`deleted_time`,`status`,`recommended`,`sort`,`id`),
                  KEY `idx_category` (`aid`,`category_id`,`deleted_time`,`status`,`id`),
                  KEY `idx_member_task` (`aid`,`member_id`,`task_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='AI数字人灵感'"
            );
        } catch (\Throwable $e) {
            if (stripos($e->getMessage(), 'already exists') === false) {
                Log::error('digitalavatar inspire table: ' . $e->getMessage());
            }
        }
    }

    private static function ensureLikeTable(): void
    {
        $table = self::tableName('digitalavatar_inspire_like');
        if ($table === '' || self::tableExists($table)) {
            return;
        }
        try {
            Db::execute(
                "CREATE TABLE IF NOT EXISTS `" . $table . "` (
                  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                  `aid` int(11) NOT NULL DEFAULT '0',
                  `member_id` int(11) NOT NULL DEFAULT '0',
                  `inspire_id` int(11) NOT NULL DEFAULT '0',
                  `create_time` int(11) NOT NULL DEFAULT '0',
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `uk_like` (`aid`,`member_id`,`inspire_id`),
                  KEY `idx_inspire` (`inspire_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='AI数字人灵感点赞'"
            );
        } catch (\Throwable $e) {
            if (stripos($e->getMessage(), 'already exists') === false) {
                Log::error('digitalavatar inspire like table: ' . $e->getMessage());
            }
        }
    }

    private static function ensureCommentTable(): void
    {
        $table = self::tableName('digitalavatar_inspire_comment');
        if ($table === '' || self::tableExists($table)) {
            return;
        }
        try {
            Db::execute(
                "CREATE TABLE IF NOT EXISTS `" . $table . "` (
                  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                  `aid` int(11) NOT NULL DEFAULT '0',
                  `inspire_id` int(11) NOT NULL DEFAULT '0',
                  `member_id` int(11) NOT NULL DEFAULT '0',
                  `content` varchar(500) NOT NULL DEFAULT '',
                  `deleted_time` int(11) NOT NULL DEFAULT '0',
                  `create_time` int(11) NOT NULL DEFAULT '0',
                  PRIMARY KEY (`id`),
                  KEY `idx_inspire` (`aid`,`inspire_id`,`deleted_time`,`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='AI数字人灵感评论'"
            );
        } catch (\Throwable $e) {
            if (stripos($e->getMessage(), 'already exists') === false) {
                Log::error('digitalavatar inspire comment table: ' . $e->getMessage());
            }
        }
    }

    private static function ensureFavTable(): void
    {
        $table = self::tableName('digitalavatar_inspire_fav');
        if ($table === '' || self::tableExists($table)) {
            return;
        }
        try {
            Db::execute(
                "CREATE TABLE IF NOT EXISTS `" . $table . "` (
                  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                  `aid` int(11) NOT NULL DEFAULT '0',
                  `member_id` int(11) NOT NULL DEFAULT '0',
                  `inspire_id` int(11) NOT NULL DEFAULT '0',
                  `create_time` int(11) NOT NULL DEFAULT '0',
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `uk_fav` (`aid`,`member_id`,`inspire_id`),
                  KEY `idx_inspire` (`inspire_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='AI数字人灵感收藏'"
            );
        } catch (\Throwable $e) {
            if (stripos($e->getMessage(), 'already exists') === false) {
                Log::error('digitalavatar inspire fav table: ' . $e->getMessage());
            }
        }
    }

    private static function ensureFollowTable(): void
    {
        $table = self::tableName('digitalavatar_inspire_follow');
        if ($table === '' || self::tableExists($table)) {
            return;
        }
        try {
            Db::execute(
                "CREATE TABLE IF NOT EXISTS `" . $table . "` (
                  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                  `aid` int(11) NOT NULL DEFAULT '0',
                  `member_id` int(11) NOT NULL DEFAULT '0',
                  `target_member_id` int(11) NOT NULL DEFAULT '0',
                  `create_time` int(11) NOT NULL DEFAULT '0',
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `uk_follow` (`aid`,`member_id`,`target_member_id`),
                  KEY `idx_target` (`aid`,`target_member_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='AI数字人灵感关注'"
            );
        } catch (\Throwable $e) {
            if (stripos($e->getMessage(), 'already exists') === false) {
                Log::error('digitalavatar inspire follow table: ' . $e->getMessage());
            }
        }
    }

    private static function ensureUserCategoryTable(): void
    {
        $table = self::tableName('digitalavatar_inspire_user_category');
        if ($table === '' || self::tableExists($table)) {
            return;
        }
        try {
            Db::execute(
                "CREATE TABLE IF NOT EXISTS `" . $table . "` (
                  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                  `aid` int(11) NOT NULL DEFAULT '0',
                  `member_id` int(11) NOT NULL DEFAULT '0',
                  `name` varchar(40) NOT NULL DEFAULT '',
                  `create_time` int(11) NOT NULL DEFAULT '0',
                  `update_time` int(11) NOT NULL DEFAULT '0',
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `uk_member_name` (`aid`,`member_id`,`name`),
                  KEY `idx_member` (`aid`,`member_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='AI数字人会员灵感分类'"
            );
        } catch (\Throwable $e) {
            if (stripos($e->getMessage(), 'already exists') === false) {
                Log::error('digitalavatar inspire user category table: ' . $e->getMessage());
            }
        }
    }

    private static function seedUserCategories(int $aid, int $memberId): void
    {
        self::ensureUserCategoryTable();
        if ($aid <= 0 || $memberId <= 0) {
            return;
        }
        try {
            $names = Db::name('digitalavatar_inspire')
                ->where('aid', $aid)
                ->where('member_id', $memberId)
                ->where('deleted_time', 0)
                ->where('user_category', '<>', '')
                ->group('user_category')
                ->column('user_category');
        } catch (\Throwable $e) {
            return;
        }
        $now = time();
        foreach ((array) $names as $name) {
            $name = mb_substr(trim(strval($name)), 0, 40);
            if ($name === '') {
                continue;
            }
            $exists = Db::name('digitalavatar_inspire_user_category')
                ->where('aid', $aid)
                ->where('member_id', $memberId)
                ->where('name', $name)
                ->find();
            if ($exists) {
                continue;
            }
            try {
                Db::name('digitalavatar_inspire_user_category')->insert([
                    'aid' => $aid,
                    'member_id' => $memberId,
                    'name' => $name,
                    'create_time' => $now,
                    'update_time' => $now,
                ]);
            } catch (\Throwable $e) {
            }
        }
    }

    private static function ensureInspireColumns(): void
    {
        self::ensureColumn('digitalavatar_inspire', 'user_category', "ADD COLUMN `user_category` varchar(40) NOT NULL DEFAULT '' AFTER `category_id`");
        self::ensureColumn('digitalavatar_inspire', 'favorite_count', "ADD COLUMN `favorite_count` int(11) NOT NULL DEFAULT '0' AFTER `like_count`");
        self::ensureColumn('digitalavatar_inspire', 'remix_count', "ADD COLUMN `remix_count` int(11) NOT NULL DEFAULT '0' AFTER `favorite_count`");
    }

    private static function ensureCommentColumns(): void
    {
        self::ensureColumn('digitalavatar_inspire_comment', 'parent_id', "ADD COLUMN `parent_id` int(11) NOT NULL DEFAULT '0' AFTER `member_id`");
    }

    private static function ensureColumn(string $tableName, string $column, string $ddl): void
    {
        $table = self::tableName($tableName);
        if ($table === '' || $column === '') {
            return;
        }
        try {
            $columns = Db::query("SHOW COLUMNS FROM `" . $table . "` LIKE '" . str_replace("'", '', $column) . "'");
            if ($columns) {
                return;
            }
        } catch (\Throwable $e) {
            return;
        }
        try {
            Db::execute('ALTER TABLE `' . $table . '` ' . $ddl);
        } catch (\Throwable $e) {
            if (stripos($e->getMessage(), 'Duplicate column') === false) {
                Log::error('digitalavatar column ' . $tableName . '.' . $column . ': ' . $e->getMessage());
            }
        }
    }

    private static function seedCategories(int $aid): void
    {
        if ($aid <= 0 || isset(self::$seededAids[$aid])) {
            return;
        }
        try {
            $count = intval(Db::name('digitalavatar_inspire_category')->where('aid', $aid)->count());
            if ($count > 0) {
                self::$seededAids[$aid] = true;
                return;
            }
        } catch (\Throwable $e) {
            return;
        }
        $lockName = 'da_inspire_cat_' . $aid;
        $locked = 0;
        try {
            $lockRow = Db::query("SELECT GET_LOCK('" . str_replace("'", '', $lockName) . "', 1) AS locked");
            $locked = intval($lockRow[0]['locked'] ?? 0);
        } catch (\Throwable $e) {
            $locked = 0;
        }
        try {
            $count = intval(Db::name('digitalavatar_inspire_category')->where('aid', $aid)->count());
            if ($count <= 0) {
            $now = time();
            foreach (self::DEFAULT_CATEGORIES as $item) {
                $exists = Db::name('digitalavatar_inspire_category')
                    ->where('aid', $aid)
                    ->where('name', $item['name'])
                    ->find();
                if ($exists) {
                    continue;
                }
                Db::name('digitalavatar_inspire_category')->insert([
                    'aid' => $aid,
                    'name' => $item['name'],
                    'badge' => $item['badge'],
                    'sort' => intval($item['sort']),
                    'status' => 1,
                    'create_time' => $now,
                    'update_time' => $now,
                ]);
            }
            }
            self::$seededAids[$aid] = true;
        } catch (\Throwable $e) {
        }
        if ($locked === 1) {
            try {
                Db::query("SELECT RELEASE_LOCK('" . str_replace("'", '', $lockName) . "')");
            } catch (\Throwable $e) {
            }
        }
    }

    private static function syncMenus(int $aid): void
    {
        if (self::$menusReady || $aid <= 0) {
            return;
        }
        self::$menusReady = true;
        try {
            PluginMenu::registerFromConfig('digitalavatar', $aid);
        } catch (\Throwable $e) {
        }
    }

    private static function tableName(string $name): string
    {
        $prefix = strval(config('database.connections.mysql.prefix', ''));
        return str_replace('`', '', $prefix . $name);
    }

    private static function tableExists(string $table): bool
    {
        try {
            $exists = Db::query("SHOW TABLES LIKE '" . str_replace("'", '', $table) . "'");
            return !empty($exists);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function owned(int $aid, int $id, bool $allowHidden): array
    {
        if ($id <= 0) {
            throw new \RuntimeException('灵感不存在');
        }
        $query = Db::name('digitalavatar_inspire')->where('aid', $aid)->where('id', $id)->where('deleted_time', 0);
        $row = $query->find();
        if (!$row) {
            throw new \RuntimeException('灵感不存在');
        }
        if (!$allowHidden && intval($row['status'] ?? 0) !== 1) {
            throw new \RuntimeException('灵感不存在');
        }
        return $row;
    }

    private static function requireActiveCategory(int $aid, int $categoryId): array
    {
        $row = self::requireCategory($aid, $categoryId);
        if (intval($row['status'] ?? 0) !== 1) {
            throw new \RuntimeException('该分类已停用');
        }
        return $row;
    }

    private static function requireCategory(int $aid, int $categoryId): array
    {
        if ($categoryId <= 0) {
            throw new \RuntimeException('请选择灵感分类');
        }
        $row = Db::name('digitalavatar_inspire_category')->where('aid', $aid)->where('id', $categoryId)->find();
        if (!$row) {
            throw new \RuntimeException('分类不存在');
        }
        return $row;
    }

    private static function likedMap(int $aid, int $memberId, array $rows): array
    {
        $map = [];
        if ($memberId <= 0 || !$rows) {
            return $map;
        }
        $ids = [];
        foreach ($rows as $row) {
            $id = intval($row['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        if (!$ids) {
            return $map;
        }
        $likes = Db::name('digitalavatar_inspire_like')
            ->where('aid', $aid)
            ->where('member_id', $memberId)
            ->whereIn('inspire_id', $ids)
            ->column('inspire_id');
        foreach ((array) $likes as $inspireId) {
            $map[intval($inspireId)] = 1;
        }
        return $map;
    }

    private static function favMap(int $aid, int $memberId, array $rows): array
    {
        $map = [];
        if ($memberId <= 0 || !$rows) {
            return $map;
        }
        $ids = [];
        foreach ($rows as $row) {
            $id = intval($row['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        if (!$ids) {
            return $map;
        }
        try {
            $favs = Db::name('digitalavatar_inspire_fav')
                ->where('aid', $aid)
                ->where('member_id', $memberId)
                ->whereIn('inspire_id', $ids)
                ->column('inspire_id');
        } catch (\Throwable $e) {
            return $map;
        }
        foreach ((array) $favs as $inspireId) {
            $map[intval($inspireId)] = 1;
        }
        return $map;
    }

    private static function isFollowing(int $aid, int $memberId, int $targetId): bool
    {
        if ($memberId <= 0 || $targetId <= 0 || $memberId === $targetId) {
            return false;
        }
        try {
            $row = Db::name('digitalavatar_inspire_follow')
                ->where('aid', $aid)
                ->where('member_id', $memberId)
                ->where('target_member_id', $targetId)
                ->find();
            return !empty($row);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function followCount(int $aid, int $memberId, bool $asTarget): int
    {
        if ($memberId <= 0) {
            return 0;
        }
        try {
            $query = Db::name('digitalavatar_inspire_follow')->where('aid', $aid);
            if ($asTarget) {
                $query->where('target_member_id', $memberId);
            } else {
                $query->where('member_id', $memberId);
            }
            return intval($query->count());
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private static function attachMembers(int $aid, array &$list): void
    {
        $ids = [];
        foreach ($list as $item) {
            $mid = intval($item['member_id'] ?? 0);
            if ($mid > 0) {
                $ids[$mid] = 1;
            }
        }
        if (!$ids) {
            return;
        }
        $members = [];
        $memberRows = [];
        try {
            $memberRows = Db::name('member')->where('aid', $aid)->whereIn('id', array_keys($ids))->field('id,nickname,tel,headimg,yonghujianjie')->select()->toArray();
        } catch (\Throwable $e) {
            $memberRows = Db::name('member')->where('aid', $aid)->whereIn('id', array_keys($ids))->field('id,nickname,tel,headimg')->select()->toArray();
        }
        foreach ($memberRows as $member) {
            $members[intval($member['id'])] = $member;
        }
        foreach ($list as &$item) {
            $member = $members[intval($item['member_id'] ?? 0)] ?? [];
            $item['member_name'] = strval($member['nickname'] ?? '');
            $item['member_tel'] = strval($member['tel'] ?? '');
            $item['member_headimg'] = strval($member['headimg'] ?? '');
            $item['member_bio'] = strval($member['yonghujianjie'] ?? '');
        }
        unset($item);
    }

    private static function formatCategory(array $row): array
    {
        return [
            'id' => intval($row['id'] ?? 0),
            'aid' => intval($row['aid'] ?? 0),
            'name' => strval($row['name'] ?? ''),
            'badge' => strval($row['badge'] ?? ''),
            'sort' => intval($row['sort'] ?? 0),
            'status' => intval($row['status'] ?? 0),
            'create_time' => intval($row['create_time'] ?? 0),
            'update_time' => intval($row['update_time'] ?? 0),
        ];
    }

    private static function formatInspire(array $row, bool $admin, bool $light = false): array
    {
        $categoryId = intval($row['category_id'] ?? 0);
        $categoryName = '';
        if (!$light && $categoryId > 0) {
            try {
                $categoryName = strval(Db::name('digitalavatar_inspire_category')->where('id', $categoryId)->value('name'));
            } catch (\Throwable $e) {
            }
        }
        $status = intval($row['status'] ?? 0);
        $statusMap = [0 => '待审核', 1 => '已通过', 2 => '已拒绝'];
        $item = [
            'id' => intval($row['id'] ?? 0),
            'aid' => intval($row['aid'] ?? 0),
            'bid' => intval($row['bid'] ?? 0),
            'member_id' => intval($row['member_id'] ?? 0),
            'category_id' => $categoryId,
            'category_name' => $categoryName,
            'task_id' => intval($row['task_id'] ?? 0),
            'title' => strval($row['title'] ?? ''),
            'prompt' => strval($row['prompt'] ?? ''),
            'cover_url' => strval($row['cover_url'] ?? ''),
            'media_url' => strval($row['media_url'] ?? ''),
            'media_type' => strval($row['media_type'] ?? 'image'),
            'duration_sec' => intval($row['duration_sec'] ?? 0),
            'duration_text' => self::formatDuration(intval($row['duration_sec'] ?? 0)),
            'like_count' => intval($row['like_count'] ?? 0),
            'favorite_count' => intval($row['favorite_count'] ?? 0),
            'remix_count' => intval($row['remix_count'] ?? 0),
            'user_category' => strval($row['user_category'] ?? ''),
            'recommended' => intval($row['recommended'] ?? 0),
            'sort' => intval($row['sort'] ?? 0),
            'status' => $status,
            'status_text' => strval($statusMap[$status] ?? '待审核'),
            'create_time' => intval($row['create_time'] ?? 0),
            'create_time_text' => intval($row['create_time'] ?? 0) > 0 ? date('Y-m-d H:i:s', intval($row['create_time'])) : '',
            'create_date' => intval($row['create_time'] ?? 0) > 0 ? date('Y-m-d', intval($row['create_time'])) : '',
        ];
        $extra = [];
        if (!$light) {
            $raw = trim(strval($row['extra_json'] ?? ''));
            if ($raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $extra = $decoded;
                }
            }
        }
        $item['extra'] = $extra;
        $item['refs'] = is_array($extra['refs'] ?? null) ? $extra['refs'] : [];
        $item['model_name'] = strval($extra['model_name'] ?? '');
        $item['model_code'] = strval($extra['model_code'] ?? '');
        $item['task_type'] = strval($extra['task_type'] ?? '');
        $item['ratio'] = strval($extra['ratio'] ?? '');
        $item['style'] = strval($extra['style'] ?? '');
        $item['duration'] = strval($extra['duration'] ?? '');
        $item['avatar'] = is_array($extra['avatar'] ?? null) ? $extra['avatar'] : null;
        $item['voice'] = $extra['voice'] ?? null;
        $item['history_audio'] = $extra['history_audio'] ?? ($extra['historyAudio'] ?? null);
        $item['input'] = is_array($extra['input'] ?? null) ? $extra['input'] : [];
        if ($item['model_name'] === '' && $item['model_code'] !== '') {
            $item['model_name'] = $item['model_code'];
        }
        if ($item['media_type'] === 'image') {
            $item['metric_text'] = '1张';
        } elseif ($item['duration_text'] !== '') {
            $item['metric_text'] = $item['duration_text'];
        } else {
            $item['metric_text'] = '';
        }
        if ($admin) {
            $item['audit_time'] = intval($row['audit_time'] ?? 0);
            $item['audit_time_text'] = intval($row['audit_time'] ?? 0) > 0 ? date('Y-m-d H:i:s', intval($row['audit_time'])) : '';
            $item['audit_uid'] = intval($row['audit_uid'] ?? 0);
            $item['audit_remark'] = strval($row['audit_remark'] ?? '');
            $item['update_time'] = intval($row['update_time'] ?? 0);
        }
        return $item;
    }

    private static function formatDuration(int $seconds): string
    {
        if ($seconds <= 0) {
            return '';
        }
        $m = intval(floor($seconds / 60));
        $s = $seconds % 60;
        return sprintf('%02d:%02d', $m, $s);
    }

    private static function guessMediaType(string $url): string
    {
        $url = strtolower($url);
        if (preg_match('/\.(mp4|mov|webm|mkv|m4v)(\?|$)/i', $url)) {
            return 'video';
        }
        if (preg_match('/\.(mp3|wav|m4a|aac|ogg|flac)(\?|$)/i', $url)) {
            return 'audio';
        }
        return 'image';
    }

    private static function refsFromTask(array $task): array
    {
        $input = is_array($task['input'] ?? null) ? $task['input'] : [];
        $refs = [];
        $seen = [];
        $push = function ($url) use (&$refs, &$seen) {
            $url = trim(strval($url));
            if ($url === '' || isset($seen[$url])) {
                return;
            }
            if (!preg_match('#^(https?:)?//|^/#i', $url)) {
                return;
            }
            $seen[$url] = 1;
            $kind = 'image';
            if (preg_match('/\.(mp4|mov|webm|mkv|m4v)(\?|$)/i', $url)) {
                $kind = 'video';
            } elseif (preg_match('/\.(mp3|wav|m4a|aac|ogg|flac)(\?|$)/i', $url)) {
                $kind = 'audio';
            }
            $refs[] = ['kind' => $kind, 'url' => $url];
        };
        $push($input['cover_url'] ?? ($input['coverUrl'] ?? ''));
        $push($input['img_url'] ?? ($input['imgUrl'] ?? ''));
        $push($input['img'] ?? '');
        $push($input['avatar_cover'] ?? '');
        if (is_array($input['avatar'] ?? null)) {
            $push($input['avatar']['img'] ?? ($input['avatar']['coverUrl'] ?? ($input['avatar']['url'] ?? '')));
        }
        if (is_array($input['inspire'] ?? null)) {
            $push($input['inspire']['img'] ?? ($input['inspire']['url'] ?? ''));
        }
        if (is_array($input['reference'] ?? null)) {
            $push($input['reference']['img'] ?? ($input['reference']['url'] ?? ''));
        }
        $push($input['sample_url'] ?? '');
        $push($input['video_url'] ?? '');
        $push($input['audio_url'] ?? '');
        foreach (['images', 'refs', 'references', 'img_list'] as $key) {
            if (!is_array($input[$key] ?? null)) {
                continue;
            }
            foreach ($input[$key] as $item) {
                if (is_string($item)) {
                    $push($item);
                } elseif (is_array($item)) {
                    $push($item['url'] ?? ($item['img'] ?? ($item['coverUrl'] ?? ($item['cover_url'] ?? ''))));
                }
            }
        }
        return array_slice($refs, 0, 12);
    }

    private static function extraFromTask(array $task): array
    {
        $input = is_array($task['input'] ?? null) ? $task['input'] : [];
        $duration = strval($input['duration'] ?? '');
        if ($duration === '' && intval($task['billing_quantity'] ?? 0) > 0 && strval($task['task_type'] ?? '') !== 'image') {
            $duration = strval($task['billing_quantity']);
        }
        return [
            'task_type' => strval($task['task_type'] ?? ''),
            'model_name' => strval($task['model_name'] ?? ''),
            'model_code' => strval($task['model_code'] ?? ''),
            'refs' => self::refsFromTask($task),
            'ratio' => strval($input['ratio'] ?? ($input['aspect_ratio'] ?? ($input['size'] ?? ''))),
            'style' => strval($input['style'] ?? ''),
            'count' => intval($input['count'] ?? ($input['n'] ?? 1)),
            'quality' => strval($input['quality'] ?? ''),
            'duration' => $duration,
            'avatar' => is_array($input['avatar'] ?? null) ? $input['avatar'] : null,
            'voice' => $input['voice'] ?? null,
            'history_audio' => $input['historyAudio'] ?? ($input['history_audio'] ?? null),
            'input' => $input,
        ];
    }

    private static function formatComment(array $row): array
    {
        $item = [
            'id' => intval($row['id'] ?? 0),
            'aid' => intval($row['aid'] ?? 0),
            'inspire_id' => intval($row['inspire_id'] ?? 0),
            'member_id' => intval($row['member_id'] ?? 0),
            'parent_id' => intval($row['parent_id'] ?? 0),
            'content' => strval($row['content'] ?? ''),
            'create_time' => intval($row['create_time'] ?? 0),
            'create_time_text' => intval($row['create_time'] ?? 0) > 0 ? date('Y-m-d H:i', intval($row['create_time'])) : '',
        ];
        if (trim(strval($item['member_name'] ?? '')) === '' && intval($item['member_id']) > 0) {
            $item['member_name'] = '用户' . intval($item['member_id']);
        }
        return $item;
    }

    private static function formatCompact(int $num): string
    {
        if ($num >= 10000) {
            $text = rtrim(rtrim(number_format($num / 10000, 1, '.', ''), '0'), '.');
            return $text . 'w';
        }
        if ($num >= 1000) {
            $text = rtrim(rtrim(number_format($num / 1000, 1, '.', ''), '0'), '.');
            return $text . 'k';
        }
        return strval($num);
    }
}
