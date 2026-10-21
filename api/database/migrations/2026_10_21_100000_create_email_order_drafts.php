<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Objednávky a dopyty z e-mailu: nový mail na obchod@ mimo vlákna objednávky
 * (Account webhook message.unassigned). AI z neho pripraví návrh; úplný
 * návrh môže vzniknúť ako objednávka sám, neúplný doplní obsluha v košíku.
 * Dopyt na cenovú ponuku sa založí rovno ako ponuka v stave „dopyt“.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_order_drafts', function (Blueprint $table) {
            $table->id();
            $table->uuid('account_message_id')->unique();
            $table->uuid('account_conversation_id')->nullable()->index();
            $table->string('from_email', 150)->nullable();
            $table->string('from_name', 150)->nullable();
            $table->string('subject', 255)->nullable();
            $table->longText('body')->nullable();
            $table->timestamp('received_at')->nullable();
            // pending → draft | created | not_order | failed; obsluha: dismissed
            $table->string('status', 20)->default('pending')->index();
            $table->json('preview')->nullable();
            // Prečo návrh nevznikol ako objednávka automaticky
            $table->json('reasons')->nullable();
            $table->text('error')->nullable();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            // Dopyt na cenovú ponuku vznikne vždy hneď (ako dopyt z webu)
            $table->foreignId('sales_quote_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('auto_created')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_order_drafts');
    }
};
