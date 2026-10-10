<?php

namespace app\plugin\digitalavatar\common;

use think\facade\Db;

class DigitalAvatarAdService
{
    public static function list(int $aid, bool $activeOnly = false): array
    {
        $query = Db::name('digitalavatar_ad')->where('aid', $aid);
        if ($activeOnly) {
            $now = date('Y-m-d H:i:s');
            $query->where('status', 1)
                ->where(function ($query) use ($now) {
                    $query->where('start_time', '')->whereOr('start_time', '<=', $now);
                })
                ->where(function ($query) use ($now) {
                    $query->where('end_time', '')->whereOr('end_time', '>=', $now);
                });
        }

        $rows = $query->order('sort asc,id desc')->select()->toArray();
        return array_map([self::class, 'format'], $rows);
    }

    public static function save(int $aid, array $info): array
    {
        $id = intval($info['id'] ?? 0);
        $imageUrl = mb_substr(trim(strval($info['image_url'] ?? '')), 0, 500);
        if ($imageUrl === '') {
            throw new \RuntimeException('请上传海报图片');
        }
        $title = trim(strval($info['title'] ?? ''));
        if ($title === '') {
            $title = '海报';
        }

        $startTime = self::normalizeDate($info['start_time'] ?? '');
        $endTime = self::normalizeDate($info['end_time'] ?? '');
        if ($startTime !== '' && $endTime !== '' && $startTime > $endTime) {
            throw new \RuntimeException('结束时间不能早于开始时间');
        }

        $data = [
            'aid' => $aid,
            'placement' => in_array(strval($info['placement'] ?? 'carousel'), ['carousel', 'side'], true)
                ? strval($info['placement'] ?? 'carousel')
                : 'carousel',
            'title' => mb_substr($title, 0, 120),
            'tags' => mb_substr(trim(strval($info['tags'] ?? '')), 0, 255),
            'description' => mb_substr(trim(strval($info['description'] ?? '')), 0, 500),
            'image_url' => $imageUrl,
            'background' => mb_substr(trim(strval($info['background'] ?? '')), 0, 500),
            'link_url' => mb_substr(trim(strval($info['link_url'] ?? '')), 0, 500),
            'sort' => intval($info['sort'] ?? 0),
            'status' => intval($info['status'] ?? 1) === 1 ? 1 : 0,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'update_time' => time(),
        ];

        if ($id > 0) {
            $exists = Db::name('digitalavatar_ad')->where('aid', $aid)->where('id', $id)->find();
            if (!$exists) {
                throw new \RuntimeException('广告不存在');
            }
            Db::name('digitalavatar_ad')->where('aid', $aid)->where('id', $id)->update($data);
        } else {
            $data['create_time'] = time();
            $id = intval(Db::name('digitalavatar_ad')->insertGetId($data));
        }

        return self::format(Db::name('digitalavatar_ad')->where('aid', $aid)->where('id', $id)->find() ?: []);
    }

    public static function delete(int $aid, int $id): void
    {
        if ($id <= 0) {
            throw new \RuntimeException('请传入广告ID');
        }
        $affected = Db::name('digitalavatar_ad')->where('aid', $aid)->where('id', $id)->delete();
        if ($affected <= 0) {
            throw new \RuntimeException('广告不存在');
        }
    }

    private static function format(array $row): array
    {
        $tagsText = trim(strval($row['tags'] ?? ''));
        $tags = array_values(array_filter(array_map('trim', explode(',', $tagsText)), function ($tag) {
            return $tag !== '';
        }));

        return [
            'id' => intval($row['id'] ?? 0),
            'placement' => strval($row['placement'] ?? 'carousel') ?: 'carousel',
            'title' => strval($row['title'] ?? ''),
            'tags' => $tags,
            'tags_text' => $tagsText,
            'description' => strval($row['description'] ?? ''),
            'image_url' => strval($row['image_url'] ?? ''),
            'background' => strval($row['background'] ?? ''),
            'link_url' => strval($row['link_url'] ?? ''),
            'sort' => intval($row['sort'] ?? 0),
            'status' => intval($row['status'] ?? 0),
            'start_time' => strval($row['start_time'] ?? ''),
            'end_time' => strval($row['end_time'] ?? ''),
            'create_time' => intval($row['create_time'] ?? 0),
            'update_time' => intval($row['update_time'] ?? 0),
        ];
    }

    private static function normalizeDate($value): string
    {
        $value = trim(strval($value));
        if ($value === '') {
            return '';
        }
        $value = str_replace('T', ' ', $value);
        $time = strtotime($value);
        if ($time === false) {
            throw new \RuntimeException('生效时间格式不正确，请使用 YYYY-MM-DD HH:MM:SS');
        }
        return date('Y-m-d H:i:s', $time);
    }
}
