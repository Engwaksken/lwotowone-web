<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/** Compress uploaded video to a broadly playable, lower-bitrate MP4 when FFmpeg is installed. */
class VideoUpload
{
    private const VIDEO_EXTENSIONS = ['mp4', 'webm', 'ogg', 'ogv', 'mov', 'm4v'];

    public static function compressedTemporaryPath(UploadedFile $file): ?string
    {
        if (!in_array(strtolower($file->getClientOriginalExtension()), self::VIDEO_EXTENSIONS, true)) {
            return null;
        }

        $binary = (string) config('media.ffmpeg', 'ffmpeg');
        $output = tempnam(sys_get_temp_dir(), 'lw-video-');
        if ($output === false) return null;
        @unlink($output);
        $output .= '.mp4';

        try {
            $process = new Process([
                $binary, '-nostdin', '-y', '-i', $file->getRealPath(),
                '-vcodec', 'libx264', '-preset', 'fast', '-crf', '28',
                '-acodec', 'aac', '-b:a', '96k', '-movflags', '+faststart',
                $output,
            ]);
            $process->setTimeout((int) config('media.compression_timeout', 1800));
            $process->run();
            if ($process->isSuccessful() && is_file($output) && filesize($output) > 0) return $output;
            Log::warning('Video compression failed; retaining the original upload.', [
                'error' => mb_substr($process->getErrorOutput(), 0, 500),
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Video compression is unavailable; retaining the original upload.', ['error' => $exception->getMessage()]);
        }

        @unlink($output);
        return null;
    }

    public static function store(UploadedFile $file, string $directory, string $disk = 'local'): string
    {
        $compressed = self::compressedTemporaryPath($file);
        if ($compressed === null) return $file->store($directory, $disk);

        try {
            $upload = new UploadedFile($compressed, pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME).'.mp4', 'video/mp4', null, true);
            return $upload->store($directory, $disk);
        } finally {
            @unlink($compressed);
        }
    }
}
