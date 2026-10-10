<?php

namespace App\Services;

use Illuminate\Http\Request;

class LearningContent
{
    public const FORMATS = ['text' => 'Text', 'file' => 'File / document', 'video' => 'Video', 'audio' => 'Audio'];
    public const EXTENSIONS = [
        'text' => 'txt',
        'file' => 'pdf,txt,jpg,jpeg,png,webp',
        'video' => 'mp4,webm,ogg',
        'audio' => 'mp3,wav,ogg,m4a,aac,flac',
    ];

    public static function format(?object $record, string $module): string
    {
        if ($record?->content_format) return $record->content_format;
        if (!empty($record?->video_url)) return 'video';
        if ($record && !empty($record->file_path)) {
            $type = self::mediaType($record);
            if (in_array($type, ['video', 'audio'], true)) return $type;
        }
        return $module === 'resources' ? 'file' : 'text';
    }

    public static function source(?object $record, string $module): string
    {
        return $record?->content_source ?? (!empty($record?->video_url) ? 'url' : ($module === 'resources' ? 'upload' : 'write'));
    }

    /** Explicit format submissions replace the old source, never retain hidden content. */
    public static function validate(Request $request, string $module, ?object $record): array
    {
        $choice = $request->validate(['content_format' => 'required|in:text,file,video,audio', 'content_source' => 'required|in:write,upload,url']);
        $format = $choice['content_format']; $source = $choice['content_source'];
        if ($source === 'write' && $format !== 'text') {
            throw \Illuminate\Validation\ValidationException::withMessages(['content_source' => 'Only text content can be written directly. Choose an upload or URL.']);
        }
        $retainFile = $record?->file_path && self::format($record, $module) === $format && self::source($record, $module) === 'upload';
        $rules = match ($source) {
            'write' => ['content_body' => 'required|string|max:50000'],
            'url' => ['content_url' => 'required|url:http,https|max:1000'],
            'upload' => ['file' => [$retainFile ? 'nullable' : 'required', 'file', 'mimes:'.self::EXTENSIONS[$format], 'extensions:'.self::EXTENSIONS[$format], 'max:102400']],
        };
        $data = $request->validate($rules);
        $out = $choice + ['content_body' => $data['content_body'] ?? null, 'content_url' => $data['content_url'] ?? null, 'file_path' => $source === 'upload' && $retainFile ? $record->file_path : null];
        if (isset($data['file'])) $out['file'] = $data['file'];
        if ($module === 'lessons') {
            $out['body'] = $source === 'write' ? $out['content_body'] : '';
            $out['video_url'] = $format === 'video' && $source === 'url' ? $out['content_url'] : null;
        }
        return $out;
    }

    public static function mediaType(object $record): string
    {
        if (($record->content_format ?? null) === 'audio') return 'audio';
        if (($record->content_format ?? null) === 'video') return 'video';
        $path = ($record->content_source ?? null) === 'url' ? (string)parse_url($record->content_url, PHP_URL_PATH) : (string)($record->file_path ?? '');
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'mp4', 'webm', 'ogg' => 'video', 'mp3', 'wav', 'm4a', 'aac', 'flac' => 'audio',
            'pdf' => 'pdf', 'png', 'jpg', 'jpeg', 'webp' => 'image', 'txt' => 'text', default => 'document',
        };
    }
}
