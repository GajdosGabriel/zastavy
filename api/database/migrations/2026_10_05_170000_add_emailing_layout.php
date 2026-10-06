<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['mailing_templates', 'mailing_campaigns'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->string('layout', 30)->default('default'));
        }
    }

    public function down(): void
    {
        foreach (['mailing_templates', 'mailing_campaigns'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('layout'));
        }
    }
};
