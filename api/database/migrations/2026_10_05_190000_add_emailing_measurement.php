<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mailing_campaigns', function (Blueprint $t) {
            // Existing campaigns retain their original behaviour.
            $t->boolean('track_clicks')->default(false);
            $t->boolean('track_opens')->default(false);
        });
        Schema::table('mailing_deliveries', function (Blueprint $t) {
            $t->string('open_token', 64)->nullable()->unique();
            $t->timestamp('opened_at')->nullable();
            $t->timestamp('clicked_at')->nullable();
            $t->unsignedInteger('open_count')->default(0);
        });
        Schema::create('mailing_links', function (Blueprint $t) {
            $t->id();
            $t->foreignId('delivery_id')->constrained('mailing_deliveries')->cascadeOnDelete();
            $t->string('token', 64)->unique();
            $t->unsignedInteger('position');
            $t->text('url');
            $t->string('label');
            $t->unsignedInteger('click_count')->default(0);
            $t->timestamp('clicked_at')->nullable();
            $t->unique(['delivery_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mailing_links');
        Schema::table('mailing_deliveries', function (Blueprint $t) {
            $t->dropUnique(['open_token']);
            $t->dropColumn(['open_token', 'opened_at', 'clicked_at', 'open_count']);
        });
        Schema::table('mailing_campaigns', fn (Blueprint $t) => $t->dropColumn(['track_clicks', 'track_opens']));
    }
};
