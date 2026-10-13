<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * php artisan shop:check
 *
 * Run it on the server after deploying. It lists everything that could make the live site
 * misbehave (missing PHP extensions, small upload limits, folders that cannot be written,
 * debug mode on...). FAIL = fix before going live. WARN = worth a look. Exit code is 1 only on FAIL.
 */
class ShopCheck extends Command
{
    protected $signature = 'shop:check';

    protected $description = 'Check that this server and .env are ready for the live Vireluxe site';

    private int $fails = 0;

    private int $warns = 0;

    public function handle(): int
    {
        $this->line('');
        $this->info('Vireluxe deploy check');
        $this->line(str_repeat('-', 60));

        // --- PHP and extensions
        $this->check(version_compare(PHP_VERSION, '8.4.1', '>='), 'PHP '.PHP_VERSION.' (8.4.1 or newer needed: the installed Symfony 8 packages require it)');

        foreach (['pdo_mysql', 'mbstring', 'openssl', 'fileinfo', 'ctype', 'tokenizer', 'xml', 'curl'] as $ext) {
            $this->check(extension_loaded($ext), "PHP extension {$ext}");
        }

        $gd = extension_loaded('gd') ? gd_info() : [];
        $this->check(! empty($gd), 'PHP extension gd (resizes uploaded pictures; without it originals are kept)', 'warn');
        $this->check(! empty($gd['JPEG Support']) || ! empty($gd['JPG Support']), 'GD can read JPEG', 'warn');
        $this->check(! empty($gd['PNG Support']), 'GD can read PNG', 'warn');
        $this->check(! empty($gd['WebP Support']), 'GD can read WebP', 'warn');
        $this->check(function_exists('exif_read_data'), 'PHP extension exif (turns phone photos upright)', 'warn');
        $this->check(function_exists('opcache_get_status') && ini_get('opcache.enable'), 'OPcache is on (faster pages)', 'warn');

        // --- Limits (the command line can use a different php.ini from the website)
        $this->line('Limits below are for the command line. The website (PHP-FPM / Apache) may use another php.ini: check phpinfo() there too.');
        $this->limit('upload_max_filesize', 8, 'at least 8M, 20M recommended');
        $this->limit('post_max_size', 32, 'at least 32M, 64M recommended (gallery uploads 10 pictures at once)');
        $this->limit('memory_limit', 128, 'at least 128M, 256M recommended');
        $this->check((int) ini_get('max_file_uploads') >= 20, 'max_file_uploads = '.ini_get('max_file_uploads').' (20 or more needed)', 'warn');

        // --- Folders
        foreach (['public/images/uploads', 'storage', 'storage/logs', 'storage/framework', 'bootstrap/cache'] as $dir) {
            $path = base_path($dir);
            if (! is_dir($path) && $dir === 'public/images/uploads') {
                @mkdir($path, 0775, true);
            }
            $this->check(is_dir($path) && is_writable($path), "Folder {$dir} exists and is writable");
        }

        $this->check(is_dir(public_path('build')), 'Front end is built (public/build exists: run npm run build)');

        // --- .env
        $this->check(config('app.env') === 'production', 'APP_ENV = '.config('app.env').' (should be production)', 'warn');
        // On your own computer (APP_ENV=local) debug mode is normal, so it is only a warning there.
        $this->check(! config('app.debug'), 'APP_DEBUG is false (true shows code and paths to visitors)', config('app.env') === 'local' ? 'warn' : 'fail');
        $this->check(! str_contains((string) config('app.url'), 'localhost') && ! str_contains((string) config('app.url'), '127.0.0.1'), 'APP_URL = '.config('app.url').' (set your real address)', 'warn');
        $this->check(str_starts_with((string) config('app.url'), 'https://'), 'APP_URL uses https', 'warn');
        $this->check(config('app.key') !== null && config('app.key') !== '', 'APP_KEY is set');
        $this->check(config('database.default') === 'mysql', 'Database is MySQL', 'warn');

        $this->check(config('mail.default') !== 'log', 'Real email should be set up (MAIL_MAILER is '.config('mail.default').'; "log" only writes emails to a file)', 'warn');
        $this->check((int) config('shop.advance_percent') >= 0 && (int) config('shop.advance_percent') <= 100, 'bKash advance is '.(int) config('shop.advance_percent').'%');

        $this->check(config('queue.default') !== 'sync', 'Queue is '.config('queue.default').' (use database or redis and run a worker for fast checkout)', 'warn');
        $this->check(! in_array(config('cache.default'), ['array'], true), 'Cache store is '.config('cache.default'), 'warn');

        // --- Database
        try {
            DB::connection()->getPdo();
            $this->check(true, 'Database connection works');
            $pending = Schema::hasTable('migrations')
                ? count(array_diff(
                    array_map(fn ($f) => basename($f, '.php'), glob(database_path('migrations/*.php')) ?: []),
                    DB::table('migrations')->pluck('migration')->all()
                ))
                : -1;
            $this->check($pending === 0, $pending < 0 ? 'Tables are not created yet (run php artisan migrate --force)' : ($pending === 0 ? 'All migrations are run' : "{$pending} migration(s) not run yet (php artisan migrate --force)"));

            // Order e-mails are sent by a background worker. If jobs wait for minutes, no worker is running.
            if (config('queue.default') === 'database' && Schema::hasTable('jobs')) {
                $waiting = DB::table('jobs')->where('available_at', '<=', time() - 180)->count();
                $this->check($waiting === 0, $waiting === 0 ? 'No e-mails stuck in the queue' : "{$waiting} queued e-mail(s) have waited over 3 minutes: start the worker (php artisan queue:work, see deploy/supervisor-queue.conf.example)", 'warn');
            }
        } catch (\Throwable $e) {
            $this->check(false, 'Database connection works ('.substr($e->getMessage(), 0, 80).')');
        }

        $this->line(str_repeat('-', 60));
        $this->line("{$this->fails} to fix, {$this->warns} warning(s).");
        $this->line($this->fails ? '<fg=red>Not ready yet: fix the FAIL lines above.</>' : '<fg=green>Ready to go live.</>');
        $this->line('');

        return $this->fails ? self::FAILURE : self::SUCCESS;
    }

    private function check(bool $ok, string $label, string $level = 'fail'): void
    {
        if ($ok) {
            $this->line("  <fg=green>OK  </> {$label}");

            return;
        }

        if ($level === 'warn') {
            $this->warns++;
            $this->line("  <fg=yellow>WARN</> {$label}");

            return;
        }

        $this->fails++;
        $this->line("  <fg=red>FAIL</> {$label}");
    }

    private function limit(string $name, int $minMb, string $note): void
    {
        $raw = (string) ini_get($name);
        $mb = self::megabytes($raw);
        $this->check($mb === null || $mb >= $minMb, "{$name} = {$raw} ({$note})", 'warn');
    }

    /** "20M" -> 20, "1G" -> 1024, "-1" -> null (no limit). */
    public static function megabytes(string $value): ?float
    {
        $value = trim($value);

        if ($value === '' || $value === '-1') {
            return null;
        }

        $number = (float) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024,
            'k' => $number / 1024,
            'm' => $number,
            default => $number / 1048576,
        };
    }
}
