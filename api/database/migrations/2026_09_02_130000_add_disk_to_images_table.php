<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Disk sa ukladá ku každému obrázku zvlášť, aby po prepnutí na S3 ostali
     * staré lokálne súbory dostupné (a dali sa presúvať po dávkach).
     */
    public function up(): void
    {
        Schema::table('images', function (Blueprint $table) {
            $table->string('disk', 32)->nullable()->after('path');
        });

        // Existujúce záznamy ležia na lokálnom 'public' disku.
        \Illuminate\Support\Facades\DB::table('images')->whereNull('disk')->update(['disk' => 'public']);
    }

    public function down(): void
    {
        Schema::table('images', function (Blueprint $table) {
            $table->dropColumn('disk');
        });
    }
};
