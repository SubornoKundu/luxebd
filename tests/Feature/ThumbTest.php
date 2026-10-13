<?php

namespace Tests\Feature;

use App\Services\ThumbMaker;
use Tests\TestCase;

/** Smaller copies of pictures: only fixed widths, only pictures in public/images, original sent if anything fails. */
class ThumbTest extends TestCase
{
    public function test_unknown_widths_and_paths_outside_images_are_refused(): void
    {
        $this->get('/thumbs/123/images/vireluxe/banners/banner-fragrance.jpg')->assertNotFound();
        $this->get('/thumbs/480/index.php')->assertNotFound();
        $this->get('/thumbs/480/images/vireluxe/missing.jpg')->assertNotFound();
        $this->get('/thumbs/480/images/vireluxe/banners')->assertNotFound();
    }

    public function test_a_smaller_copy_is_made_when_missing_and_is_really_smaller(): void
    {
        $copy = ThumbMaker::target(800, 'images/vireluxe/banners/banner-fragrance.jpg');
        @unlink($copy);

        $this->get('/thumbs/800/images/vireluxe/banners/banner-fragrance.jpg')->assertOk();

        $this->assertFileExists($copy);
        $this->assertSame(800, getimagesize($copy)[0]);
        $this->assertLessThan(filesize(public_path('images/vireluxe/banners/banner-fragrance.jpg')), filesize($copy));
    }

    public function test_copies_are_made_for_an_uploaded_picture_and_removed_with_it(): void
    {
        $relative = 'images/uploads/thumb-test-'.uniqid().'.jpg';
        copy(public_path('images/vireluxe/banners/banner-fragrance.jpg'), public_path($relative));

        try {
            ThumbMaker::makeFor('/'.$relative);

            foreach (ThumbMaker::UPLOAD_WIDTHS as $width) {
                $this->assertFileExists(ThumbMaker::target($width, $relative));
            }

            ThumbMaker::forget('/'.$relative);

            foreach (ThumbMaker::UPLOAD_WIDTHS as $width) {
                $this->assertFileDoesNotExist(ThumbMaker::target($width, $relative));
            }
        } finally {
            @unlink(public_path($relative));
            ThumbMaker::forget('/'.$relative);
        }
    }
}
