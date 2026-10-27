<?php

namespace Tests\Feature;

use App\Enums\ModelStatus;
use App\Models\User;
use App\Notifications\StaffAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StaffAlertsTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('super-admin', 'web');
        $this->staff = User::factory()->create(['status' => ModelStatus::Active->value]);
        $this->staff->assignRole('super-admin');
        Sanctum::actingAs($this->staff);
    }

    private function alert(User $user, string $title = 'Nová objednávka T1'): string
    {
        $before = $user->notifications()->pluck('id');
        $user->notify(new StaffAlert('order', $title, 'Firma s.r.o.', '/objednavky/1/show'));
        $this->travel(1)->seconds();

        return $user->notifications()->whereNotIn('id', $before)->firstOrFail()->id;
    }

    public function test_clicked_alert_stays_in_history_as_read(): void
    {
        $id = $this->alert($this->staff);
        $this->alert($this->staff, 'Druhá');

        $this->getJson('/api/dashboard/notifications')->assertOk()->assertJsonPath('data.alerts_unread', 2);
        $this->postJson('/api/dashboard/alerts/'.$id.'/read')->assertNoContent();

        $this->getJson('/api/dashboard/alerts')->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('unread', 1)
            ->assertJsonPath('data.1.title', 'Nová objednávka T1')
            ->assertJsonPath('data.1.url', '/objednavky/1/show');
        $this->assertNotNull(DB::table('notifications')->where('id', $id)->value('read_at'));

        $this->postJson('/api/dashboard/alerts/read-all')->assertNoContent();
        $this->getJson('/api/dashboard/alerts')->assertJsonPath('unread', 0);
    }

    public function test_user_sees_and_marks_only_own_alerts(): void
    {
        $other = User::factory()->create(['status' => ModelStatus::Active->value]);
        $other->assignRole('super-admin');
        $id = $this->alert($other);

        $this->getJson('/api/dashboard/alerts')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson('/api/dashboard/alerts/'.$id.'/read')->assertNotFound();
        $this->assertNull(DB::table('notifications')->where('id', $id)->value('read_at'));
    }

    public function test_prune_removes_read_after_30_and_unread_after_90_days(): void
    {
        $ids = collect([[40, true], [20, true], [60, false], [100, false]])->map(function ($row) {
            $id = $this->alert($this->staff);
            DB::table('notifications')->where('id', $id)->update([
                'created_at' => now()->subDays($row[0]),
                'read_at' => $row[1] ? now()->subDays($row[0]) : null,
            ]);

            return $id;
        });

        $this->artisan('notifications:prune')->assertSuccessful();

        $this->assertSame([$ids[1], $ids[2]], DB::table('notifications')->orderBy('created_at', 'desc')->pluck('id')->all());
    }
}
