<?php

use App\Enums\ModelStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('status', 32)->default(ModelStatus::Active->value)->index();
            $table->string('placement', 32)->index();
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('style_class')->default('bg-blue-700 text-gray-100');
            $table->unsignedInteger('sort_order')->default(0);
            $table->dateTime('published_from')->nullable();
            $table->dateTime('published_until')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::table('announcements')->insert([
            [
                'status' => ModelStatus::Active->value,
                'placement' => 'top',
                'title' => 'Prebieha Letná akcia!',
                'body' => 'Najlacnejšie Zástavy a Vlajky na Slovensku',
                'style_class' => 'bg-sky-700 text-gray-100',
                'sort_order' => 10,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'status' => ModelStatus::Active->value,
                'placement' => 'bottom',
                'title' => 'Nav Bar Bottom component',
                'body' => null,
                'style_class' => 'bg-blue-500 text-gray-200',
                'sort_order' => 10,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
