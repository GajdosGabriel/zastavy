<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Konverzácia k cenovej ponuke a odmietnutie zákazníkom. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_quote_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sales_quote_id')->constrained()->cascadeOnDelete();
            $t->string('direction', 20);
            $t->string('kind', 30)->default('message');
            $t->string('author', 150);
            $t->unsignedBigInteger('user_id')->nullable();
            $t->text('body');
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
            $t->index(['direction', 'read_at']);
        });
        Schema::table('sales_quotes', function (Blueprint $t) {
            $t->timestamp('rejected_at')->nullable()->after('accepted_by');
            $t->text('rejection_reason')->nullable()->after('rejected_at');
        });
    }

    public function down(): void
    {
        Schema::table('sales_quotes', fn (Blueprint $t) => $t->dropColumn(['rejected_at', 'rejection_reason']));
        Schema::dropIfExists('sales_quote_messages');
    }
};
