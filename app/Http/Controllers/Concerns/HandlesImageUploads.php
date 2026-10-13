<?php

namespace App\Http\Controllers\Concerns;

use App\Services\ImageProcessor;
use App\Services\ThumbMaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

trait HandlesImageUploads
{
    /**
     * Save an uploaded image under public/images/uploads/{dir} and return the
     * public URL path (stored in the database). Using the public folder means
     * no `storage:link` is needed.
     *
     * The picture is first fitted to the place it is shown in (see
     * ImageProcessor: square for products, 3:1 for banners ...) so any size or
     * shape the admin uploads looks right. $profile overrides the folder's
     * default; pass 'none' to keep the file exactly as uploaded. If fitting is
     * not possible the original is kept.
     */
    protected function storeImage(UploadedFile $file, string $dir, ?string $profile = null): string
    {
        $dir = trim($dir, '/');
        $folder = public_path("images/uploads/{$dir}");
        $base = Str::random(20);
        $profile ??= ImageProcessor::profileFor($dir);

        if ($profile && $profile !== 'none' && ($name = ImageProcessor::process($file->getRealPath(), $profile, $folder, $base))) {
            ThumbMaker::makeFor("/images/uploads/{$dir}/{$name}");

            return "/images/uploads/{$dir}/{$name}";
        }

        $name = $base.'.'.($file->guessExtension() ?: 'jpg');

        $file->move($folder, $name);

        ThumbMaker::makeFor("/images/uploads/{$dir}/{$name}");

        return "/images/uploads/{$dir}/{$name}";
    }

    /**
     * Delete an image previously saved by storeImage(). Seeded artwork
     * (anything outside images/uploads) is never touched.
     */
    protected function deleteImage(?string $path): void
    {
        if ($path && str_starts_with($path, '/images/uploads/') && ! str_contains($path, '..')) {
            // Past orders keep showing the picture they were bought with.
            if (\App\Models\OrderItem::where('product_image', $path)->exists()) {
                return;
            }

            @unlink(public_path(ltrim($path, '/')));
            ThumbMaker::forget($path);
        }
    }
}
