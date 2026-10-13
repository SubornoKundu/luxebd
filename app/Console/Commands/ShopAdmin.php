<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * Create (or reset) an admin from the terminal. No admin lives in the code:
 * the first one is made here, more can be added from Admin > Admins.
 *
 *   php artisan shop:admin owner@example.com --replace
 */
class ShopAdmin extends Command
{
    protected $signature = 'shop:admin
        {email : Sign-in email of the admin}
        {--name= : Display name (default: Admin)}
        {--password= : Password (asked for when left out; avoid typing it here on a shared machine)}
        {--replace : Delete every OTHER admin account afterwards}';

    protected $description = 'Create or update an admin account; --replace removes all other admins';

    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));
        $password = (string) ($this->option('password') ?: $this->secret('Password (min 8 characters)'));

        $validator = Validator::make(
            ['email' => $email, 'password' => $password],
            ['email' => ['required', 'email', 'max:255'], 'password' => ['required', Password::min(8)]],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::firstOrNew(['email' => $email]);
        $user->name = $this->option('name') ?: ($user->exists ? $user->name : 'Admin');
        $user->password = $password;
        $user->forceFill(['role' => 'admin', 'is_blocked' => false, 'email_verified_at' => $user->email_verified_at ?? now()])->save();

        $this->info("Admin ready: {$email} (sign in at /admin/login).");

        if ($this->option('replace')) {
            $removed = 0;
            foreach (User::where('role', 'admin')->whereKeyNot($user->id)->get() as $other) {
                try {
                    $other->delete();
                } catch (\Throwable) {
                    // Cannot delete (still referenced): at least take away the access.
                    $other->forceFill(['role' => 'customer'])->save();
                    $this->warn("{$other->email} could not be deleted, so admin access was removed instead.");
                }
                $removed++;
            }
            $this->info("Other admin accounts removed: {$removed}.");
        }

        return self::SUCCESS;
    }
}
