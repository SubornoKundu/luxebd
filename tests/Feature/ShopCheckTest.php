<?php

namespace Tests\Feature;

use App\Console\Commands\ShopCheck;
use Tests\TestCase;

class ShopCheckTest extends TestCase
{
    public function test_size_settings_are_read_correctly(): void
    {
        $this->assertSame(20.0, ShopCheck::megabytes('20M'));
        $this->assertSame(1024.0, ShopCheck::megabytes('1G'));
        $this->assertSame(0.5, ShopCheck::megabytes('512K'));
        $this->assertNull(ShopCheck::megabytes('-1'));
        $this->assertNull(ShopCheck::megabytes(''));
    }

    public function test_the_deploy_check_runs_and_reports_problems(): void
    {
        config(['app.debug' => true]);

        $this->artisan('shop:check')
            ->expectsOutputToContain('Vireluxe deploy check')
            ->expectsOutputToContain('APP_DEBUG is false')
            ->assertExitCode(1);
    }

    public function test_the_deploy_files_exist_and_the_server_setup_is_complete(): void
    {
        foreach (['deploy/deploy.sh', 'deploy/nginx.conf.example', 'deploy/php-shopease.ini', 'deploy/supervisor-queue.conf.example'] as $file) {
            $this->assertFileExists(base_path($file), $file);
        }

        $this->assertStringContainsString('client_max_body_size 64M', file_get_contents(base_path('deploy/nginx.conf.example')));
        $this->assertStringContainsString('post_max_size = 64M', file_get_contents(base_path('deploy/php-shopease.ini')));
    }

    public function test_a_live_https_site_produces_https_links_even_behind_a_proxy(): void
    {
        config(['app.url' => 'https://shop.example.com']);
        $this->app['env'] = 'production';
        (new \App\Providers\AppServiceProvider($this->app))->boot();

        $this->assertStringStartsWith('https://', url('/products'));
        $this->assertStringStartsWith('https://', route('products.index'));

        \Illuminate\Support\Facades\URL::forceScheme('http');
    }
}
