<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Predpripravené texty správ zákazníkom (objednávky aj cenové ponuky). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_templates', function (Blueprint $t) {
            $t->id();
            $t->string('name', 150);
            $t->text('body');
            $t->unsignedInteger('sort_order')->default(99);
            $t->timestamps();
        });

        DB::table('message_templates')->insert([
            'name' => 'Grafický návrh obecnej zástavy',
            'body' => "Dobrý deň,\n\nna základe vašej objednávky spracoval som grafický návrh obecnej zástavy, v prílohe.\n\nAk je návrh správny, prosím o jeho potvrdenie.\n\nPo potvrdení správnosti návrhu ju začneme vyrábať.\n\nPotvrdiť prosím obratom.\n\nĎakujem\nGajdoš Gabriel",
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('message_templates');
    }
};
