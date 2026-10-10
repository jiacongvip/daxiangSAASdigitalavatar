<?php
namespace app\common;

class AsyncTask
{
    public static function metaKey(string $biz, string $taskId): string
    {
        return "$biz:task:$taskId:meta";
    }

    public static function progressKey(string $biz, string $taskId): string
    {
        return "$biz:task:$taskId:progress";
    }

    public static function runningKey(string $biz, string $scope): string
    {
        return "$biz:running:$scope";
    }

    public static function enqueue(string $queue, array $payload): bool
    {
        return RedisClient::rPush($queue, json_encode($payload, JSON_UNESCAPED_UNICODE)) > 0;
    }

    public static function setProgress(string $biz, string $taskId, array $data): void
    {
        $key = self::progressKey($biz, $taskId);
        $old = RedisClient::getJson($key) ?: [];
        RedisClient::setJson($key, array_merge($old, $data));
    }
}



