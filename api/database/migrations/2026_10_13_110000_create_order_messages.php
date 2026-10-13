<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            // staff = obsluha, customer = zákazník cez súkromný odkaz.
            $t->string('direction', 10);
            // message | artwork_sent | changes_requested | approved
            $t->string('kind', 20)->default('message');
            $t->string('author', 150);
            $t->unsignedBigInteger('user_id')->nullable();
            $t->text('body')->nullable();
            $t->foreignId('artwork_version_id')->nullable()->constrained()->nullOnDelete();
            // Kedy správu zákazníka videla obsluha.
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
            $t->index(['order_id', 'id']);
            $t->index(['direction', 'read_at']);
        });
        Schema::table('order_productions', function (Blueprint $t) {
            // Šifrovaná kópia tokenu: ďalšie e-maily k objednávke používajú ten istý odkaz.
            $t->text('token')->nullable();
            // false = záznam vznikol len kvôli konverzácii, do plánu výroby nepatrí.
            $t->boolean('planned')->default(true);
        });

        // Doterajšie pripomienky a schválenia grafiky sa stanú súčasťou konverzácie.
        $now = now();
        $versions = DB::table('artwork_versions')->get(['id', 'order_id', 'approved_at', 'approved_by'])->keyBy('id');
        foreach (DB::table('artwork_comments')->orderBy('id')->get() as $c) {
            if (! isset($versions[$c->artwork_version_id])) {
                continue;
            }
            DB::table('order_messages')->insert(['order_id' => $versions[$c->artwork_version_id]->order_id, 'direction' => 'customer', 'kind' => 'changes_requested',
                'author' => mb_substr($c->author, 0, 150), 'body' => $c->body, 'artwork_version_id' => $c->artwork_version_id, 'read_at' => $now,
                'created_at' => $c->created_at, 'updated_at' => $c->updated_at]);
        }
        foreach ($versions->whereNotNull('approved_at') as $v) {
            DB::table('order_messages')->insert(['order_id' => $v->order_id, 'direction' => 'customer', 'kind' => 'approved',
                'author' => mb_substr((string) $v->approved_by, 0, 150), 'artwork_version_id' => $v->id, 'read_at' => $now,
                'created_at' => $v->approved_at, 'updated_at' => $v->approved_at]);
        }
    }

    public function down(): void
    {
        Schema::table('order_productions', fn (Blueprint $t) => $t->dropColumn(['token', 'planned']));
        Schema::dropIfExists('order_messages');
    }
};
