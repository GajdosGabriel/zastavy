<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Väzba správy na správu v Accounte (e-mail prijatý alebo odoslaný z Inboxu).
 * Chráni pred duplicitou, keď Account webhook doručí druhýkrát.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_messages', function (Blueprint $table) {
            $table->uuid('account_message_id')->nullable()->after('read_at')->index();
        });
    }

    public function down(): void
    {
        Schema::table('order_messages', function (Blueprint $table) {
            $table->dropIndex(['account_message_id']);
            $table->dropColumn('account_message_id');
        });
    }
};
