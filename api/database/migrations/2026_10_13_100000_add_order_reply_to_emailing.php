<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mailing_campaigns', function (Blueprint $t) {
            // Email odíde ako odpoveď vo vlákne objednávky, nie ako ponuka v šablóne.
            $t->boolean('reply_to_order')->default(false);
            // Pod text odpovede pripojiť citovaný systémový email udalosti.
            $t->boolean('quote_event')->default(true);
        });
        Schema::table('mailing_deliveries', function (Blueprint $t) {
            // Odpoveď k objednávke ide zákazníkovi aj bez záznamu v adresári príjemcov.
            $t->unsignedBigInteger('contact_id')->nullable()->change();
            $t->string('email')->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('mailing_deliveries')->whereNull('contact_id')->exists()) {
            throw new RuntimeException('Archive order-reply deliveries before reverting this migration.');
        }
        Schema::table('mailing_deliveries', function (Blueprint $t) {
            $t->unsignedBigInteger('contact_id')->nullable(false)->change();
            $t->dropColumn('email');
        });
        Schema::table('mailing_campaigns', fn (Blueprint $t) => $t->dropColumn(['reply_to_order', 'quote_event']));
    }
};
