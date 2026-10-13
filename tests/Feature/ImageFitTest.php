<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Slider;
use App\Services\ImageProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/** Whatever size or shape the admin uploads, it is saved in the shape its place needs. */
class ImageFitTest extends TestCase
{
    use Concerns, RefreshDatabase;

    /** @var list<string> */
    protected array $files = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        if (! ImageProcessor::available()) {
            $this->markTestSkipped('PHP GD is not installed here.');
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $path) {
            @unlink(public_path(ltrim($path, '/')));
        }
        parent::tearDown();
    }

    /** @return array{0:int,1:int} width, height of a saved upload */
    protected function dims(?string $path): array
    {
        $this->assertNotNull($path);
        $this->files[] = $path;
        $this->assertFileExists(public_path(ltrim($path, '/')));
        $info = getimagesize(public_path(ltrim($path, '/')));

        return [$info[0], $info[1]];
    }

    protected function product(array $extra = []): array
    {
        $cat = Category::firstOrCreate(['slug' => 'phones'], ['name' => 'Phones']);
        $brand = Brand::firstOrCreate(['slug' => 'zed'], ['name' => 'Zed']);

        return array_merge(['name' => 'Fit Phone', 'category_id' => $cat->id, 'brand_id' => $brand->id, 'price' => 5000, 'stock' => 5, 'is_active' => true], $extra);
    }

    public function test_product_pictures_become_square_without_cutting_anything_off(): void
    {
        $this->actingAs($this->makeAdmin());

        // very wide main picture, very tall extra picture, and a huge one
        $this->post('/admin/products', $this->product([
            'image' => UploadedFile::fake()->image('wide.jpg', 2400, 600),
            'gallery' => [UploadedFile::fake()->image('tall.png', 400, 1600), UploadedFile::fake()->image('huge.jpg', 2600, 1900)],
        ]))->assertSessionHasNoErrors();

        $product = Product::with('images')->firstOrFail();
        $this->assertSame([1200, 1200], $this->dims($product->image));
        $this->assertStringEndsWith('.jpg', $product->image);

        $this->assertSame([1200, 1200], $this->dims($product->images[0]->path));
        $this->assertSame([1200, 1200], $this->dims($product->images[1]->path));
    }

    public function test_small_pictures_are_not_blown_up(): void
    {
        $this->actingAs($this->makeAdmin());
        $this->post('/admin/products', $this->product(['image' => UploadedFile::fake()->image('s.jpg', 300, 200)]))->assertSessionHasNoErrors();

        $this->assertSame([300, 300], $this->dims(Product::firstOrFail()->image));
    }

    public function test_banner_is_cut_to_the_banner_shape_and_category_to_its_shape(): void
    {
        $this->actingAs($this->makeAdmin());

        $this->post('/admin/sliders', ['title' => 'Portrait', 'image' => UploadedFile::fake()->image('p.jpg', 1000, 1800), 'is_active' => true])->assertSessionHasNoErrors();
        [$w, $h] = $this->dims(Slider::firstOrFail()->image);
        $this->assertSame(1000, $w);
        $this->assertSame(333, $h);

        $this->post('/admin/sliders', ['title' => 'Huge', 'image' => UploadedFile::fake()->image('h.jpg', 4000, 1000), 'is_active' => true])->assertSessionHasNoErrors();
        $this->assertSame([1800, 600], $this->dims(Slider::where('title', 'Huge')->firstOrFail()->image));

        $this->post('/admin/categories', ['name' => 'Cats', 'image' => UploadedFile::fake()->image('c.jpg', 2000, 2000)])->assertSessionHasNoErrors();
        $this->assertSame([900, 675], $this->dims(Category::where('name', 'Cats')->firstOrFail()->image));
    }

    public function test_brand_logo_keeps_its_own_shape_and_transparency(): void
    {
        $this->actingAs($this->makeAdmin());
        $this->post('/admin/brands', ['name' => 'Wide Brand', 'logo' => UploadedFile::fake()->image('l.png', 800, 200)])->assertSessionHasNoErrors();

        $logo = Brand::where('name', 'Wide Brand')->firstOrFail()->logo;
        $this->assertSame([600, 150], $this->dims($logo));
        $this->assertStringEndsWith('.png', $logo);
    }

    public function test_brand_logo_empty_edges_are_trimmed_so_it_fills_its_box(): void
    {
        $this->actingAs($this->makeAdmin());

        // 600x600 file with a 240x60 logo in the middle and see-through space all around
        $img = imagecreatetruecolor(600, 600);
        imagealphablending($img, false);
        imagesavealpha($img, true);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 255, 255, 255, 127));
        imagefilledrectangle($img, 180, 270, 419, 329, imagecolorallocatealpha($img, 220, 30, 30, 0));
        ob_start();
        imagepng($img);
        $png = ob_get_clean();

        $this->post('/admin/brands', ['name' => 'Padded', 'logo' => UploadedFile::fake()->createWithContent('p.png', $png)])->assertSessionHasNoErrors();

        [$w, $h] = $this->dims(Brand::where('name', 'Padded')->firstOrFail()->logo);
        $this->assertEqualsWithDelta(240, $w, 4);
        $this->assertEqualsWithDelta(60, $h, 4);
    }

    public function test_brand_logo_on_a_white_background_is_trimmed_too(): void
    {
        $this->actingAs($this->makeAdmin());

        $img = imagecreatetruecolor(500, 500);
        imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
        imagefilledrectangle($img, 100, 200, 399, 299, imagecolorallocate($img, 20, 40, 160));
        ob_start();
        imagepng($img);
        $png = ob_get_clean();

        $this->post('/admin/brands', ['name' => 'White Bg', 'logo' => UploadedFile::fake()->createWithContent('w.png', $png)])->assertSessionHasNoErrors();

        [$w, $h] = $this->dims(Brand::where('name', 'White Bg')->firstOrFail()->logo);
        $this->assertEqualsWithDelta(300, $w, 4);
        $this->assertEqualsWithDelta(100, $h, 4);
    }

    public function test_replacing_a_picture_removes_the_old_processed_file(): void
    {
        $this->actingAs($this->makeAdmin());
        $this->post('/admin/products', $this->product(['image' => UploadedFile::fake()->image('a.jpg', 900, 500)]));
        $product = Product::firstOrFail();
        $old = $product->image;
        $this->dims($old);

        $this->put("/admin/products/{$product->id}", $this->product(['image' => UploadedFile::fake()->image('b.jpg', 500, 900)]))->assertSessionHasNoErrors();

        $this->assertNotSame($old, $product->fresh()->image);
        $this->assertFileDoesNotExist(public_path(ltrim($old, '/')));
        $this->dims($product->fresh()->image);
    }

    public function test_pictures_that_cannot_be_processed_are_kept_as_they_are(): void
    {
        $dir = sys_get_temp_dir().'/fit-'.uniqid();
        $bad = sys_get_temp_dir().'/bad-'.uniqid().'.jpg';
        file_put_contents($bad, 'this is not a picture');

        $this->assertNull(ImageProcessor::process($bad, 'product', $dir, 'x'));
        $this->assertNull(ImageProcessor::process(__DIR__.'/../../public/images/vireluxe/products/rayhaan-lion.jpg', 'unknown-profile', $dir, 'x'));
        $this->assertNull(ImageProcessor::process('/nonexistent/file.jpg', 'product', $dir, 'x'));
        @unlink($bad);
    }

    public function test_pictures_too_big_for_the_servers_memory_are_left_alone(): void
    {
        $old = ini_get('memory_limit');
        ini_set('memory_limit', '1024M');

        try {
            $this->assertTrue(ImageProcessor::fitsInMemory(1600, 1200));
            $this->assertFalse(ImageProcessor::fitsInMemory(20000, 20000));
            ini_set('memory_limit', '-1');
            $this->assertTrue(ImageProcessor::fitsInMemory(20000, 20000));
        } finally {
            ini_set('memory_limit', $old);
        }
    }

    public function test_a_phone_photo_that_is_rotated_by_the_camera_is_turned_upright(): void
    {
        if (! function_exists('exif_read_data')) {
            $this->markTestSkipped('exif extension missing');
        }

        // A 400x200 picture whose red block is top-left and which carries "rotate 90 clockwise".
        $im = imagecreatetruecolor(400, 200);
        imagefill($im, 0, 0, imagecolorallocate($im, 255, 255, 255));
        imagefilledrectangle($im, 0, 0, 199, 99, imagecolorallocate($im, 255, 0, 0));
        $plain = sys_get_temp_dir().'/rot-'.uniqid().'.jpg';
        imagejpeg($im, $plain, 95);

        // add a minimal EXIF block with Orientation = 6
        $jpeg = file_get_contents($plain);
        $exif = "Exif\0\0".'MM'."\x00\x2A\x00\x00\x00\x08"."\x00\x01"."\x01\x12\x00\x03\x00\x00\x00\x01\x00\x06\x00\x00"."\x00\x00\x00\x00";
        $app1 = "\xFF\xE1".pack('n', strlen($exif) + 2).$exif;
        file_put_contents($plain, substr($jpeg, 0, 2).$app1.substr($jpeg, 2));

        $this->assertSame(6, (int) (@exif_read_data($plain)['Orientation'] ?? 0), 'test picture carries the flag');

        $dir = sys_get_temp_dir().'/fit-'.uniqid();
        $name = ImageProcessor::process($plain, 'product', $dir, 'rot');
        $out = imagecreatefromjpeg("{$dir}/{$name}");
        $red = fn ($x, $y) => (imagecolorat($out, $x, $y) >> 16 & 0xFF) > 200 && (imagecolorat($out, $x, $y) >> 8 & 0xFF) < 80;

        // rotated clockwise: the red block is now in the top-right
        $this->assertTrue($red((int) (imagesx($out) * 0.62), (int) (imagesy($out) * 0.15)));
        $this->assertFalse($red((int) (imagesx($out) * 0.1), (int) (imagesy($out) * 0.15)));

        @unlink($plain);
        @unlink("{$dir}/{$name}");
        @rmdir($dir);
    }
}
