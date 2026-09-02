<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Prílohy nahrané zákazníkom (podklady k objednávke). Polymorfné, aby sa
     * dali vešať aj na iné modely než Order. Obsah je vždy súkromný — sťahuje
     * sa cez API, nie priamym odkazom do bucketu.
     */
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->morphs('attachable');
            $table->string('disk', 32);
            $table->string('path', 255);
            $table->string('name', 255);
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
