<?php

namespace Tests\Feature;

use App\Enums\CustomerType;
use App\Enums\ModelStatus;
use App\Models\Customer;
use App\Models\User;
use App\Services\Customers\CustomerTypeClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CustomerTypeTest extends TestCase
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

    public function test_classifier_recognizes_all_four_types(): void
    {
        $classifier = new CustomerTypeClassifier();

        $cases = [
            ['Obec Raslavice', '14287315', 'Starosta', CustomerType::Municipality],
            ['OBEC JAMNÍK', '00329215', null, CustomerType::Municipality],
            ['Mestský úrad Senec', '00305065', null, CustomerType::Municipality],
            ['MČ Košice - Pereš', '00690953', null, CustomerType::Municipality],
            // Názov nepovie nič, ale IČO je z bloku obcí.
            ['Ratkovské Bystré', '00328715', null, CustomerType::Municipality],
            ['ZŠ s MŠ Plaveč', '37872907', null, CustomerType::School],
            ['ZŠsMŠ Tekovské Nemce', '37865153', null, CustomerType::School],
            ['Gymnázium', '00161021', null, CustomerType::School],
            ['SOŠOaS', '00351873', null, CustomerType::School],
            // Škôlka fakturovaná cez obec je stále škola.
            ['Obec Kunerad Materská škola Kunerad 213', '00648892', null, CustomerType::School],
            ['Správa majetku a služieb, s.r.o.', '46130101', null, CustomerType::Company],
            ['Daycare International sro', '46708502', null, CustomerType::Company],
            ['Farský úrad', null, 'Ján Novák', CustomerType::Company],
            // Živnostník má IČO — je to firma, nie súkromná osoba.
            ['Ján Novák', '47647221', 'Novák', CustomerType::Company],
            ['Ján Novák', null, 'Mgr. Ján Novák', CustomerType::Person],
            ['Novák Ján', '', 'Ján Novák', CustomerType::Person],
            ['Súkromná osoba', null, 'Ján Novák', CustomerType::Person],
            // Kontakt si do mena skopíroval názov organizácie.
            ['Hasičský zbor Lúčka', null, 'Hasičský zbor Lúčka', CustomerType::Company],
        ];

        foreach ($cases as [$company, $ico, $contact, $expected]) {
            $this->assertSame($expected, $classifier->classify($company, $ico, $contact), $company);
        }
    }

    public function test_new_customer_gets_type_automatically(): void
    {
        $this->actingAsSuperAdmin();

        $this->postJson('/api/customers', $this->payload())
            ->assertSuccessful()
            ->assertJsonPath('data.type', 'municipality')
            ->assertJsonPath('data.type_label', CustomerType::Municipality->label());

        $this->postJson('/api/customers', $this->payload(['company' => 'Ján Novák', 'name' => 'Ján Novák', 'email' => 'jan@example.com']))
            ->assertSuccessful()
            ->assertJsonPath('data.type', 'person');
    }

    public function test_type_chosen_in_form_wins_and_survives_update_without_it(): void
    {
        $this->actingAsSuperAdmin();

        $id = $this->postJson('/api/customers', $this->payload(['type' => 'company']))
            ->assertSuccessful()
            ->assertJsonPath('data.type', 'company')
            ->json('data.id');

        $update = $this->payload(['name' => 'Ján Novák', 'status' => ModelStatus::Active->value]);

        $this->putJson('/api/customers/' . $id, $update)
            ->assertSuccessful()
            ->assertJsonPath('data.type', 'company');

        $this->putJson('/api/customers/' . $id, $update + ['type' => 'school'])
            ->assertSuccessful()
            ->assertJsonPath('data.type', 'school');

        $this->putJson('/api/customers/' . $id, $update + ['type' => 'nezmysel'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('type');
    }

    public function test_customers_can_be_filtered_by_type(): void
    {
        $this->actingAsSuperAdmin();

        $this->postJson('/api/customers', $this->payload())->assertSuccessful();
        $this->postJson('/api/customers', $this->payload(['company' => 'Základná škola Testovo', 'email' => 'zs@example.com']))->assertSuccessful();

        $this->getJson('/api/customers?type=school')
            ->assertSuccessful()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.company', 'Základná škola Testovo');
    }

    /** Zákazník založený mimo CustomerService (import, tinker) typ dostane tiež. */
    public function test_customer_created_directly_is_classified(): void
    {
        $customer = Customer::create([
            'company' => 'Gymnázium Testovo',
            'postcode' => '81101',
            'city' => 'Bratislava',
        ]);

        $this->assertSame(CustomerType::School, $customer->type);
    }
}
