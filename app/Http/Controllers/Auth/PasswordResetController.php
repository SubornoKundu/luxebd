<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\PasswordCodeMail;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Forgot password" with a 6-digit security code sent by email (customers and
 * admins alike). The code is kept hashed in the cache, works for 10 minutes,
 * once, and is locked after 5 wrong tries.
 */
class PasswordResetController extends Controller
{
    private const MINUTES = 10;

    private const MAX_TRIES = 5;

    private const RESEND_SECONDS = 45;

    public function create(Request $request): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'from' => $this->from($request->query('from')),
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'from' => ['nullable', 'in:admin,customer']]);
        $email = Str::lower(trim($data['email']));

        $user = User::where('email', $email)->first();

        if ($user && ! $user->is_blocked && $this->mayIssueCode($email)) {
            $code = (string) random_int(100000, 999999);
            Cache::put($this->key($email, 'code'), ['hash' => Hash::make($code), 'tries' => 0], now()->addMinutes(self::MINUTES));

            try {
                Mail::to($email)->send(new PasswordCodeMail($code, self::MINUTES));
            } catch (\Throwable $e) {
                // Mail is not set up or the provider refused it. Do not tell the visitor (that
                // would reveal the account exists), but leave a trace for the owner.
                report($e);
                Log::warning('Password reset code could not be emailed to '.$email);
            }
        }

        // Same answer whether or not the email exists, so this form can't
        // be used to discover who has an account.
        $message = 'If that email is registered, we sent a 6-digit code to it. The code works for '.self::MINUTES.' minutes.';

        if (config('mail.default') === 'log') {
            $message .= ' (Mail is in log mode: the code is in storage/logs/laravel.log.)';
        }

        $query = ['email' => $email];
        if ($this->from($data['from'] ?? null) === 'admin') {
            $query['from'] = 'admin'; // so the page can send an admin back to the admin sign-in
        }

        return redirect()->route('password.code', $query)->with('success', $message);
    }

    public function edit(Request $request): Response
    {
        return Inertia::render('Auth/ResetPassword', [
            'email' => (string) $request->query('email', ''),
            'from' => $this->from($request->query('from')),
        ]);
    }

    /**
     * Step 1 of the reset page: is the code right? The code stays valid (wrong guesses still
     * count towards the lock); the password is only changed in update(), which checks the code again.
     */
    public function check(Request $request): RedirectResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:6'],
        ]);

        $this->assertCode($data['email'], $data['code']);

        $user = User::where('email', $data['email'])->first();
        if (! $user || $user->is_blocked) {
            throw $this->invalid();
        }

        return back()->with('success', 'Code is correct. Now choose your new password.');
    }

    public function update(Request $request): RedirectResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:6'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $this->assertCode($data['email'], $data['code']);

        $codeKey = $this->key($data['email'], 'code');
        $user = User::where('email', $data['email'])->first();
        Cache::forget($codeKey);

        if (! $user || $user->is_blocked) {
            throw $this->invalid();
        }

        $user->forceFill([
            'password' => $data['password'],
            'remember_token' => Str::random(60),
        ])->save();

        event(new PasswordReset($user));

        return redirect()->route($user->isAdmin() ? 'admin.login' : 'login')
            ->with('success', 'Your password has been changed. You can sign in now.');
    }

    /** Where the visitor came from, so "back to sign in" goes to the right sign-in page. */
    private function from(mixed $value): string
    {
        return $value === 'admin' ? 'admin' : '';
    }

    private function invalid(): ValidationException
    {
        return ValidationException::withMessages(['code' => 'That code is wrong or has expired. Ask for a new one.']);
    }

    /** Throws unless the code is the live one for this address. Wrong guesses are counted; five lock it. */
    private function assertCode(string $email, string $code): void
    {
        $codeKey = $this->key($email, 'code');
        $entry = Cache::get($codeKey);

        if (! is_array($entry)) {
            throw $this->invalid();
        }

        if (! Hash::check($code, $entry['hash'])) {
            $entry['tries']++;
            if ($entry['tries'] >= self::MAX_TRIES) {
                Cache::forget($codeKey);
                throw ValidationException::withMessages(['code' => 'Too many wrong codes. Ask for a new one.']);
            }
            Cache::put($codeKey, $entry, now()->addMinutes(self::MINUTES));
            throw $this->invalid();
        }
    }

    /** One code per 45 seconds and at most 5 per hour for an address, so codes cannot be guessed by asking for new ones. */
    private function mayIssueCode(string $email): bool
    {
        if (! Cache::add($this->key($email, 'sent'), true, self::RESEND_SECONDS)) {
            return false;
        }

        $hourKey = $this->key($email, 'hour');
        Cache::add($hourKey, 0, now()->addHour());

        return Cache::increment($hourKey) <= 5;
    }

    private function key(string $email, string $what): string
    {
        return 'pwreset:'.$what.':'.sha1($email);
    }
}
