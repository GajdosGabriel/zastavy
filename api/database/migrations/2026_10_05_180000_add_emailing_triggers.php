<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mailing_campaigns', function (Blueprint $t) {
            $t->string('trigger_event')->nullable();
            $t->unsignedInteger('delay_hours')->default(24);
        });
        Schema::table('mailing_deliveries', function (Blueprint $t) {
            // Zero identifies a broadcast; order IDs identify individual events.
            $t->unsignedBigInteger('event_order_id')->default(0);
            $t->timestamp('due_at')->nullable()->index();
            $t->unique(['campaign_id', 'contact_id', 'event_order_id'], 'mailing_delivery_event_unique');
        });
        Schema::table('mailing_deliveries', function (Blueprint $t) {
            $t->dropUnique(['campaign_id', 'contact_id']);
        });
    }

    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::table('mailing_deliveries')->where('event_order_id', '>', 0)->exists()
            || \Illuminate\Support\Facades\DB::table('mailing_campaigns')->whereNotNull('trigger_event')->exists()) {
            throw new RuntimeException('Archive trigger campaigns and deliveries before reverting this migration.');
        }
        Schema::table('mailing_deliveries', fn (Blueprint $t) => $t->unique(['campaign_id', 'contact_id']));
        Schema::table('mailing_deliveries', function (Blueprint $t) {
            $t->dropUnique('mailing_delivery_event_unique');
            $t->dropIndex(['due_at']);
            $t->dropColumn(['event_order_id', 'due_at']);
        });
        Schema::table('mailing_campaigns', fn (Blueprint $t) => $t->dropColumn(['trigger_event', 'delay_hours']));
    }
};
