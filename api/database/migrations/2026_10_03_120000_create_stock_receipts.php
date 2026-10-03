<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_receipts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('number', 64)->nullable()->unique();
            $table->date('received_at');
            $table->string('supplier');
            $table->string('supplier_address')->nullable();
            $table->string('supplier_ico', 32)->nullable();
            $table->string('supplier_dic', 32)->nullable();
            $table->string('supplier_vat_id', 32)->nullable();
            $table->string('document_number', 64)->nullable();
            $table->string('warehouse');
            $table->string('received_by');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('created_by_name');
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason')->nullable();
            $table->timestamps();
        });
        Schema::table('stocks', function (Blueprint $table) {
            $table->foreignId('stock_receipt_id')->nullable()->constrained('stock_receipts')->restrictOnDelete();
            $table->decimal('receipt_unit_price', 10, 2)->nullable();
            $table->decimal('receipt_discount', 5, 2)->nullable();
            $table->decimal('receipt_vat', 5, 2)->nullable();
            $table->json('receipt_item_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stock_receipt_id');
            $table->dropColumn(['receipt_unit_price', 'receipt_discount', 'receipt_vat', 'receipt_item_snapshot']);
        });
        Schema::dropIfExists('stock_receipts');
    }
};
