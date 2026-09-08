<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Odtlačok doručovacej adresy na objednávke.
 *
 * Prázdne stĺpce znamenajú „doručiť na fakturačnú adresu" — tak sa objednávky
 * správali doteraz a existujúce riadky tým ostávajú nedotknuté.
 *
 * Adresa je odfotená, nie odkazovaná: `customer_address_id` je len stopa, odkiaľ
 * hodnoty prišli. Keď zákazník o rok prepíše adresu v adresári, staré objednávky
 * musia stále ukazovať, kam sa vtedy naozaj poslalo.
 *
 * `delivery_token` je tajomstvo za verejným odkazom „zmeniť adresu doručenia"
 * z potvrdzovacieho e-mailu. Je oddelené od `uuid`, ktoré otvára len čítanie —
 * preposlaný odkaz na detail objednávky tak nedáva právo prepísať adresu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('customer_address_id')->nullable()->after('user_id');

            $table->string('delivery_company', 200)->nullable();
            $table->string('delivery_name', 150)->nullable();
            $table->string('delivery_street', 250)->nullable();
            $table->string('delivery_postcode', 20)->nullable();
            $table->string('delivery_city', 100)->nullable();
            $table->string('delivery_country', 2)->nullable();
            $table->string('delivery_phone', 40)->nullable();
            $table->string('delivery_note', 255)->nullable();

            $table->string('delivery_token', 64)->nullable()->unique();
            $table->timestamp('delivery_token_expires_at')->nullable();
            $table->timestamp('delivery_changed_at')->nullable();
            $table->string('delivery_changed_by', 100)->nullable();

            $table->foreign('customer_address_id')->references('id')->on('customer_addresses')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['customer_address_id']);
            $table->dropUnique(['delivery_token']);
            $table->dropColumn([
                'customer_address_id',
                'delivery_company',
                'delivery_name',
                'delivery_street',
                'delivery_postcode',
                'delivery_city',
                'delivery_country',
                'delivery_phone',
                'delivery_note',
                'delivery_token',
                'delivery_token_expires_at',
                'delivery_changed_at',
                'delivery_changed_by',
            ]);
        });
    }
};
