<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Emailing beží v Accounte (kontakty, kampane, doručenia sa preniesli cez
 * `mailing:import`), lokálne tabuľky sa už nepoužívajú.
 *
 * Návrat tabuľky nevytvorí – dáta sú v Accounte a v zálohe pred prechodom.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach (['mailing_links', 'mailing_deliveries', 'mailing_campaigns', 'mailing_templates', 'mailing_contacts'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void {}
};
