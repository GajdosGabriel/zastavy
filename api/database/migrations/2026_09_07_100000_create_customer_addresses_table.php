<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adresár doručovacích adries zákazníka.
 *
 * Fakturačná adresa ostáva na `customers` — je jedna a je to sídlo organizácie.
 * Tovar však chodí kamkoľvek: obec objedná zástavy na kultúrny dom, firma na
 * pobočku, škola na inú budovu. Tie adresy sa opakujú, tak nech si ich zákazník
 * (a obsluha) nemusí písať znova.
 *
 * Objednávka si adresu odfotí do vlastných stĺpcov (viď migráciu na `orders`),
 * takže neskoršia úprava riadku v adresári nezmení, kam sa už raz posielalo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->string('label', 100)->nullable();
            $table->string('company', 200)->nullable();
            $table->string('name', 150)->nullable();
            $table->string('street', 250);
            $table->string('postcode', 20);
            $table->string('city', 100);
            $table->string('country', 2)->default('SK');
            $table->string('phone', 40)->nullable();
            $table->string('note', 255)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->index(['customer_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
    }
};
