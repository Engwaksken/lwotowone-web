<?php
namespace App\Services;

use BaconQrCode\{Writer, Renderer\ImageRenderer, Renderer\Image\SvgImageBackEnd, Renderer\RendererStyle\RendererStyle};

class Sharing
{
    public static function qr(string $url, string $filename, bool $download = false)
    {
        $svg = (new Writer(new ImageRenderer(new RendererStyle(280), new SvgImageBackEnd())))->writeString($url);
        return response($svg, 200, ['Content-Type' => 'image/svg+xml', 'Content-Disposition' => ($download ? 'attachment' : 'inline').'; filename="'.$filename.'"', 'Cache-Control' => 'private, no-store']);
    }

    public static function csvCell(mixed $value): string
    {
        $text = is_array($value) ? implode('; ', $value) : (string)$value;
        return preg_match('/^[\s]*[=+@-]/u', $text) ? "'".$text : $text;
    }
}
