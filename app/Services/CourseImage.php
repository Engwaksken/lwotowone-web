<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

/** Course cover images live in public/course-images so pages can show them directly. */
class CourseImage
{
    public const RULES = ['nullable', 'file', 'mimetypes:image/png,image/jpeg,image/webp', 'extensions:png,jpg,jpeg,webp', 'max:4096'];
    private const DIRECTORY = 'course-images';

    public static function store(UploadedFile $file): string
    {
        $directory = public_path(self::DIRECTORY);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException('The course image folder could not be created.');
        }
        $name = 'course-'.bin2hex(random_bytes(8)).'.'.strtolower($file->getClientOriginalExtension());
        $file->move($directory, $name);
        return '/'.self::DIRECTORY.'/'.$name;
    }

    public static function delete(?string $path): void
    {
        if ($path && str_starts_with($path, '/'.self::DIRECTORY.'/') && is_file($file = public_path(ltrim($path, '/')))) {
            @unlink($file);
        }
    }
}
