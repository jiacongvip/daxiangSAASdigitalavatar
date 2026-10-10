<?php

namespace app\plugin\zhuibaoagent\common;

class ZhuibaoOutputFileName
{
    public static function normalizeBaseName(string $value, int $timestamp = 0): string
    {
        $value = trim($value);
        $value = preg_replace(
            '/\.(?:mp4|mov|m4v|webm|avi|mkv|mp3|wav|m4a|aac|flac|ogg|png|jpe?g|webp|gif|bmp|pdf|zip|txt|json)$/iu',
            '',
            $value
        ) ?? $value;
        $value = preg_replace('~[\\\\/:*?"<>|\x00-\x1F\x7F]+~u', '-', $value) ?? '';
        $value = preg_replace('/\s+/u', ' ', $value) ?? '';
        $value = trim($value, " .\t\n\r\0\x0B-");
        if ($value === '') {
            return date('YmdHis', $timestamp > 0 ? $timestamp : time());
        }

        return mb_substr($value, 0, 60, 'UTF-8');
    }

    public static function build(string $value, string $extension, int $timestamp = 0, int $index = 1): string
    {
        $baseName = self::normalizeBaseName($value, $timestamp);
        $extension = preg_replace('/[^a-zA-Z0-9]/', '', strtolower(trim($extension))) ?: 'dat';
        $suffix = $index > 1 ? '_' . $index : '';

        return $baseName . $suffix . '.' . $extension;
    }
}
