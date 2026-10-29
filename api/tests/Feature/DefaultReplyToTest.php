<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

/**
 * Technické maily idú z noreply@ s Reply-To na obchod@; mail s vlastným
 * Reply-To (objednávky) si ho nechá.
 */
class DefaultReplyToTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'mail.default' => 'array',
            'mail.from' => ['address' => 'noreply@zastavy-vlajky.sk', 'name' => 'Zástavy'],
            'mail.default_reply_to' => ['address' => 'obchod@zastavy-vlajky.sk', 'name' => 'Zástavy'],
        ]);
    }

    private function lastSent(): Email
    {
        return Mail::mailer('array')->getSymfonyTransport()->messages()->last()->getOriginalMessage();
    }

    /** @return array<int, string> */
    private function replyTo(Email $email): array
    {
        return array_map(fn (Address $a) => $a->getAddress(), $email->getReplyTo());
    }

    public function test_password_reset_goes_from_noreply_with_reply_to_shop(): void
    {
        User::factory()->create(['email' => 'jana@example.test'])->notify(new ResetPassword('token'));

        $email = $this->lastSent();

        $this->assertSame('noreply@zastavy-vlajky.sk', $email->getFrom()[0]->getAddress());
        $this->assertSame(['obchod@zastavy-vlajky.sk'], $this->replyTo($email));
    }

    public function test_own_reply_to_is_kept_alone(): void
    {
        Mail::mailer('array')->raw('Objednávka', fn (Message $m) => $m->to('obchod@zastavy-vlajky.sk')
            ->subject('Nová objednávka')
            ->replyTo('zakaznik@example.test'));

        $this->assertSame(['zakaznik@example.test'], $this->replyTo($this->lastSent()));
    }

    public function test_empty_default_adds_nothing(): void
    {
        config(['mail.default_reply_to.address' => null]);

        Mail::mailer('array')->raw('Text', fn (Message $m) => $m->to('jana@example.test')->subject('Bez'));

        $this->assertSame([], $this->replyTo($this->lastSent()));
    }
}
