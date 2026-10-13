<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use Concerns, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_pages_render(): void
    {
        $this->get('/login')->assertOk();
        $this->get('/register')->assertOk();
        $this->get('/forgot-password')->assertOk();
    }

    public function test_register_creates_verified_customer_and_logs_in(): void
    {
        $response = $this->post('/register', [
            'name' => 'New Person', 'email' => 'New@Example.com', 'phone' => '01712345678',
            'password' => 'Password1', 'password_confirmation' => 'Password1',
        ]);

        $response->assertRedirect('/');
        $user = User::where('email', 'new@example.com')->firstOrFail();
        $this->assertSame('customer', $user->role);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check('Password1', $user->password));
        $this->assertAuthenticatedAs($user);
    }

    public function test_register_validation(): void
    {
        $this->makeUser('taken@example.com');

        $this->post('/register', ['name' => '', 'email' => 'taken@example.com', 'password' => 'short', 'password_confirmation' => 'nope'])
            ->assertSessionHasErrors(['name', 'email', 'password']);
        $this->assertGuest();
    }

    public function test_cannot_self_assign_admin_role(): void
    {
        $this->post('/register', [
            'name' => 'Sneaky', 'email' => 'sneaky@example.com', 'role' => 'admin',
            'password' => 'Password1', 'password_confirmation' => 'Password1',
        ]);

        $this->assertSame('customer', User::where('email', 'sneaky@example.com')->value('role'));
    }

    public function test_login_logout(): void
    {
        $user = $this->makeUser();

        $this->post('/login', ['email' => 'buyer@example.com', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/login', ['email' => 'BUYER@example.com', 'password' => 'Secret@123'])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_admin_login_goes_to_admin_panel(): void
    {
        $this->makeAdmin();

        $this->post('/admin/login', ['email' => 'boss@example.com', 'password' => 'Secret@123'])->assertRedirect('/admin');
        $this->assertAuthenticated();
    }

    public function test_admins_cannot_sign_in_on_the_customer_page_and_customers_not_on_the_admin_page(): void
    {
        $this->makeAdmin();
        $this->makeUser();

        $this->post('/login', ['email' => 'boss@example.com', 'password' => 'Secret@123'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/admin/login', ['email' => 'buyer@example.com', 'password' => 'Secret@123'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->get('/admin/login')->assertOk();
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/profile')->assertRedirect('/login');
    }

    public function test_blocked_user_cannot_login_and_is_kicked_out(): void
    {
        $user = $this->makeUser();
        $user->forceFill(['is_blocked' => true])->save();

        $this->post('/login', ['email' => 'buyer@example.com', 'password' => 'Secret@123'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $user->forceFill(['is_blocked' => false])->save();
        $this->actingAs($user)->get('/profile')->assertOk();

        $user->forceFill(['is_blocked' => true])->save();
        $this->actingAs($user)->get('/profile')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_login_is_rate_limited(): void
    {
        $this->makeUser();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'buyer@example.com', 'password' => 'bad']);
        }

        $this->post('/login', ['email' => 'buyer@example.com', 'password' => 'Secret@123'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_password_reset_with_an_emailed_code(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        $user = $this->makeUser();

        $this->post('/forgot-password', ['email' => 'buyer@example.com'])->assertRedirect('/reset-password?email=buyer%40example.com')->assertSessionHas('success');
        // Unknown emails get the same answer and no mail.
        $this->post('/forgot-password', ['email' => 'nobody@example.com'])->assertSessionHas('success');
        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\PasswordCodeMail::class, 1);

        $code = null;
        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\PasswordCodeMail::class, function ($m) use (&$code) {
            $code = $m->code;

            return $m->hasTo('buyer@example.com');
        });

        $this->get('/reset-password?email=buyer@example.com')->assertOk();

        // A wrong code changes nothing.
        $this->post('/reset-password', ['email' => $user->email, 'code' => '000000', 'password' => 'BrandNew99', 'password_confirmation' => 'BrandNew99'])
            ->assertSessionHasErrors('code');
        $this->assertTrue(Hash::check('Secret@123', $user->fresh()->password));

        $this->post('/reset-password', ['email' => $user->email, 'code' => $code, 'password' => 'BrandNew99', 'password_confirmation' => 'BrandNew99'])
            ->assertRedirect('/login');
        $this->assertTrue(Hash::check('BrandNew99', $user->fresh()->password));

        // The code works once.
        $this->post('/reset-password', ['email' => $user->email, 'code' => $code, 'password' => 'Another99x', 'password_confirmation' => 'Another99x'])
            ->assertSessionHasErrors('code');
    }

    public function test_reset_code_locks_after_five_wrong_tries_and_admins_return_to_the_admin_login(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        $admin = $this->makeAdmin();

        $this->post('/forgot-password', ['email' => 'boss@example.com']);
        $code = null;
        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\PasswordCodeMail::class, function ($m) use (&$code) {
            $code = $m->code;

            return true;
        });

        $wrong = $code === '123456' ? '654321' : '123456';
        for ($i = 0; $i < 5; $i++) {
            $this->post('/reset-password', ['email' => 'boss@example.com', 'code' => $wrong, 'password' => 'BrandNew99', 'password_confirmation' => 'BrandNew99'])
                ->assertSessionHasErrors('code');
        }
        // Locked: even the right code no longer works.
        $this->post('/reset-password', ['email' => 'boss@example.com', 'code' => $code, 'password' => 'BrandNew99', 'password_confirmation' => 'BrandNew99'])
            ->assertSessionHasErrors('code');
        $this->assertTrue(Hash::check('Secret@123', $admin->fresh()->password));

        \Illuminate\Support\Facades\Cache::flush();
        $this->post('/forgot-password', ['email' => 'boss@example.com']);
        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\PasswordCodeMail::class, function ($m) use (&$code) {
            $code = $m->code;

            return true;
        });
        $this->post('/reset-password', ['email' => 'boss@example.com', 'code' => $code, 'password' => 'BrandNew99', 'password_confirmation' => 'BrandNew99'])
            ->assertRedirect('/admin/login');
    }

    public function test_profile_and_password_update(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->put('/profile', ['name' => 'Renamed', 'phone' => '01799999999', 'address' => 'Road 9', 'city' => 'Sylhet'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Sylhet', $user->fresh()->city);
        $this->assertSame('customer', $user->fresh()->role);

        $this->put('/profile/password', ['current_password' => 'nope', 'password' => 'NewPass123', 'password_confirmation' => 'NewPass123'])
            ->assertSessionHasErrors('current_password');

        $this->put('/profile/password', ['current_password' => 'Secret@123', 'password' => 'NewPass123', 'password_confirmation' => 'NewPass123'])
            ->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('NewPass123', $user->fresh()->password));
    }

    public function test_emails_carry_a_plain_text_part_too(): void
    {
        $code = new \App\Mail\PasswordCodeMail('482913', 10);
        $this->assertStringContainsString('482913', $code->render());
        $text = view('emails.password-code-text', ['code' => '482913', 'expiryMinutes' => 10])->render();
        $this->assertStringContainsString('482913', $text);
        $this->assertStringNotContainsString('<', $text);
    }

    public function test_the_code_is_checked_first_and_the_password_comes_after(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        $user = $this->makeUser();
        $this->post('/forgot-password', ['email' => 'buyer@example.com']);

        $code = null;
        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\PasswordCodeMail::class, function ($m) use (&$code) {
            $code = $m->code;

            return true;
        });

        // A wrong code is refused at step 1 and nothing changes.
        $this->post('/reset-password/check', ['email' => $user->email, 'code' => '000000'])->assertSessionHasErrors('code');
        $this->post('/reset-password/check', ['email' => 'nobody@example.com', 'code' => $code])->assertSessionHasErrors('code');

        // The right code passes, and checking does not use it up.
        $this->post('/reset-password/check', ['email' => $user->email, 'code' => $code])->assertSessionHasNoErrors();
        $this->post('/reset-password/check', ['email' => $user->email, 'code' => $code])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('Secret@123', $user->fresh()->password));

        // Step 2 still re-checks the code: it is what changes the password.
        $this->post('/reset-password', ['email' => $user->email, 'code' => '111111', 'password' => 'BrandNew99', 'password_confirmation' => 'BrandNew99'])
            ->assertSessionHasErrors('code');
        $this->post('/reset-password', ['email' => $user->email, 'code' => $code, 'password' => 'BrandNew99', 'password_confirmation' => 'BrandNew99'])
            ->assertRedirect('/login');
        $this->assertTrue(Hash::check('BrandNew99', $user->fresh()->password));

        // Used up now: checking again fails.
        $this->post('/reset-password/check', ['email' => $user->email, 'code' => $code])->assertSessionHasErrors('code');
    }

    public function test_guessing_codes_at_the_check_step_locks_the_code(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        $user = $this->makeUser();
        $this->post('/forgot-password', ['email' => 'buyer@example.com']);

        $code = null;
        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\PasswordCodeMail::class, function ($m) use (&$code) {
            $code = $m->code;

            return true;
        });

        $wrong = $code === '000000' ? '000001' : '000000';
        for ($i = 0; $i < 5; $i++) {
            $this->post('/reset-password/check', ['email' => $user->email, 'code' => $wrong])->assertSessionHasErrors('code');
        }

        // Locked: even the real code no longer works.
        $this->post('/reset-password/check', ['email' => $user->email, 'code' => $code])->assertSessionHasErrors('code');
    }

    public function test_the_reset_pages_know_the_email_and_where_to_go_back_to(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        $this->makeUser('boss@example.com', 'admin');

        // Customer: no "from", the email is carried to the code page so it is not typed again.
        $this->get('/forgot-password')->assertInertia(fn ($p) => $p->component('Auth/ForgotPassword')->where('from', ''));
        $this->post('/forgot-password', ['email' => 'Boss@Example.com'])->assertRedirect('/reset-password?email=boss%40example.com');
        $this->get('/reset-password?email=boss@example.com')->assertInertia(fn ($p) => $p
            ->component('Auth/ResetPassword')->where('email', 'boss@example.com')->where('from', ''));

        // Admin sign-in page links with from=admin and it is carried along.
        $this->get('/forgot-password?from=admin')->assertInertia(fn ($p) => $p->where('from', 'admin'));
        $this->post('/forgot-password', ['email' => 'boss@example.com', 'from' => 'admin'])
            ->assertRedirect('/reset-password?email=boss%40example.com&from=admin');
        $this->get('/reset-password?email=boss@example.com&from=admin')->assertInertia(fn ($p) => $p->where('from', 'admin'));

        // Anything else is ignored.
        $this->get('/reset-password?email=boss@example.com&from=https://evil.example')->assertInertia(fn ($p) => $p->where('from', ''));
        $this->post('/forgot-password', ['email' => 'boss@example.com', 'from' => 'https://evil.example'])->assertSessionHasErrors('from');
    }
}
