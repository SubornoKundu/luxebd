<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the logged-in user's profile.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'user' => $request->user(),
            'hasPassword' => $request->user()->password !== null,
        ]);
    }

    /**
     * Update the logged-in user's name, phone and default delivery address.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => [
                'nullable', 'string', 'max:20',
                Rule::unique('users', 'phone')->ignore($user->id),
            ],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
        ]);

        $user->update($data);

        return back()->with('success', 'Profile updated.');
    }

    /**
     * Change the account password (the current one is required unless the
     * account was created before passwords existed).
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();

        $rules = ['password' => ['required', 'confirmed', Password::min(8)]];

        if ($user->password !== null) {
            $rules['current_password'] = ['required', 'current_password'];
        }

        $data = $request->validate($rules);

        $user->update(['password' => $data['password']]);

        return back()->with('success', 'Password changed.');
    }
}
