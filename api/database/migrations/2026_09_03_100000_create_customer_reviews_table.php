<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Posudok údajov jedného zákazníka — „ako je na tom tento riadok".
 *
 * Jeden riadok na zákazníka, prepisuje sa na mieste. História posudkov nikoho
 * nezaujíma, aktuálny stav áno; čo sa naozaj zmenilo v údajoch, je vidieť
 * v `applied` a to je zároveň jediná stopa po zásahu automatu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_reviews', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_id')->unique()->constrained()->cascadeOnDelete();

            // Odtlačok kontrolovaných polí. Ten istý odtlačok = ten istý
            // posudok, netreba platiť za druhé volanie.
            $table->string('fingerprint', 64)->nullable();

            // 0-100. Pod 60 riadok potrebuje ľudský zásah.
            $table->unsignedTinyInteger('score')->nullable();
            $table->string('summary', 500)->nullable();

            // Nájdené výhrady: [{field, severity, source, message, current, suggested, fix}]
            $table->json('issues')->nullable();

            // Čo automat sám opravil: [{field, from, to, fix}]. Toto je audit —
            // bez neho by sa po zmene fakturačného údaja nedalo zistiť, kto ju
            // spravil a z čoho vychádzal.
            $table->json('applied')->nullable();

            // Kedy sa kontrola má spustiť. NULL = nie je splatná.
            $table->timestamp('due_at')->nullable()->index();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('notified_at')->nullable();

            // Kedy a kto nálezy odbavil. Odbavený posudok sa v zozname
            // nesvieti, ale ostáva — nová zmena údajov ho oživí.
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('last_error', 255)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_reviews');
    }
};
