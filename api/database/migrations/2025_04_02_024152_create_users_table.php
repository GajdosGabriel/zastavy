<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('status', 32)->default('active')->index();
            $table->string('name', 255)->nullable();
            $table->string('firstName', 255);
            $table->string('lastName', 255);
            $table->string('slug', 255);
            $table->string('username', 100)->nullable();
            $table->string('email', 255);
            $table->string('phone', 40)->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password', 255);
            $table->string('remember_token', 100)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
