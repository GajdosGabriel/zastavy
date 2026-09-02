<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Funkcia/pozícia kontaktu u zákazníka — vyhľadáva sa v zozname používateľov.
            $table->string('position', 120)->nullable()->after('postfix');
            // Jazyk notifikácií; null = jazyk aplikácie.
            $table->string('locale', 5)->nullable()->after('phone');
            // Interná poznámka, len pre správu účtov (neposiela sa používateľovi).
            $table->text('note')->nullable()->after('locale');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['position', 'locale', 'note']);
        });
    }
};
