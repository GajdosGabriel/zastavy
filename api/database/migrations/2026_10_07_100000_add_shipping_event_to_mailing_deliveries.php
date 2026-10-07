<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mailing_deliveries', function (Blueprint $t) {
            // Nula = udalosť nie je viazaná na zásielku (objednávka ako celok).
            $t->unsignedBigInteger('event_shipping_id')->default(0);
            $t->unique(['campaign_id', 'contact_id', 'event_order_id', 'event_shipping_id'], 'mailing_delivery_event_shipping_unique');
        });
        Schema::table('mailing_deliveries', fn (Blueprint $t) => $t->dropUnique('mailing_delivery_event_unique'));
    }

    public function down(): void
    {
        if (DB::table('mailing_deliveries')->where('event_shipping_id', '>', 0)->exists()
            || DB::table('mailing_campaigns')->whereNotIn('trigger_event', ['order_created'])->exists()) {
            throw new RuntimeException('Archive shipping-based trigger campaigns and deliveries before reverting this migration.');
        }
        Schema::table('mailing_deliveries', fn (Blueprint $t) => $t->unique(['campaign_id', 'contact_id', 'event_order_id'], 'mailing_delivery_event_unique'));
        Schema::table('mailing_deliveries', function (Blueprint $t) {
            $t->dropUnique('mailing_delivery_event_shipping_unique');
            $t->dropColumn('event_shipping_id');
        });
    }
};
