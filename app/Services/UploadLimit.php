<?php

namespace App\Services;

/** The largest upload the PHP server will actually accept, so forms never promise more than it allows. */
class UploadLimit
{
    public static function bytes(string $value): int
    {
        $value = trim($value);
        $number = (float) $value;
        return (int) match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3, 'm' => $number * 1024 ** 2, 'k' => $number * 1024, default => $number,
        };
    }

    /** Usable size of one request body, leaving headroom for the other form fields. 0 means unlimited. */
    public static function requestBytes(): int
    {
        $post = self::bytes((string) ini_get('post_max_size'));
        return $post > 0 ? max(1024 * 1024, $post - 1024 * 1024) : 0;
    }

    /** Per-file limit in kilobytes for Laravel's `max` rule: the smallest of PHP's file and request limits and the cap. */
    public static function fileKilobytes(int $capMegabytes = 100): int
    {
        $limits = array_filter([self::bytes((string) ini_get('upload_max_filesize')), self::requestBytes(), $capMegabytes * 1024 ** 2], fn ($bytes) => $bytes > 0);
        return max(1024, (int) floor(min($limits) / 1024));
    }

    public static function fileMegabytes(int $capMegabytes = 100): int
    {
        return max(1, (int) floor(self::fileKilobytes($capMegabytes) / 1024));
    }
}
