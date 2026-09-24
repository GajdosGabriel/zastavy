<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_quotes', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->string('token_hash', 64);
            $t->dateTime('token_expires_at');
            $t->json('customer');
            $t->text('brief');
            $t->json('requested_items');
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('status')->default('requested')->index();
            $t->unsignedInteger('version')->default(0);
            $t->unsignedBigInteger('order_id')->nullable()->unique();
            $t->timestamp('accepted_at')->nullable();
            $t->string('accepted_by')->nullable();
            $t->timestamps();
        });
        Schema::create('sales_quote_versions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sales_quote_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('version');
            $t->json('items');
            $t->json('shipping');
            $t->text('terms')->nullable();
            $t->date('valid_until');
            $t->unsignedBigInteger('created_by');
            $t->timestamps();
            $t->unique(['sales_quote_id', 'version']);
        });
        Schema::create('order_productions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $t->string('status')->default('awaiting_artwork')->index();
            $t->date('production_due_at')->nullable()->index();
            $t->date('delivery_due_at')->nullable()->index();
            $t->text('materials')->nullable();
            $t->text('note')->nullable();
            $t->string('token_hash', 64)->nullable();
            $t->dateTime('token_expires_at')->nullable();
            $t->timestamps();
        });
        Schema::create('artwork_versions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('version');
            $t->string('status')->default('pending');
            $t->string('disk');
            $t->string('path');
            $t->string('name');
            $t->string('sha256', 64);
            $t->text('note')->nullable();
            $t->unsignedBigInteger('created_by');
            $t->timestamp('approved_at')->nullable();
            $t->string('approved_by')->nullable();
            $t->string('approved_email')->nullable();
            $t->timestamps();
            $t->unique(['order_id', 'version']);
        });
        Schema::create('artwork_comments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('artwork_version_id')->constrained()->cascadeOnDelete();
            $t->string('author');
            $t->text('body');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['artwork_comments', 'artwork_versions', 'order_productions', 'sales_quote_versions', 'sales_quotes'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
