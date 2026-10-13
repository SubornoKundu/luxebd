<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureUserNotBlocked;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => EnsureAdmin::class,
            'settings.open' => \App\Http\Middleware\EnsureSettingsOpen::class,
        ]);

        // Guests who tap "add to cart", "wishlist" or "checkout" land on the login page
        // with a short explanation of why.
        $middleware->redirectGuestsTo(function (Request $request) {
            // The admin panel has its own sign-in page, separate from the customers'.
            if ($request->is('admin', 'admin/*')) {
                return route('admin.login');
            }

            $notice = match (true) {
                $request->is('cart*') => 'Please sign in to add items to your cart.',
                $request->is('wishlist*') => 'Please sign in to save items to your wishlist.',
                $request->is('checkout*', 'payment*') => 'Please sign in to place your order.',
                $request->is('orders*') => 'Please sign in to see your orders.',
                default => 'Please sign in to continue.',
            };

            if ($request->hasSession()) {
                $request->session()->flash('login_notice', $notice);
            }

            return route('login');
        });

        // A signed-in admin who opens a sign-in page goes straight to the panel.
        $middleware->redirectUsersTo(fn (Request $request) => $request->user()?->isAdmin() ? route('admin.dashboard') : route('home'));

        $middleware->web(append: [
            \App\Http\Middleware\SecurityHeaders::class,
            EnsureUserNotBlocked::class,
            HandleInertiaRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Friendly messages instead of a bare error page for expired sessions and rate limits.
        $exceptions->respond(function (\Symfony\Component\HttpFoundation\Response $response, \Throwable $e, Request $request) {
            $status = $response->getStatusCode();

            // Branded error page (Home / Products buttons) for 403, 404, 500 and 503.
            // In debug mode a 500 keeps the developer error screen so bugs stay visible.
            $branded = in_array($status, [403, 404], true)
                || (in_array($status, [500, 503], true) && ! config('app.debug'));

            if ($branded && ! $request->expectsJson() && ! $request->is('api/*')) {
                try {
                    return \Inertia\Inertia::render('Error', ['status' => $status])
                        ->toResponse($request)
                        ->setStatusCode($status);
                } catch (\Throwable) {
                    return $response;
                }
            }

            if (! in_array($status, [419, 429], true) || $request->expectsJson() || $request->is('api/*')) {
                return $response;
            }

            $message = $status === 419
                ? 'Your session expired. Please try again.'
                : 'Too many attempts. Please wait a moment and try again.';

            return back(fallback: '/')->with('error', $message);
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
