<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('company', 200)->nullable();
            $table->string('slug', 200);
            $table->string('phone', 40)->nullable();
            $table->string('email', 150);
            $table->string('username', 100)->nullable();
            $table->string('street', 250)->nullable();
            $table->string('postcode', 20);
            $table->string('city', 100);
            $table->string('ico', 10)->nullable();
            $table->string('dic', 200)->nullable();
            $table->string('ic_dic', 200)->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamp('last_login')->nullable();
            $table->string('remember_token', 100)->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
