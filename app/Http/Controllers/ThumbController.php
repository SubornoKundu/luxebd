<?php

namespace App\Http\Controllers;

use App\Services\ThumbMaker;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Smaller copies of pictures for phones and small cards.
 *
 * /thumbs/480/images/uploads/products/x.jpg  ->  a 480 px wide copy of /images/uploads/products/x.jpg.
 * The copy is saved under public/thumbs the first time it is asked for, so the next visit is served
 * straight by the web server without touching PHP. Only a few fixed widths and only pictures inside
 * public/images are allowed. If anything goes wrong the original picture is sent instead.
 */
class ThumbController extends Controller
{
    private const CACHE = ['Cache-Control' => 'public, max-age=2592000'];

    public function show(int $width, string $path): BinaryFileResponse
    {
        abort_unless(in_array($width, ThumbMaker::WIDTHS, true), 404);

        $relative = ltrim(str_replace('\\', '/', $path), '/');

        abort_if(
            $relative === '' || str_contains($relative, '..') || str_contains($relative, "\0")
                || ! str_starts_with($relative, 'images/')
                || ! preg_match('/\.(jpe?g|png|webp)$/i', $relative),
            404
        );

        $source = public_path($relative);
        $imagesRoot = realpath(public_path('images'));
        $real = is_file($source) ? realpath($source) : false;

        abort_unless($real && $imagesRoot && str_starts_with($real, $imagesRoot.DIRECTORY_SEPARATOR), 404);

        $target = ThumbMaker::target($width, $relative);

        if (is_file($target) && filemtime($target) >= filemtime($real)) {
            return response()->file($target, self::CACHE);
        }

        if (ThumbMaker::make($real, $target, $width)) {
            return response()->file($target, self::CACHE);
        }

        return response()->file($real, self::CACHE);
    }
}
