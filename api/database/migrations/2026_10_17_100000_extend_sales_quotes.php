<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cenové ponuky pre obsluhu: číslo, väzba na zákazníka, koncepty a odoslanie.
 *
 * `sales_quotes.version` ostáva číslom poslednej odoslanej verzie. Koncept je
 * verzia o jedna vyššia bez `sent_at`; zákazník ju nevidí.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_quotes', function (Blueprint $t) {
            $t->string('number', 20)->nullable()->unique()->after('uuid');
            $t->text('token')->nullable()->after('token_hash');
            $t->unsignedBigInteger('customer_id')->nullable()->index()->after('customer');
            $t->string('source', 20)->default('web')->after('customer_id');
            $t->string('title', 200)->nullable()->after('source');
            $t->text('internal_note')->nullable()->after('brief');
            $t->unsignedBigInteger('created_by')->nullable()->after('user_id');
            $t->date('valid_until')->nullable()->index()->after('version');
            $t->decimal('total_net', 10, 2)->nullable()->after('valid_until');
            $t->decimal('total_gross', 10, 2)->nullable()->after('total_net');
            $t->timestamp('sent_at')->nullable()->after('total_gross');
            $t->string('sent_to')->nullable()->after('sent_at');
            $t->timestamp('viewed_at')->nullable()->after('sent_to');
        });
        Schema::table('sales_quotes', function (Blueprint $t) {
            $t->text('brief')->nullable()->change();
        });
        Schema::table('sales_quote_versions', function (Blueprint $t) {
            $t->json('shipping')->nullable()->change();
            $t->date('valid_until')->nullable()->change();
            $t->decimal('discount_percent', 5, 2)->default(0)->after('shipping');
            $t->json('totals')->nullable()->after('discount_percent');
            $t->timestamp('sent_at')->nullable()->after('created_by');
        });

        DB::table('sales_quote_versions')->update(['sent_at' => DB::raw('created_at')]);

        $last = [];
        foreach (DB::table('sales_quotes')->orderBy('id')->get() as $quote) {
            $period = 'CP-'.substr((string) $quote->created_at, 0, 4);
            $last[$period] = ($last[$period] ?? 0) + 1;
            $version = DB::table('sales_quote_versions')->where('sales_quote_id', $quote->id)->orderByDesc('version')->first();
            $gross = null;
            if ($version) {
                $gross = round(collect(json_decode($version->items, true))->sum(fn ($i) => round($i['quantity'] * $i['price'], 2))
                    + (float) (json_decode((string) $version->shipping, true)['price'] ?? 0), 2);
            }
            DB::table('sales_quotes')->where('id', $quote->id)->update([
                'number' => $period.'-'.str_pad($last[$period], 4, '0', STR_PAD_LEFT),
                'valid_until' => $version?->valid_until,
                'total_gross' => $gross,
                'sent_at' => $version?->created_at,
                'sent_to' => $version ? (json_decode($quote->customer, true)['email'] ?? null) : null,
            ]);
        }
        foreach ($last as $period => $number) {
            DB::table('order_sequences')->insertOrIgnore(['period' => $period, 'last_number' => $number]);
        }
    }

    public function down(): void
    {
        Schema::table('sales_quote_versions', fn (Blueprint $t) => $t->dropColumn(['discount_percent', 'totals', 'sent_at']));
        Schema::table('sales_quotes', function (Blueprint $t) {
            $t->dropUnique(['number']);
            $t->dropIndex(['customer_id']);
            $t->dropIndex(['valid_until']);
            $t->dropColumn(['number', 'token', 'customer_id', 'source', 'title', 'internal_note', 'created_by', 'valid_until', 'total_net', 'total_gross', 'sent_at', 'sent_to', 'viewed_at']);
        });
        DB::table('order_sequences')->where('period', 'like', 'CP-%')->delete();
    }
};
