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

    /**
     * Dĺžky kopírujú stĺpce v databáze — bez nich by dlhý text skončil SQL chybou.
     */
    public function test_too_long_values_are_validation_errors(): void
    {
        $this->actingAsSuperAdmin();

        $this->postJson('/api/customers', $this->payload([
            'company' => str_repeat('x', 201),
            'name' => str_repeat('x', 151),
            'note' => str_repeat('x', 256),
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['company', 'name', 'note']);
    }

    /**
     * Poznámku mení len formulár, ktorý ju pozná — úprava bez nej ju nesmie vymazať.
     */
    public function test_note_is_saved_and_survives_update_without_it(): void
    {
        $this->actingAsSuperAdmin();

        $id = $this->postJson('/api/customers', $this->payload(['note' => 'Fakturovať až po dodaní']))
            ->assertSuccessful()
            ->assertJsonPath('data.note', 'Fakturovať až po dodaní')
            ->json('data.id');

        $update = $this->payload(['name' => 'Ján Novák', 'status' => ModelStatus::Active->value]);

        $this->putJson('/api/customers/' . $id, $update)
            ->assertSuccessful()
            ->assertJsonPath('data.note', 'Fakturovať až po dodaní');

        $this->putJson('/api/customers/' . $id, $update + ['note' => 'Platí vopred'])
            ->assertSuccessful()
            ->assertJsonPath('data.note', 'Platí vopred');
    }

    public function test_address_rejects_garbage_postcode_and_phone(): void
    {
        $this->actingAsSuperAdmin();

        $id = $this->postJson('/api/customers', $this->payload())->assertSuccessful()->json('data.id');

        $address = ['street' => 'Hlavná 1', 'city' => 'Nitra', 'postcode' => '949 01'];

        $this->postJson("/api/customers/{$id}/addresses", ['postcode' => 'abc', 'phone' => 'abc'] + $address)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['postcode', 'phone']);

        $this->postJson("/api/customers/{$id}/addresses", $address + ['phone' => '0905 123 456'])
            ->assertSuccessful();
    }

    public function test_valid_customer_is_created_without_street(): void
    {
        $this->actingAsSuperAdmin();

        $this->postJson('/api/customers', $this->payload(['phone' => '0905 123 456']))
            ->assertSuccessful();
    }
}
