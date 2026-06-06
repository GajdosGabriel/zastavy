<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE `customers` MODIFY `email` VARCHAR(150) NULL');
    }

    public function down(): void
    {
        DB::statement("UPDATE `customers` SET `email` = CONCAT('customer-', `id`, '@example.invalid') WHERE `email` IS NULL");
        DB::statement('ALTER TABLE `customers` MODIFY `email` VARCHAR(150) NOT NULL');
    }
};
