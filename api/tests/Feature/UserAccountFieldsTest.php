<?php

namespace Tests\Feature;

use App\Enums\ModelStatus;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserAccountFieldsTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        Role::findOrCreate('super-admin', 'web');
        $staff = User::factory()->create(['status' => ModelStatus::Active->value]);
        $staff->assignRole('super-admin');
        Sanctum::actingAs($staff);

        return $staff;
    }

    public function test_store_saves_titles_status_and_active(): void
    {
        Notification::fake();
        $this->superAdmin();

        $customer = Customer::create([
            'name' => 'Firma', 'slug' => 'firma', 'company' => 'Firma',
            'postcode' => '01001', 'city' => 'Žilina', 'status' => ModelStatus::Active->value,
        ]);

        $response = $this->postJson(route('users.store'), [
            'prefix' => 'Ing.',
            'firstName' => 'Ján',
            'lastName' => 'Novák',
            'postfix' => 'PhD.',
            'position' => 'konateľ',
            'email' => 'jan.novak@example.com',
            'customer_id' => $customer->id,
            'locale' => 'cs',
            'note' => 'Volať len doobeda.',
            'status' => ModelStatus::Draft->value,
            'active' => false,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.prefix', 'Ing.');
        $response->assertJsonPath('data.postfix', 'PhD.');
        $response->assertJsonPath('data.fullName', 'Ing. Ján Novák, PhD.');
        $response->assertJsonPath('data.active', false);
        $response->assertJsonPath('data.status.value', 'draft');
        $response->assertJsonPath('data.position', 'konateľ');
        $response->assertJsonPath('data.locale', 'cs');
        $response->assertJsonPath('data.note', 'Volať len doobeda.');

        $user = User::where('email', 'jan.novak@example.com')->firstOrFail();
        $this->assertSame('Ing. Ján Novák, PhD.', $user->name);
        $this->assertSame('Ján Novák', $user->username);
        $this->assertFalse($user->isActive());
        $this->assertSame('cs', $user->preferredLocale());

        $update = $this->putJson(route('users.update', $user->id), [
            'prefix' => '',
            'firstName' => 'Ján',
            'lastName' => 'Novák',
            'postfix' => 'MBA',
            'username' => '',
            'email' => 'jan.novak@example.com',
            'status' => ModelStatus::Active->value,
            'active' => true,
        ]);

        $update->assertOk();
        $update->assertJsonPath('data.fullName', 'Ján Novák, MBA');
        $update->assertJsonPath('data.active', true);
        $this->assertTrue($user->refresh()->isActive());
    }

    public function test_locale_must_be_supported(): void
    {
        Notification::fake();
        $this->superAdmin();

        $customer = Customer::create([
            'name' => 'Firma3', 'slug' => 'firma3', 'company' => 'Firma3',
            'postcode' => '01001', 'city' => 'Žilina', 'status' => ModelStatus::Active->value,
        ]);

        $this->postJson(route('users.store'), [
            'firstName' => 'Peter',
            'email' => 'peter@example.com',
            'customer_id' => $customer->id,
            'locale' => 'de',
        ])->assertStatus(422)->assertJsonValidationErrors('locale');
    }

    public function test_non_super_admin_cannot_set_status(): void
    {
        Notification::fake();
        Role::findOrCreate('admin', 'web');
        $staff = User::factory()->create(['status' => ModelStatus::Active->value]);
        $staff->assignRole('admin');
        $staff->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate('users.update', 'web'));
        Sanctum::actingAs($staff);

        $customer = Customer::create([
            'name' => 'Firma2', 'slug' => 'firma2', 'company' => 'Firma2',
            'postcode' => '01001', 'city' => 'Žilina', 'status' => ModelStatus::Active->value,
        ]);
        \App\Models\Order::create([
            'customer_id' => $customer->id, 'user_id' => $staff->id,
            'serial_number' => 'T4242', 'status' => 'processing', 'isOpened' => 1,
        ]);

        $this->postJson(route('users.store'), [
            'firstName' => 'Eva',
            'email' => 'eva@example.com',
            'customer_id' => $customer->id,
            'status' => ModelStatus::Active->value,
        ])->assertStatus(422)->assertJsonValidationErrors('status');
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->create([
            'status' => ModelStatus::Active->value,
            'email' => 'off@example.com',
            'password' => bcrypt('secret-pass'),
            'active' => false,
        ]);

        $this->postJson(route('sanctum.login'), ['email' => $user->email, 'password' => 'secret-pass'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }
}
