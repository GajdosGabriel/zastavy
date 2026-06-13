<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('images', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status', 32)->default('active')->index();
            $table->unsignedBigInteger('fileable_id')->nullable();
            $table->string('fileable_type', 255)->nullable();
            $table->string('name', 60);
            $table->string('path', 255)->nullable();
            $table->string('org_name', 255)->nullable();
            $table->string('mime', 100)->nullable();
            $table->unsignedInteger('size')->nullable();
            $table->unsignedInteger('heigh')->nullable();
            $table->unsignedInteger('with')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('images');
    }
};
