<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mailing_contacts', function (Blueprint $t) {
            $t->id();
            $t->string('email')->unique();
            $t->string('name')->nullable();
            $t->string('permission_note');
            $t->timestamp('permission_at');
            $t->unsignedBigInteger('created_by')->nullable();
            $t->string('token', 64)->unique();
            $t->timestamp('unsubscribed_at')->nullable();
            $t->timestamps();
        });
        Schema::create('mailing_templates', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('subject');
            $t->string('heading');
            $t->text('body');
            $t->string('button_label')->nullable();
            $t->string('button_url', 2048)->nullable();
            $t->timestamps();
        });
        Schema::create('mailing_campaigns', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('subject');
            $t->string('heading');
            $t->text('body');
            $t->string('button_label')->nullable();
            $t->string('button_url', 2048)->nullable();
            $t->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $t->string('coupon_code')->nullable();
            $t->string('status')->default('draft')->index();
            $t->timestamp('scheduled_at')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
        });
        Schema::create('mailing_deliveries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('campaign_id')->constrained('mailing_campaigns');
            $t->foreignId('contact_id')->constrained('mailing_contacts');
            $t->string('status')->default('pending')->index();
            $t->timestamp('attempted_at')->nullable()->index();
            $t->timestamp('sent_at')->nullable()->index();
            $t->string('error')->nullable();
            $t->timestamps();
            $t->unique(['campaign_id', 'contact_id']);
        });
    }

    public function down(): void
    {
        foreach (['mailing_deliveries', 'mailing_campaigns', 'mailing_templates', 'mailing_contacts'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
