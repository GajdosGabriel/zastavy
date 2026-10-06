<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mailing_contacts', function (Blueprint $t) {
            // Adresa, na ktorú sa už neposiela (neexistuje, zrušená, opakovane plná schránka).
            // Oddelené od unsubscribed_at, aby sa dalo rozlíšiť odhlásenie od nedoručiteľnosti.
            $t->timestamp('bounced_at')->nullable()->index();
            $t->string('bounce_type', 20)->nullable();
            $t->string('bounce_reason')->nullable();
            $t->unsignedSmallInteger('soft_bounces')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('mailing_contacts', function (Blueprint $t) {
            $t->dropIndex(['bounced_at']);
            $t->dropColumn(['bounced_at', 'bounce_type', 'bounce_reason', 'soft_bounces']);
        });
    }
};
