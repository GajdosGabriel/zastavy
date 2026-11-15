<?php

namespace Tests\Feature;

use App\Enums\ModelStatus;
use App\Models\Customer;
use App\Models\User;
use App\Services\CustomerService;
use App\Support\Csv;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuditHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        foreach (['super-admin', 'admin'] as $role) {
            Role::findOrCreate($role, 'web');
        }
        $admin = User::factory()->create(['status' => ModelStatus::Active->value]);
        $admin->assignRole('admin');
        foreach (['users.viewAny', 'users.view', 'users.update'] as $permission) {
            $admin->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        Sanctum::actingAs($admin);

        return $admin;
    }

    private function payload(User $user, array $extra = []): array
    {
        return $extra + [
            'firstName' => $user->firstName ?: 'Meno',
            'email' => $user->email,
            'status' => ModelStatus::Active->value,
        ];
    }

    public function test_admin_cannot_grant_himself_super_admin(): void
    {
        $admin = $this->admin();

        $this->putJson(route('users.update', $admin->id), $this->payload($admin, ['roles' => ['super-admin']]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('roles');

        $this->assertFalse($admin->fresh()->hasRole('super-admin'));
    }

    public function test_admin_cannot_view_or_edit_another_staff_account(): void
    {
        $this->admin();
        $owner = User::factory()->create(['status' => ModelStatus::Active->value, 'email' => 'owner@example.com']);
        $owner->assignRole('super-admin');

        $this->getJson(route('users.show', $owner->id))->assertForbidden();
        $this->putJson(route('users.update', $owner->id), $this->payload($owner, ['email' => 'attacker@example.com']))
            ->assertForbidden();

        $this->assertSame('owner@example.com', $owner->fresh()->email);
    }

    public function test_admin_can_still_edit_his_own_account(): void
    {
        $admin = $this->admin();

        $this->putJson(route('users.update', $admin->id), $this->payload($admin, ['phone' => '0905123456']))
            ->assertOk();

        $this->assertTrue($admin->fresh()->hasRole('admin'));
    }

    public function test_forgot_password_does_not_reveal_whether_account_exists(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'known@example.com', 'status' => ModelStatus::Active->value]);

        $known = $this->postJson(route('password.forgot'), ['email' => 'known@example.com'])->assertOk();
        $unknown = $this->postJson(route('password.forgot'), ['email' => 'unknown@example.com'])->assertOk();

        $this->assertSame($known->json('message'), $unknown->json('message'));
    }

    public function test_login_rejects_non_string_credentials_without_server_error(): void
    {
        $this->postJson(route('sanctum.login'), ['email' => ['a'], 'password' => ['b']])
            ->assertStatus(422);
    }

    public function test_array_search_filter_is_ignored(): void
    {
        Role::findOrCreate('super-admin', 'web');
        $staff = User::factory()->create(['status' => ModelStatus::Active->value]);
        $staff->assignRole('super-admin');
        Sanctum::actingAs($staff);

        $this->getJson(route('customers.index', ['bySearchInput' => ['x']]))->assertOk();
    }

    public function test_staff_lookup_finds_customer_by_unpadded_ico(): void
    {
        $customer = Customer::create([
            'slug' => 'firma', 'company' => 'Firma', 'ico' => '123456',
            'postcode' => '01001', 'city' => 'Žilina', 'status' => ModelStatus::Active->value,
        ]);
        $this->assertSame('00123456', $customer->fresh()->ico);

        Role::findOrCreate('super-admin', 'web');
        $staff = User::factory()->create(['status' => ModelStatus::Active->value]);
        $staff->assignRole('super-admin');

        [$found] = (new CustomerService)->handleCheckout([
            'company' => 'Firma', 'ico' => '123456', 'postcode' => '01001', 'city' => 'Žilina',
        ], $staff);

        $this->assertSame($customer->id, $found->id);
        $this->assertSame(1, Customer::count());
    }

    public function test_csv_cells_cannot_start_a_formula(): void
    {
        $this->assertSame("'=HYPERLINK(\"http://x\")", Csv::cell('=HYPERLINK("http://x")'));
        $this->assertSame("'@SUM(A1)", Csv::cell('@SUM(A1)'));
        $this->assertSame("'+1+cmd|' /C calc'!A0", Csv::cell("+1+cmd|' /C calc'!A0"));
        $this->assertSame('+421 905 123 456', Csv::cell('+421 905 123 456'));
        $this->assertSame('-12,50', Csv::cell('-12,50'));
        $this->assertSame('Obec Horná', Csv::cell('Obec Horná'));
        $this->assertSame('', Csv::cell(null));
    }
}
