<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\LogErrorsFound;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LogErrorsReportTest extends TestCase
{
    use RefreshDatabase;

    private string $log;

    private string $state;

    protected function setUp(): void
    {
        parent::setUp();

        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'log-report-'.uniqid();
        mkdir($dir);
        $this->log = $dir.DIRECTORY_SEPARATOR.'laravel.log';
        $this->state = $dir.DIRECTORY_SEPARATOR.'state.json';
        file_put_contents($this->log, $this->record('ERROR', 'Stará chyba spred nasadenia'));

        config([
            'logging.report.path' => $this->log,
            'logging.report.state' => $this->state,
            'logging.report.emails' => [],
        ]);

        Notification::fake();
    }

    private function record(string $level, string $message, string $class = 'Error', string $env = 'production'): string
    {
        $path = str_replace('\\', '/', base_path()).'/app/Http/Controllers/Api/OrderController.php';
        $class = str_replace('\\', '\\\\', $class);

        return "[2026-10-23 14:05:01] {$env}.{$level}: {$message} {\"exception\":\"[object] ({$class}(code: 0): {$message} at {$path}:42)\n"
            ."[stacktrace]\n#0 {$path}(42): foo()\n#1 {main}\n\"}\n";
    }

    private function append(string $text): void
    {
        file_put_contents($this->log, $text, FILE_APPEND);
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('super-admin', 'web'));

        return $user;
    }

    public function test_first_run_skips_old_history(): void
    {
        $this->superAdmin();

        $this->artisan('logs:report-errors')->assertSuccessful();

        Notification::assertNothingSent();
        $this->assertFileExists($this->state);
    }

    public function test_fixable_errors_arrive_in_one_mail_noise_does_not(): void
    {
        $admin = $this->superAdmin();
        $this->artisan('logs:report-errors');

        $this->append($this->record('ERROR', 'Unknown named parameter $ordersOnly'));
        $this->append($this->record('ERROR', 'Unknown named parameter $ordersOnly'));
        $this->append($this->record('ERROR', "SQLSTATE[42S02]: Base table or view not found: 1146 Table 'zastavy.order_messages' doesn't exist", 'Illuminate\\Database\\QueryException'));
        $this->append("[2026-10-23 14:05:02] production.ERROR: operations.unhealthy {\"checks\":{\"worker\":\"failed\"}} \n");
        $this->append($this->record('WARNING', 'Pomalá odpoveď registra'));
        $this->append($this->record('ERROR', 'cURL error 7: Failed to connect to registeruz.sk', 'Illuminate\\Http\\Client\\ConnectionException'));
        $this->append($this->record('ERROR', 'HTTP request returned status code 503:', 'Illuminate\\Http\\Client\\RequestException'));
        $this->append($this->record('ERROR', 'Expected response code "250" but got code "550", with message "550 5.1.1 User unknown".', 'Symfony\\Component\\Mailer\\Exception\\UnexpectedResponseException'));
        $this->append($this->record('ERROR', 'Table checkout_submissions doesn\'t exist', 'Illuminate\\Database\\QueryException', 'testing'));

        $this->artisan('logs:report-errors')->assertSuccessful();

        Notification::assertSentTo($admin, LogErrorsFound::class, function (LogErrorsFound $n) {
            $this->assertCount(3, $n->groups);
            $this->assertSame(2, $n->groups[0]['count']);
            $this->assertSame('Unknown named parameter $ordersOnly', $n->groups[0]['message']);
            $this->assertSame('app/Http/Controllers/Api/OrderController.php:42', $n->groups[0]['location']);
            $this->assertSame('Illuminate\\Database\\QueryException', $n->groups[1]['class']);
            $this->assertStringContainsString('"worker":"failed"', $n->groups[2]['message']);

            return true;
        });
        Notification::assertSentTimes(LogErrorsFound::class, 1);

        // Bez nových záznamov sa nič neposiela znova.
        $this->artisan('logs:report-errors')->assertSuccessful();
        Notification::assertSentTimes(LogErrorsFound::class, 1);
    }

    public function test_only_noise_sends_nothing(): void
    {
        $this->superAdmin();
        $this->artisan('logs:report-errors');

        $this->append($this->record('ERROR', 'cURL error 28: Resolving timed out', 'Illuminate\\Http\\Client\\ConnectionException'));

        $this->artisan('logs:report-errors')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_truncated_log_is_read_from_start(): void
    {
        $this->superAdmin();
        $this->append(str_repeat("[2026-10-23 14:00:00] production.INFO: výplň\n", 50));
        $this->artisan('logs:report-errors');

        file_put_contents($this->log, $this->record('ERROR', 'Call to undefined method Order::foo()'));

        $this->artisan('logs:report-errors')->assertSuccessful();

        Notification::assertSentTimes(LogErrorsFound::class, 1);
    }

    public function test_configured_address_wins(): void
    {
        config(['logging.report.emails' => ['dev@example.com']]);
        $this->artisan('logs:report-errors');

        $this->append($this->record('ERROR', 'Array to string conversion'));
        $this->artisan('logs:report-errors')->assertSuccessful();

        Notification::assertSentTo(new AnonymousNotifiable, LogErrorsFound::class,
            fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === ['dev@example.com']);
    }

    public function test_mail_renders(): void
    {
        $html = (string) (new LogErrorsFound([[
            'message' => 'Undefined property: stdClass::$ico_number <b>', 'class' => 'ErrorException', 'location' => 'app/X.php:1',
            'count' => 3, 'first' => '2026-10-23 14:00:00', 'last' => '2026-10-23 14:05:01',
        ]]))->toMail($this->superAdmin())->render();

        $this->assertStringContainsString('stdClass::$ico_number &lt;b&gt;', $html);
        $this->assertStringContainsString('3× · naposledy 2026-10-23 14:05:01', $html);
        $this->assertStringContainsString('ErrorException · app/X.php:1', $html);
    }
}
