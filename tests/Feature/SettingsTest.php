<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use Concerns, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget(SiteSettings::CACHE_KEY);
        // On the in-memory test DB the table only exists after the app has booted.
        SiteSettings::applyToConfig();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(public_path('images/uploads/site'));
        parent::tearDown();
    }

    private function admin(): User
    {
        return $this->makeAdmin();
    }

    private function payload(array $over = []): array
    {
        return array_merge([
            'site_name' => 'Rupa Bazar',
            'tagline' => 'Best deals in Dhaka',
            'meta_description' => 'Rupa Bazar online shop',
            'primary_color' => '#2563eb',
            'announcement_text' => 'Eid sale 20% off',
            'announcement_link' => 'https://example.com/sale',
            'footer_text' => 'Rupa Bazar 2026',
            'phone' => '01700000000',
            'email' => 'hello@rupa.test',
            'address' => 'Dhanmondi, Dhaka',
            'hours' => '9am-9pm',
            'whatsapp' => '8801700000000',
            'facebook' => 'https://facebook.com/rupa',
            'instagram' => '',
            'youtube' => '',
            'x' => '',
            'shipping_fee' => 99,
            'free_shipping_over' => 3000,
            'low_stock_threshold' => 7,
            'bkash_number' => '01811111111',
            'nagad_number' => '01922222222',
        ], $over);
    }

    public function test_only_admins_can_open_or_save_settings(): void
    {
        $this->get('/admin/settings')->assertRedirect('/admin/login');
        $this->post('/admin/settings', $this->payload())->assertRedirect('/admin/login');

        $this->actingAs($this->makeUser())
            ->get('/admin/settings')->assertForbidden();
        $this->post('/admin/settings', $this->payload())->assertForbidden();
        $this->assertSame(0, Setting::count());
    }

    public function test_defaults_apply_when_nothing_is_saved(): void
    {
        $this->assertSame('ShopEase', config('app.name'));
        $this->assertSame('#16a34a', config('site.primary_color'));
        $this->assertSame('', config('site.palette_css'));

        $this->get('/')->assertOk()
            ->assertSee('<title inertia>ShopEase</title>', false)
            ->assertDontSee('site-palette', false)
            ->assertSee('href="/favicon.svg"', false);

        $this->actingAs($this->admin())->get('/admin/settings')->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Settings/Index', false)
                ->where('settings.site_name', 'ShopEase')
                ->where('settings.primary_color', '#16a34a'));
    }

    public function test_saving_updates_name_colour_contact_and_shared_props(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/settings', $this->payload())
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        // The next request boots the app again and picks the saved values up.
        SiteSettings::applyToConfig();

        $this->assertSame('Rupa Bazar', config('app.name'));
        $this->assertSame('Rupa Bazar', config('mail.from.name'));
        $this->assertSame('01700000000', config('shop.contact.phone'));
        $this->assertNull(config('shop.social.instagram'));
        $this->assertSame('https://facebook.com/rupa', config('shop.social.facebook'));
        $this->assertSame(99.0, (float) config('shop.shipping_fee'));
        $this->assertSame(7, config('shop.low_stock_threshold'));
        $this->assertSame('01811111111', config('shop.payments.bkash.number'));

        $this->get('/')->assertOk()
            ->assertSee('<title inertia>Rupa Bazar</title>', false)
            ->assertSee('content="Rupa Bazar online shop"', false)
            ->assertSee('content="#2563eb"', false)
            ->assertSee('--color-green-600:#2563eb', false)
            ->assertInertia(fn ($page) => $page
                ->where('site.name', 'Rupa Bazar')
                ->where('site.tagline', 'Best deals in Dhaka')
                ->where('site.announcement.text', 'Eid sale 20% off')
                ->where('site.announcement.link', 'https://example.com/sale')
                ->where('site.footerText', 'Rupa Bazar 2026')
                ->where('site.primaryColor', '#2563eb')
                ->where('shop.shippingFee', 99)
                ->where('shop.freeShippingOver', 3000)
                ->where('shop.whatsapp', '8801700000000'));
    }

    public function test_clearing_the_announcement_hides_it_and_default_colour_removes_the_palette(): void
    {
        $this->actingAs($this->admin());
        $this->post('/admin/settings', $this->payload());
        $this->post('/admin/settings', $this->payload(['announcement_text' => '', 'announcement_link' => '', 'primary_color' => '#16A34A']));
        SiteSettings::applyToConfig();

        $this->assertSame('#16a34a', config('site.primary_color'));
        $this->get('/')->assertDontSee('site-palette', false)
            ->assertInertia(fn ($page) => $page->where('site.announcement', null));
    }

    public function test_shipping_settings_change_order_totals(): void
    {
        $this->actingAs($this->admin())->post('/admin/settings', $this->payload(['shipping_fee' => 250, 'free_shipping_over' => 0]));
        SiteSettings::applyToConfig();

        $customer = $this->makeUser();
        $product = $this->makeProduct(['price' => 500, 'stock' => 10]);
        $this->addToCart($customer, $product, 1);
        $this->actingAs($customer)->post('/checkout', $this->shipping());

        $this->assertDatabaseHas('orders', ['user_id' => $customer->id, 'shipping_fee' => 250, 'total_amount' => 750]);
    }

    public function test_logo_and_favicon_upload_replace_and_remove(): void
    {
        $this->actingAs($this->admin());

        $this->post('/admin/settings', $this->payload([
            'logo' => UploadedFile::fake()->image('logo.png', 300, 100),
            'favicon' => UploadedFile::fake()->image('fav.png', 64, 64),
        ]))->assertSessionHasNoErrors();

        $logo = Setting::where('key', 'logo')->value('value');
        $favicon = Setting::where('key', 'favicon')->value('value');
        $this->assertStringStartsWith('/images/uploads/site/', $logo);
        $this->assertFileExists(public_path(ltrim($logo, '/')));
        $this->assertFileExists(public_path(ltrim($favicon, '/')));

        SiteSettings::applyToConfig();
        $this->get('/')->assertSee('<link rel="icon" href="'.$favicon.'">', false)
            ->assertInertia(fn ($page) => $page->where('site.logo', $logo)->where('site.favicon', $favicon));

        // Saving again without files keeps them.
        $this->post('/admin/settings', $this->payload());
        $this->assertSame($logo, Setting::where('key', 'logo')->value('value'));

        // Replacing deletes the old file.
        $this->post('/admin/settings', $this->payload(['logo' => UploadedFile::fake()->image('new.jpg', 200, 80)]))->assertSessionHasNoErrors();
        $newLogo = Setting::where('key', 'logo')->value('value');
        $this->assertNotSame($logo, $newLogo);
        $this->assertFileDoesNotExist(public_path(ltrim($logo, '/')));
        $this->assertFileExists(public_path(ltrim($newLogo, '/')));

        // Removing clears the setting and deletes the file.
        $this->post('/admin/settings', $this->payload(['remove_logo' => '1', 'remove_favicon' => '1']));
        $this->assertNull(Setting::where('key', 'logo')->value('value'));
        $this->assertNull(Setting::where('key', 'favicon')->value('value'));
        $this->assertFileDoesNotExist(public_path(ltrim($newLogo, '/')));
        $this->assertFileDoesNotExist(public_path(ltrim($favicon, '/')));
    }

    public function test_validation_rejects_bad_input_and_saves_nothing(): void
    {
        $this->actingAs($this->admin());

        $bad = [
            'site_name' => '',
            'primary_color' => 'blue',
            'announcement_link' => 'not a url',
            'facebook' => 'javascript:alert(1)',
            'email' => 'nope',
            'whatsapp' => '+88 017',
            'shipping_fee' => -1,
            'free_shipping_over' => 'abc',
            'low_stock_threshold' => 1.5,
            'logo' => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml'),
            'favicon' => UploadedFile::fake()->create('f.exe', 10, 'application/octet-stream'),
        ];

        $this->post('/admin/settings', $this->payload($bad))->assertSessionHasErrors(array_keys($bad));
        $this->assertSame(0, Setting::count());
        $this->assertSame('ShopEase', SiteSettings::all()['site_name']);
    }

    public function test_html_in_text_settings_is_escaped_in_the_page_shell(): void
    {
        $this->actingAs($this->admin())->post('/admin/settings', $this->payload(['site_name' => '<script>alert(1)</script>']));
        SiteSettings::applyToConfig();

        $this->get('/')->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_cache_is_refreshed_after_saving(): void
    {
        $this->assertSame('ShopEase', SiteSettings::all()['site_name']); // primes the cache
        $this->actingAs($this->admin())->post('/admin/settings', $this->payload());
        $this->assertSame('Rupa Bazar', SiteSettings::all()['site_name']);
    }

    public function test_unmigrated_table_does_not_break_boot(): void
    {
        \Illuminate\Support\Facades\Schema::drop('settings');
        Cache::forget(SiteSettings::CACHE_KEY);

        SiteSettings::applyToConfig(); // must not throw
        $this->assertTrue(true);
    }

    public function test_settings_are_hidden_and_not_found_unless_switched_on(): void
    {
        $this->actingAs($this->makeAdmin());
        config(['shop.settings_access' => false]);

        $this->get('/admin/settings')->assertNotFound();
        $this->post('/admin/settings', $this->payload(['site_name' => 'Hacked']))->assertNotFound();
        $this->assertNull(Setting::where('key', 'site_name')->value('value'));

        // the rest of the admin panel is untouched and the menu does not offer Settings
        $this->get('/admin')->assertOk()->assertInertia(fn ($page) => $page->where('shop.settingsAccess', false));

        // visitors and customers still see the saved name and logo
        $this->get('/')->assertOk();

        config(['shop.settings_access' => true]);
        $this->get('/admin/settings')->assertOk();
    }

    public function test_guests_and_customers_never_learn_the_settings_address_exists(): void
    {
        config(['shop.settings_access' => false]);

        $this->get('/admin/settings')->assertRedirect('/admin/login');
        $this->actingAs($this->makeUser());
        $this->get('/admin/settings')->assertForbidden();
    }

    public function test_banner_and_shop_links_cannot_be_script_addresses(): void
    {
        $this->actingAs($this->admin());

        $this->post('/admin/sliders', ['title' => 'X', 'link' => 'javascript:alert(1)', 'image' => UploadedFile::fake()->image('a.jpg', 1600, 640), 'is_active' => true])
            ->assertSessionHasErrors('link');
        $this->post('/admin/settings', $this->payload(['announcement_link' => 'javascript:alert(1)']))->assertSessionHasErrors('announcement_link');
        $this->post('/admin/settings', $this->payload(['facebook' => 'ftp://example.com']))->assertSessionHasErrors('facebook');

        // normal addresses still work
        $this->post('/admin/sliders', ['title' => 'Ok', 'link' => '/products?category=fashion', 'image' => UploadedFile::fake()->image('a.jpg', 1600, 640), 'is_active' => true, 'sort_order' => 1])
            ->assertSessionHasNoErrors();
    }
}
