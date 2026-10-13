<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUsersTest extends TestCase
{
    use Concerns, RefreshDatabase;

    private function payload(array $over = []): array
    {
        return array_merge([
            'name' => 'New Admin', 'email' => 'NewAdmin@Example.com', 'phone' => '01712345678',
            'password' => 'Secret@123', 'password_confirmation' => 'Secret@123',
        ], $over);
    }

    public function test_only_admins_can_manage_admins(): void
    {
        $this->get('/admin/admins')->assertRedirect('/admin/login');
        $this->post('/admin/admins', $this->payload())->assertRedirect('/admin/login');

        $customer = $this->makeUser();
        $this->actingAs($customer)->get('/admin/admins')->assertForbidden();
        $this->post('/admin/admins', $this->payload())->assertForbidden();
        $this->post('/admin/admins/promote', ['promote_email' => $customer->email])->assertForbidden();

        $this->assertSame('customer', $customer->fresh()->role);
        $this->assertDatabaseMissing('users', ['email' => 'newadmin@example.com']);
    }

    public function test_page_lists_admins(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->get('/admin/admins')->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Admins/Index', false)
                ->has('admins', 1)
                ->where('admins.0.is_me', true));
    }

    public function test_admin_can_create_a_new_admin_who_can_use_the_panel(): void
    {
        $this->actingAs($this->makeAdmin())
            ->post('/admin/admins', $this->payload())
            ->assertSessionHasNoErrors()->assertSessionHas('success');

        $new = User::where('email', 'newadmin@example.com')->firstOrFail();
        $this->assertSame('admin', $new->role);
        $this->assertFalse((bool) $new->is_blocked);
        $this->assertNotNull($new->email_verified_at);
        $this->assertTrue(\Hash::check('Secret@123', $new->password));

        auth()->logout();
        $this->actingAs($new)->get('/admin')->assertOk();
    }

    public function test_create_validation(): void
    {
        $existing = $this->makeUser('taken@example.com');
        $this->actingAs($this->makeAdmin());

        $this->post('/admin/admins', $this->payload(['email' => 'taken@example.com']))->assertSessionHasErrors('email');
        $this->post('/admin/admins', $this->payload(['password' => 'short', 'password_confirmation' => 'short']))->assertSessionHasErrors('password');
        $this->post('/admin/admins', $this->payload(['password_confirmation' => 'different1']))->assertSessionHasErrors('password');
        $this->post('/admin/admins', $this->payload(['name' => '']))->assertSessionHasErrors('name');
        $this->post('/admin/admins', $this->payload(['email' => 'nope']))->assertSessionHasErrors('email');
        $this->assertSame(2, User::count()); // only the admin and $existing
        $this->assertSame('customer', $existing->fresh()->role);
    }

    public function test_promote_existing_customer(): void
    {
        $customer = $this->makeUser('promoteme@example.com');
        $this->actingAs($this->makeAdmin());

        $this->post('/admin/admins/promote', ['promote_email' => ' PromoteMe@Example.com '])->assertSessionHas('success');
        $this->assertSame('admin', $customer->fresh()->role);

        $this->post('/admin/admins/promote', ['promote_email' => 'promoteme@example.com'])->assertSessionHasErrors('promote_email');
        $this->post('/admin/admins/promote', ['promote_email' => 'nobody@example.com'])->assertSessionHasErrors('promote_email');

        $blocked = $this->makeUser('blocked@example.com');
        $blocked->forceFill(['is_blocked' => true])->save();
        $this->post('/admin/admins/promote', ['promote_email' => 'blocked@example.com'])->assertSessionHasErrors('promote_email');
        $this->assertSame('customer', $blocked->fresh()->role);
    }

    public function test_remove_admin_access_and_safety_rules(): void
    {
        $me = $this->makeAdmin();
        $other = $this->makeUser('other-admin@example.com');
        $other->forceFill(['role' => 'admin'])->save();
        $customer = $this->makeUser('plain@example.com');
        $this->actingAs($me);

        // cannot remove yourself
        $this->delete("/admin/admins/{$me->id}")->assertSessionHas('error');
        $this->assertSame('admin', $me->fresh()->role);

        // a customer is not an admin row
        $this->delete("/admin/admins/{$customer->id}")->assertNotFound();

        // removes the other admin; the account stays
        $this->delete("/admin/admins/{$other->id}")->assertSessionHas('success');
        $this->assertSame('customer', $other->fresh()->role);
        $this->assertSame(0, $other->orders()->count());

        // the removed person is locked out immediately
        auth()->logout();
        $this->actingAs($other->fresh())->get('/admin')->assertForbidden();
    }

    public function test_one_admin_always_remains(): void
    {
        $a = $this->makeAdmin();
        $b = $this->makeUser('b-admin@example.com');
        $b->forceFill(['role' => 'admin'])->save();

        $this->actingAs($a)->delete("/admin/admins/{$b->id}")->assertSessionHas('success');
        $this->assertSame(1, User::where('role', 'admin')->count());
    }

    public function test_shop_admin_command_creates_an_admin_and_can_replace_the_others(): void
    {
        $old = User::create(['name' => 'Old', 'email' => 'old@example.com', 'password' => 'Secret@123']);
        $old->forceFill(['role' => 'admin'])->save();

        $this->artisan('shop:admin', ['email' => ' Owner@Example.com ', '--password' => 'Vireluxebd', '--replace' => true])->assertSuccessful();

        $owner = User::where('email', 'owner@example.com')->firstOrFail();
        $this->assertTrue($owner->isAdmin());
        $this->assertDatabaseMissing('users', ['email' => 'old@example.com']);
        $this->assertSame(1, User::where('role', 'admin')->count());

        $this->post('/admin/login', ['email' => 'owner@example.com', 'password' => 'Vireluxebd'])->assertRedirect('/admin');

        $this->artisan('shop:admin', ['email' => 'x@example.com', '--password' => 'short'])->assertFailed();
    }

    public function test_the_last_admin_can_never_be_removed(): void
    {
        $only = User::create(['name' => 'Only', 'email' => 'only@example.com', 'password' => 'Secret@123']);
        $only->forceFill(['role' => 'admin'])->save();

        $this->actingAs($only)->delete("/admin/admins/{$only->id}")->assertSessionHas('error');
        $this->assertTrue($only->fresh()->isAdmin());

        $second = User::create(['name' => 'Second', 'email' => 'second@example.com', 'password' => 'Secret@123']);
        $second->forceFill(['role' => 'admin'])->save();

        // One admin can remove the other, but then the survivor is protected.
        $this->actingAs($only)->delete("/admin/admins/{$second->id}")->assertSessionHas('success');
        $this->actingAs($only)->delete("/admin/admins/{$only->id}")->assertSessionHas('error');
        $this->assertSame(1, User::where('role', 'admin')->count());
    }
}
