<?php

namespace App\Services;

/**
 * Makes every uploaded picture fit its place, whatever size or shape it had.
 *
 *  product      square, nothing cut off: wide/tall photos get matching-colour borders
 *               (photos that are nearly square are simply trimmed a little)
 *  banner       wide 3:1 (1800x600), centre-cropped. Computers show it whole; phones show the middle of it
 *  category     4:3, centre-cropped
 *  logo         keeps its own shape, transparent PNG
 *  logo-square  square with transparent borders (kept for compatibility)
 *  logo-fit     brand logos: empty transparent edges are cut away and the logo keeps its own
 *               shape, so it fills its box on the home page
 *
 * Also: phone photos are turned upright, huge pictures are shrunk (faster pages),
 * and the camera's hidden data (GPS location etc.) is dropped.
 *
 * If anything goes wrong (no GD, damaged file, enormous picture) process() returns
 * null and the caller keeps the original file, so an upload never fails because of this.
 */
class ImageProcessor
{
    private const MAX_PIXELS = 36_000_000;

    /** Which profile a folder gets by default. */
    private const FOLDER_PROFILES = [
        'products' => 'product',
        'variants' => 'product',
        'slider' => 'banner',
        'category' => 'category',
        'brand' => 'logo-fit',
    ];

    public static function profileFor(string $folder): ?string
    {
        return self::FOLDER_PROFILES[$folder] ?? null;
    }

    public static function available(): bool
    {
        return function_exists('imagecreatetruecolor') && function_exists('imagecopyresampled');
    }

    /**
     * @return string|null the new file name inside $folder, or null to keep the original
     */
    public static function process(string $source, string $profile, string $folder, string $baseName): ?string
    {
        if (! self::available() || ! is_file($source)) {
            return null;
        }

        $oldLimit = self::raiseMemory();

        try {
            $info = @getimagesize($source);

            if (! $info || $info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > self::MAX_PIXELS || ! self::fitsInMemory((int) $info[0], (int) $info[1])) {
                return null;
            }

            $image = self::load($source, (int) $info[2]);

            if (! $image) {
                return null;
            }

            $image = self::upright($image, $source, (int) $info[2]);
            $out = match ($profile) {
                'product' => self::product($image),
                'banner' => self::cover($image, 3, 1, 1800),
                'category' => self::cover($image, 4, 3, 900),
                'logo' => self::contain($image, 800, false),
                'logo-square' => self::contain($image, 600, true),
                'logo-fit' => self::contain(self::trimTransparent($image), 600, false),
                default => null,
            };

            unset($image);

            if (! $out) {
                return null;
            }

            $png = in_array($profile, ['logo', 'logo-square', 'logo-fit'], true);
            $name = $baseName.($png ? '.png' : '.jpg');

            if (! is_dir($folder) && ! @mkdir($folder, 0775, true) && ! is_dir($folder)) {
                return null;
            }

            $ok = $png ? imagepng($out, $folder.'/'.$name, 6) : (imageinterlace($out, true) !== false && imagejpeg($out, $folder.'/'.$name, 85));

            unset($out);

            return $ok ? $name : null;
        } catch (\Throwable) {
            return null;
        } finally {
            if ($oldLimit !== null) {
                @ini_set('memory_limit', $oldLimit);
            }
        }
    }

    /** Gives picture work up to 384M while it runs; returns the old limit to restore, or null if unchanged. */
    public static function raiseMemory(): ?string
    {
        $old = trim((string) ini_get('memory_limit'));

        if ($old === '' || $old === '-1') {
            return null;
        }

        $bytes = (int) $old * match (strtolower(substr($old, -1))) { 'g' => 1024 ** 3, 'm' => 1024 ** 2, 'k' => 1024, default => 1 };

        if ($bytes >= 384 * 1024 ** 2) {
            return null;
        }

        return @ini_set('memory_limit', '384M') !== false ? $old : null;
    }

    /**
     * Opening a picture takes about 4 bytes per pixel (twice while it is being resized).
     * Running out of memory cannot be caught, so a picture too big for this server's
     * memory_limit is skipped and the original file is kept instead.
     */
    public static function fitsInMemory(int $width, int $height): bool
    {
        $limit = trim((string) ini_get('memory_limit'));

        if ($limit === '' || $limit === '-1') {
            return true;
        }

        $bytes = (int) $limit;
        $unit = strtolower(substr($limit, -1));
        $bytes *= match ($unit) { 'g' => 1024 ** 3, 'm' => 1024 ** 2, 'k' => 1024, default => 1 };

        if ($bytes <= 0) {
            return true;
        }

        $needed = (int) ($width * $height * 4 * 1.8) + 8 * 1024 * 1024;

        return $bytes - memory_get_usage(true) > $needed;
    }

    private static function load(string $path, int $type): \GdImage|false
    {
        return match ($type) {
            IMAGETYPE_JPEG => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($path) : false,
            IMAGETYPE_PNG => function_exists('imagecreatefrompng') ? @imagecreatefrompng($path) : false,
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => false,
        };
    }

    /** Apply the camera's rotation flag so phone photos are not sideways. */
    private static function upright(\GdImage $image, string $path, int $type): \GdImage
    {
        if ($type !== IMAGETYPE_JPEG || ! function_exists('exif_read_data')) {
            return $image;
        }

        $orientation = (int) (@exif_read_data($path)['Orientation'] ?? 1);

        switch ($orientation) {
            case 2: imageflip($image, IMG_FLIP_HORIZONTAL); break;
            case 3: $image = imagerotate($image, 180, 0) ?: $image; break;
            case 4: imageflip($image, IMG_FLIP_VERTICAL); break;
            case 5: imageflip($image, IMG_FLIP_HORIZONTAL); $image = imagerotate($image, 90, 0) ?: $image; break;
            case 6: $image = imagerotate($image, -90, 0) ?: $image; break;
            case 7: imageflip($image, IMG_FLIP_HORIZONTAL); $image = imagerotate($image, -90, 0) ?: $image; break;
            case 8: $image = imagerotate($image, 90, 0) ?: $image; break;
        }

        return $image;
    }

    private static function canvas(int $w, int $h, bool $transparent): \GdImage
    {
        $c = imagecreatetruecolor($w, $h);

        if ($transparent) {
            imagealphablending($c, false);
            imagesavealpha($c, true);
            imagefill($c, 0, 0, imagecolorallocatealpha($c, 255, 255, 255, 127));
            imagealphablending($c, true);
        }

        return $c;
    }

    /** Square picture. Nearly-square: trim to square. Otherwise: whole picture + borders. */
    private static function product(\GdImage $src): \GdImage
    {
        $w = imagesx($src);
        $h = imagesy($src);
        $ratio = $w / $h;

        if ($ratio >= 0.88 && $ratio <= 1.14) {
            return self::cover($src, 1, 1, 1200);
        }

        $side = min(1200, max($w, $h));
        $out = imagecreatetruecolor($side, $side);
        [$r, $g, $b] = self::borderColor($src);
        imagefill($out, 0, 0, imagecolorallocate($out, $r, $g, $b));

        $scale = $side / max($w, $h);
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));
        imagecopyresampled($out, $src, intdiv($side - $nw, 2), intdiv($side - $nh, 2), 0, 0, $nw, $nh, $w, $h);

        return $out;
    }

    /** Fill an exact w:h shape by cropping the middle; never bigger than $maxWidth wide. */
    private static function cover(\GdImage $src, int $rw, int $rh, int $maxWidth): \GdImage
    {
        $w = imagesx($src);
        $h = imagesy($src);

        // biggest centred area of the right shape
        $cw = $w;
        $ch = (int) round($w * $rh / $rw);

        if ($ch > $h) {
            $ch = $h;
            $cw = (int) round($h * $rw / $rh);
        }

        $ow = min($maxWidth, $cw);
        $oh = max(1, (int) round($ow * $rh / $rw));
        $out = imagecreatetruecolor($ow, $oh);
        // transparent PNGs are flattened onto white
        imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
        imagecopyresampled($out, $src, 0, 0, intdiv($w - $cw, 2), intdiv($h - $ch, 2), $ow, $oh, $cw, $ch);

        return $out;
    }

    /** Whole picture inside max x max, optionally on a square transparent canvas. */
    /**
     * Cuts away see-through or white edges (a logo saved with lots of empty space
     * around it) so the logo itself can fill its box.
     */
    private static function trimTransparent(\GdImage $src): \GdImage
    {
        if (! imageistruecolor($src)) {
            imagepalettetotruecolor($src);
        }

        // Look at a reduced copy so a huge logo is not scanned pixel by pixel.
        $w = imagesx($src);
        $h = imagesy($src);
        $scale = min(1, 1200 / max($w, $h));
        $small = $scale < 1 ? self::contain($src, 1200, false) : $src;
        $sw = imagesx($small);
        $sh = imagesy($small);
        // "Empty" means see-through or (nearly) white, so a logo saved on a white
        // background with a wide blank margin is trimmed too.
        $visible = static function (int $x, int $y) use ($small): bool {
            $c = imagecolorat($small, $x, $y);

            if ((($c >> 24) & 0x7F) >= 100) {
                return false;
            }

            return ! ((($c >> 16) & 0xFF) >= 232 && (($c >> 8) & 0xFF) >= 232 && ($c & 0xFF) >= 232);
        };

        $top = null;
        for ($y = 0; $y < $sh && $top === null; $y++) {
            for ($x = 0; $x < $sw; $x++) {
                if ($visible($x, $y)) {
                    $top = $y;
                    break;
                }
            }
        }

        if ($top === null) {
            return $src; // completely empty: leave it alone
        }

        $bottom = $top;
        for ($y = $sh - 1; $y > $top; $y--) {
            for ($x = 0; $x < $sw; $x++) {
                if ($visible($x, $y)) {
                    $bottom = $y;
                    break 2;
                }
            }
        }

        $left = $sw - 1;
        $right = 0;
        for ($y = $top; $y <= $bottom; $y++) {
            for ($x = 0; $x < $left; $x++) {
                if ($visible($x, $y)) {
                    $left = $x;
                    break;
                }
            }
            for ($x = $sw - 1; $x > $right; $x--) {
                if ($visible($x, $y)) {
                    $right = $x;
                    break;
                }
            }
        }

        if ($small !== $src) {
            unset($small);
        }

        if ($left > $right) {
            return $src;
        }

        // Back to the real picture's pixels, with a hair of room so edges are not clipped.
        $f = $w / $sw;
        $x0 = max(0, (int) floor($left * $f) - 1);
        $y0 = max(0, (int) floor($top * $f) - 1);
        $x1 = min($w, (int) ceil(($right + 1) * $f) + 1);
        $y1 = min($h, (int) ceil(($bottom + 1) * $f) + 1);
        $cw = $x1 - $x0;
        $ch = $y1 - $y0;

        if ($cw >= $w && $ch >= $h) {
            return $src;
        }

        $out = self::canvas($cw, $ch, true);
        imagealphablending($out, false);
        imagecopy($out, $src, 0, 0, $x0, $y0, $cw, $ch);
        imagesavealpha($out, true);

        return $out;
    }

    private static function contain(\GdImage $src, int $max, bool $square): \GdImage
    {
        $w = imagesx($src);
        $h = imagesy($src);
        $scale = min(1, $max / max($w, $h));
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));
        $cw = $square ? max($nw, $nh) : $nw;
        $ch = $square ? max($nw, $nh) : $nh;

        $out = self::canvas($cw, $ch, true);
        imagealphablending($out, false);
        imagecopyresampled($out, $src, intdiv($cw - $nw, 2), intdiv($ch - $nh, 2), 0, 0, $nw, $nh, $w, $h);
        imagesavealpha($out, true);

        return $out;
    }

    /** Typical colour around the edge of the picture (white when it is see-through). Median, so a product touching the edge does not tint it. */
    private static function borderColor(\GdImage $img): array
    {
        $w = imagesx($img);
        $h = imagesy($img);
        $points = [[0, 0], [$w - 1, 0], [0, $h - 1], [$w - 1, $h - 1], [intdiv($w, 2), 0], [intdiv($w, 2), $h - 1], [0, intdiv($h, 2)], [$w - 1, intdiv($h, 2)]];
        $channels = [[], [], []];

        foreach ($points as [$x, $y]) {
            $c = imagecolorat($img, $x, $y);

            if ((($c >> 24) & 0x7F) > 20) {
                return [255, 255, 255];
            }

            $channels[0][] = ($c >> 16) & 0xFF;
            $channels[1][] = ($c >> 8) & 0xFF;
            $channels[2][] = $c & 0xFF;
        }

        $median = function (array $v): int {
            sort($v);

            return (int) round(($v[3] + $v[4]) / 2);
        };
        $rgb = [$median($channels[0]), $median($channels[1]), $median($channels[2])];

        // practically white -> pure white (avoids a faint grey frame)
        return min($rgb) > 250 ? [255, 255, 255] : $rgb;
    }
}
