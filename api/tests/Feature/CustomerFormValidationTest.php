<?php

namespace Tests\Feature;

use App\Enums\ModelStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CustomerFormValidationTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsSuperAdmin(): void
    {
        Role::findOrCreate('super-admin', 'web');
        $staff = User::factory()->create(['status' => ModelStatus::Active->value]);
        $staff->assignRole('super-admin');
        Sanctum::actingAs($staff);
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'company' => 'Obec Testovo',
            'email' => 'obec@example.com',
            'postcode' => '811 01',
            'city' => 'Bratislava',
        ], $override);
    }

    public function test_missing_city_is_validation_error_not_sql_error(): void
    {
        $this->actingAsSuperAdmin();

        $this->postJson('/api/customers', $this->payload(['city' => null]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('city');
    }

    public function test_garbage_ico_and_phone_are_rejected(): void
    {
        $this->actingAsSuperAdmin();

        $this->postJson('/api/customers', $this->payload(['ico' => 'abc', 'phone' => 'abc']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ico', 'phone']);
    }

    public function test_valid_customer_is_created_without_street(): void
    {
        $this->actingAsSuperAdmin();

        $this->postJson('/api/customers', $this->payload(['phone' => '0905 123 456']))
            ->assertSuccessful();
    }
}
