<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The Site Settings page (name, logo, colours...) belongs to whoever built the site.
 * It stays hidden, and its address answers "not found", unless SHOP_SETTINGS_ACCESS=true
 * is set in the server's .env file. Turn it on, change what you need, turn it off again.
 */
class EnsureSettingsOpen
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('shop.settings_access'), 404);

        return $next($request);
    }
}
