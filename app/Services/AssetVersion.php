<?php
namespace App\Services;

/** Public asset URL tagged with the file's modified time so browsers fetch new CSS and JS after each deploy. */
class AssetVersion
{
    public static function url(string $path): string
    {
        $file = public_path($path);
        return asset($path).(is_file($file) ? '?v='.filemtime($file) : '');
    }
}
