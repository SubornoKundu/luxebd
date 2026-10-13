<?php

namespace App\Http\Controllers;

abstract class Controller
{
    /**
     * Title / description / picture for the page's social-share (Open Graph) tags.
     * Crawlers (WhatsApp, Facebook) do not run JavaScript, so these are printed by
     * the server in resources/views/app.blade.php.
     */
    protected function seo(string $title, ?string $description = null, ?string $image = null, string $type = 'website'): void
    {
        request()->attributes->set('seo', array_filter([
            'title' => $title,
            'description' => $description ? \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags($description))), 200) : null,
            'image' => $image,
            'type' => $type,
        ]));
    }
}
