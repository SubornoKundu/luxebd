<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manage who can use the admin panel: add a new admin, promote an existing
 * customer, or take admin access away again.
 */
class AdminUserController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Admin/Admins/Index', [
            'admins' => User::query()
                ->where('role', 'admin')
                ->orderBy('id')
                ->get(['id', 'name', 'email', 'phone', 'created_at'])
                ->map(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'phone' => $u->phone,
                    'created_at' => $u->created_at,
                    'is_me' => $u->id === $request->user()->id,
                ])
                ->values(),
        ]);
    }

    /** Create a brand-new admin account. */
    public function store(Request $request): RedirectResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20', 'unique:users,phone'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = new User([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
        ]);
        $user->forceFill(['role' => 'admin', 'is_blocked' => false, 'email_verified_at' => now()])->save();

        return back()->with('success', "{$user->name} is now an admin.");
    }

    /** Give an existing customer admin access (by email). */
    public function promote(Request $request): RedirectResponse
    {
        $request->merge(['promote_email' => Str::lower(trim((string) $request->input('promote_email')))]);
        $data = $request->validate(['promote_email' => ['required', 'string', 'email', 'max:255']]);

        $user = User::where('email', $data['promote_email'])->first();

        if (! $user) {
            return back()->withErrors(['promote_email' => 'No account found with this email.']);
        }
        if ($user->isAdmin()) {
            return back()->withErrors(['promote_email' => 'This person is already an admin.']);
        }
        if ($user->is_blocked) {
            return back()->withErrors(['promote_email' => 'This account is suspended. Un-suspend it from Customers first.']);
        }

        $user->forceFill(['role' => 'admin'])->save();

        return back()->with('success', "{$user->name} is now an admin.");
    }

    /** Take admin access away (the account stays, as a normal customer). */
    public function demote(Request $request, User $admin): RedirectResponse
    {
        abort_unless($admin->isAdmin(), 404);

        if ($admin->id === $request->user()->id) {
            return back()->with('error', 'You cannot remove your own admin access.');
        }
        if (User::where('role', 'admin')->count() <= 1) {
            return back()->with('error', 'At least one admin must remain.');
        }

        $admin->forceFill(['role' => 'customer'])->save();

        return back()->with('success', "{$admin->name} is no longer an admin.");
    }
}
