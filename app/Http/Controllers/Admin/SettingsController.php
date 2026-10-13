<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesImageUploads;
use App\Http\Controllers\Controller;
use App\Services\SiteSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    use HandlesImageUploads;

    public function index(): Response
    {
        return Inertia::render('Admin/Settings/Index', [
            'settings' => SiteSettings::all(),
            'defaultColor' => SiteSettings::DEFAULT_COLOR,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'site_name' => ['required', 'string', 'max:60'],
            'tagline' => ['nullable', 'string', 'max:300'],
            'meta_description' => ['nullable', 'string', 'max:300'],
            'primary_color' => ['sometimes', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'announcement_text' => ['nullable', 'string', 'max:200'],
            'announcement_link' => ['nullable', 'url:http,https', 'max:255'],
            'footer_text' => ['nullable', 'string', 'max:300'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'hours' => ['nullable', 'string', 'max:120'],
            'whatsapp' => ['nullable', 'regex:/^\d{8,15}$/'],
            'facebook' => ['nullable', 'url:http,https', 'max:255'],
            'instagram' => ['nullable', 'url:http,https', 'max:255'],
            'youtube' => ['nullable', 'url:http,https', 'max:255'],
            'tiktok' => ['nullable', 'url:http,https', 'max:255'],
            'x' => ['nullable', 'url:http,https', 'max:255'],
            'shipping_fee' => ['required', 'numeric', 'min:0', 'max:100000'],
            'free_shipping_over' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'low_stock_threshold' => ['required', 'integer', 'min:0', 'max:10000'],
            'bkash_number' => ['nullable', 'string', 'max:30'],
            'bkash_type' => ['nullable', 'in:Personal,Agent,Merchant'],
            'advance_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'premium_from' => ['nullable', 'integer', 'min:1', 'max:10000000'],
            'map_query' => ['nullable', 'string', 'max:200'],
            'nagad_number' => ['nullable', 'string', 'max:30'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'favicon' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,ico', 'max:1024'],
            'remove_logo' => ['nullable', 'boolean'],
            'remove_favicon' => ['nullable', 'boolean'],
        ], [
            'whatsapp.regex' => 'WhatsApp number must be digits only with country code, e.g. 8801700000000.',
            'primary_color.regex' => 'Pick a valid colour (e.g. #16a34a).',
        ]);

        $current = SiteSettings::all();
        $save = collect($data)->except(['logo', 'favicon', 'remove_logo', 'remove_favicon'])->all();
        if (isset($data['primary_color'])) {
            $save['primary_color'] = strtolower($data['primary_color']);
        }

        foreach (['logo', 'favicon'] as $field) {
            if ($request->hasFile($field)) {
                $save[$field] = $this->storeImage($request->file($field), 'site', $field === 'logo' ? 'logo' : 'none');
                $this->deleteImage($current[$field] ?? null);
            } elseif ($request->boolean("remove_{$field}")) {
                $this->deleteImage($current[$field] ?? null);
                $save[$field] = null;
            }
        }

        SiteSettings::save($save);

        return back()->with('success', 'Site settings saved.');
    }
}
