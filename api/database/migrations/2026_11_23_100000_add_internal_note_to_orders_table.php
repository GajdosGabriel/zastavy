<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Poznámka objednávky ide zákazníkovi do e-mailov aj na verejnú stránku.
 * Pôvod objednávky z e-mailu a poznámka z AI (písaná o zákazníkovi, „na ručné
 * doplnenie“) patria len obsluhe – presunú sa do internej poznámky.
 */
return new class extends Migration
{
    private const LINE = '/^Objednávka z e-mailu.*– pôvodný text v konverzácii.*$/mu';

    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->text('internal_note')->nullable()->after('note');
        });

        DB::table('orders')
            ->join('email_order_drafts', 'email_order_drafts.order_id', '=', 'orders.id')
            ->whereNotNull('orders.note')
            ->select('orders.id', 'orders.note', 'email_order_drafts.auto_created')
            ->orderBy('orders.id')
            ->get()
            ->each(function ($row) {
                // Automatická objednávka: celá poznámka je z AI. Ručná: obsluha poznámku videla, presunie sa len riadok o pôvode.
                if ($row->auto_created) {
                    [$internal, $note] = [$row->note, null];
                } else {
                    preg_match_all(self::LINE, $row->note, $m);
                    $internal = implode("\n", $m[0]) ?: null;
                    $note = trim((string) preg_replace(self::LINE, '', $row->note)) ?: null;
                }

                DB::table('orders')->where('id', $row->id)->update(['note' => $note, 'internal_note' => $internal]);
            });
    }

    public function down(): void
    {
        DB::table('orders')->whereNotNull('internal_note')->orderBy('id')->get(['id', 'note', 'internal_note'])
            ->each(fn ($row) => DB::table('orders')->where('id', $row->id)
                ->update(['note' => trim($row->internal_note."\n".$row->note)]));

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('internal_note');
        });
    }
};
