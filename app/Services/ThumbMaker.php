<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Makes the smaller copies of pictures that phones and small cards download (see ThumbController).
 * A copy of /images/x/y.jpg at 480 px wide lives at public/thumbs/480/images/x/y.jpg, so the web
 * server can hand it out directly without starting PHP.
 */
class ThumbMaker
{
    /** The only widths that exist. */
    public const WIDTHS = [360, 480, 720, 800, 1200];

    /** Copies made right after an admin upload (the banner's 800 / 1200 are made on first use). */
    public const UPLOAD_WIDTHS = [360, 480, 720];

    public static function target(int $width, string $relative): string
    {
        return public_path('thumbs/'.$width.'/'.ltrim($relative, '/'));
    }

    /** Makes one copy. False when it could not be made (the original picture is then used instead). */
    public static function make(string $source, string $target, int $width): bool
    {
        $oldLimit = ImageProcessor::raiseMemory();

        try {
            $info = @getimagesize($source);

            if (! $info || $info[0] <= $width || $info[1] < 1) {
                return false; // already small enough
            }

            if (! ImageProcessor::fitsInMemory((int) $info[0], (int) $info[1])) {
                Log::notice('Thumbnail skipped: not enough PHP memory for '.basename($source).' (raise memory_limit).');

                return false;
            }

            $image = match ($info[2]) {
                IMAGETYPE_JPEG => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($source) : false,
                IMAGETYPE_PNG => function_exists('imagecreatefrompng') ? @imagecreatefrompng($source) : false,
                IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($source) : false,
                default => false,
            };

            if (! $image) {
                Log::notice('Thumbnail skipped: PHP could not open '.basename($source).'.');

                return false;
            }

            $height = max(1, (int) round($info[1] * $width / $info[0]));
            $small = imagecreatetruecolor($width, $height);

            if ($small) {
                imagealphablending($small, false);
                imagesavealpha($small, true);
                imagefill($small, 0, 0, imagecolorallocatealpha($small, 0, 0, 0, 127));

                if (! imagecopyresampled($small, $image, 0, 0, 0, 0, $width, $height, $info[0], $info[1])) {
                    $small = false;
                }
            }

            unset($image);

            if (! $small) {
                return false;
            }

            $dir = dirname($target);

            if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
                Log::notice('Thumbnail skipped: the folder '.$dir.' cannot be created (check its permissions).');

                return false;
            }

            $temp = $target.'.'.bin2hex(random_bytes(4)).'.tmp';

            $ok = match ($info[2]) {
                IMAGETYPE_PNG => (function () use ($small, $temp) {
                    imagealphablending($small, false);
                    imagesavealpha($small, true);

                    return imagepng($small, $temp, 6);
                })(),
                IMAGETYPE_WEBP => imagewebp($small, $temp, 82),
                default => imageinterlace($small, true) !== false && imagejpeg($small, $temp, 82),
            };

            unset($small);

            if (! $ok || ! @rename($temp, $target)) {
                @unlink($temp);

                return is_file($target);
            }

            return true;
        } catch (\Throwable $e) {
            Log::notice('Thumbnail failed for '.basename($source).': '.$e->getMessage());

            return false;
        } finally {
            if ($oldLimit !== null) {
                @ini_set('memory_limit', $oldLimit);
            }
        }
    }

    /**
     * Makes the copies of one stored picture ("/images/uploads/products/x.jpg"). Never throws.
     *
     * @param  list<int>  $widths
     */
    public static function makeFor(string $path, array $widths = self::UPLOAD_WIDTHS): void
    {
        $relative = ltrim($path, '/');

        if (str_contains($relative, '..') || ! str_starts_with($relative, 'images/') || ! preg_match('/\.(jpe?g|png|webp)$/i', $relative)) {
            return;
        }

        $source = public_path($relative);

        if (! is_file($source)) {
            return;
        }

        foreach ($widths as $width) {
            if (in_array($width, self::WIDTHS, true)) {
                self::make($source, self::target($width, $relative), $width);
            }
        }
    }

    /** Removes the copies of a picture that was deleted or replaced. */
    public static function forget(string $path): void
    {
        $relative = ltrim($path, '/');

        if (str_contains($relative, '..') || ! str_starts_with($relative, 'images/uploads/')) {
            return;
        }

        foreach (self::WIDTHS as $width) {
            @unlink(self::target($width, $relative));
        }
    }
}
