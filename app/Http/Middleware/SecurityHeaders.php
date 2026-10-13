<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser-side protections on every response: no framing (clickjacking), no
 * MIME sniffing, a short referrer, no camera/microphone/payment prompts, and
 * HTTPS-only once the site is served over HTTPS.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), payment=(), usb=(), geolocation=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin-allow-popups');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        // Admin and sign-in pages must never be kept in a shared or back-button cache.
        if ($request->is('admin', 'admin/*', 'login', 'forgot-password', 'reset-password')) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}
