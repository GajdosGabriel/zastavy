<?php

use App\Enums\ModelStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<string, ModelStatus>
     */
    private array $tables = [
        'users' => ModelStatus::Active,
        'customers' => ModelStatus::Active,
        'orders' => ModelStatus::Draft,
        'order_products' => ModelStatus::Active,
        'products' => ModelStatus::Active,
        'shippings' => ModelStatus::Active,
        'stocks' => ModelStatus::Active,
        'categories' => ModelStatus::Active,
        'images' => ModelStatus::Active,
        'marks' => ModelStatus::Active,
        'notices' => ModelStatus::Active,
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName => $defaultStatus) {
            Schema::table($tableName, function (Blueprint $table) use ($defaultStatus) {
                $table->string('status', 32)
                    ->default($defaultStatus->value)
                    ->index()
                    ->after('id');
            });
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->tables) as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }
    }
};
