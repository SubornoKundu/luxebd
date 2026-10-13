<?php

namespace App\Console\Commands;

use App\Services\ThumbMaker;
use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * php artisan shop:thumbs
 *
 * Makes the small copies of every picture under public/images (products, banners, logo ...) so the
 * live site serves them straight from public/thumbs. Run it on your own computer, then upload
 * public/thumbs together with the site: nothing has to be made on the hosting.
 */
class ShopThumbs extends Command
{
    protected $signature = 'shop:thumbs {--force : Make the copies again even if they exist}';

    protected $description = 'Make the small phone-sized copies of all pictures in public/images';

    public function handle(): int
    {
        $root = public_path('images');
        $made = 0;
        $kept = 0;

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if (! preg_match('/\.(jpe?g|png|webp)$/i', $file->getFilename())) {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen(public_path()) + 1));

            foreach (ThumbMaker::WIDTHS as $width) {
                $target = ThumbMaker::target($width, $relative);

                if (! $this->option('force') && is_file($target) && filemtime($target) >= $file->getMTime()) {
                    $kept++;

                    continue;
                }

                if (ThumbMaker::make($file->getPathname(), $target, $width)) {
                    $made++;
                }
            }
        }

        $this->info("Made {$made} small copies ({$kept} were already up to date). Upload the public/thumbs folder with the site.");

        return self::SUCCESS;
    }
}
