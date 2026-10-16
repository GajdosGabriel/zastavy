<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Trvalá história e-mailov zákazníkovi k objednávke (detail objednávky).
 * Denník system_logs sa po 30 dňoch maže, preto má objednávka vlastnú kópiu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_emails', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            // Krátky názov notifikácie (OrderCreated, OrderExpedition…), null = kampaň/iný mail.
            $t->string('type', 64)->nullable();
            $t->string('subject', 255)->default('');
            $t->string('recipient', 191)->nullable();
            // sent | failed
            $t->string('status', 16);
            $t->string('error', 500)->nullable();
            $t->longText('body')->nullable();
            $t->timestamp('created_at')->nullable();
            $t->index(['order_id', 'created_at']);
        });

        $this->backfill();
    }

    /** Doterajšie e-maily z denníka (posledných 30 dní), aby história nezačínala prázdna. */
    private function backfill(): void
    {
        if (! Schema::hasTable('system_logs')) {
            return;
        }

        $staff = DB::table('users')->whereNotNull('email')->pluck('email')->map(fn ($e) => mb_strtolower($e))->flip();
        $hasBody = Schema::hasColumn('system_logs', 'body');

        DB::table('system_logs')->where('channel', 'mail')->whereIn('event', ['mail.sent', 'mail.failed'])
            ->orderBy('id')->chunk(500, function ($logs) use ($staff, $hasBody) {
                $rows = [];
                foreach ($logs as $log) {
                    $recipient = $log->recipient ? mb_strtolower($log->recipient) : null;
                    if ($recipient && $staff->has($recipient)) {
                        continue;
                    }
                    $context = json_decode($log->context ?? '[]', true) ?: [];
                    $orderId = $this->orderId((string) $log->message, (string) ($context['message_id'] ?? ''));
                    if (! $orderId) {
                        continue;
                    }
                    $class = $context['class'] ?? null;
                    $rows[] = [
                        'order_id' => $orderId,
                        'type' => $class ? class_basename($class) : null,
                        'subject' => mb_substr((string) $log->message, 0, 255),
                        'recipient' => $log->recipient,
                        'status' => $log->event === 'mail.sent' ? 'sent' : 'failed',
                        'error' => isset($context['error']) ? mb_substr((string) $context['error'], 0, 500) : null,
                        'body' => $hasBody ? $log->body : null,
                        'created_at' => $log->created_at,
                    ];
                }
                if ($rows) {
                    DB::table('order_emails')->insert($rows);
                }
            });
    }

    private function orderId(string $subject, string $messageId): ?int
    {
        if (preg_match('/^order-([0-9a-f-]{36})@/', $messageId, $m)) {
            return DB::table('orders')->where('uuid', $m[1])->value('id');
        }
        if (preg_match('/Objednávka č\. (.+?)(?: \||$)/u', $subject, $m)) {
            return DB::table('orders')->where('serial_number', $m[1])->value('id');
        }

        return null;
    }

    public function down(): void
    {
        Schema::dropIfExists('order_emails');
    }
};
