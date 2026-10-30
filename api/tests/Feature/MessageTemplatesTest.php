<?php

namespace Tests\Feature;

use App\Models\MessageTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MessageTemplatesTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): void
    {
        $u = User::factory()->create();
        $u->assignRole('super-admin');
        Sanctum::actingAs($u);
    }

    public function test_migration_prepares_the_artwork_template(): void
    {
        $this->staff();
        $this->getJson('/api/message-templates')->assertOk()->assertJsonPath('data.0.name', 'Grafický návrh obecnej zástavy');
    }

    public function test_staff_manages_templates_in_order(): void
    {
        $this->staff();
        $id = $this->postJson('/api/message-templates', ['name' => 'Prvá', 'body' => "Dobrý deň,\nďakujeme.", 'sort_order' => 0])
            ->assertCreated()->assertJsonPath('data.body', "Dobrý deň,\nďakujeme.")->json('data.id');
        $this->getJson('/api/message-templates')->assertJsonPath('data.0.id', $id);

        $this->putJson('/api/message-templates/'.$id, ['name' => 'Nová', 'body' => 'Text'])->assertOk()->assertJsonPath('data.name', 'Nová')->assertJsonPath('data.sort_order', 99);

        $this->deleteJson('/api/message-templates/'.$id)->assertNoContent();
        $this->assertNull(MessageTemplate::find($id));
    }

    public function test_template_requires_name_and_body(): void
    {
        $this->staff();
        $this->postJson('/api/message-templates', ['name' => '', 'body' => ''])->assertUnprocessable()->assertJsonValidationErrors(['name', 'body']);
    }

    public function test_customers_and_guests_have_no_access(): void
    {
        $this->getJson('/api/message-templates')->assertUnauthorized();
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/message-templates')->assertForbidden();
        $this->postJson('/api/message-templates', ['name' => 'X', 'body' => 'Y'])->assertForbidden();
    }
}
