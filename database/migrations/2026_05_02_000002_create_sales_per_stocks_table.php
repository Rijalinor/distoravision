<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_per_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_per_import_log_id')->constrained('sales_per_import_logs')->onDelete('cascade');
            $table->string('principal_code', 50)->nullable();
            $table->string('principal_name')->nullable();
            $table->string('warehouse_code', 50)->nullable();
            $table->string('warehouse_name')->nullable();
            $table->string('item_no', 50)->nullable();
            $table->string('item_name')->nullable();
            $table->string('size', 100)->nullable();
            $table->integer('on_hand_base')->default(0);
            $table->integer('on_sales_base')->default(0);
            $table->decimal('stock_value_on_hand', 18, 4)->default(0);
            $table->decimal('stock_value_on_sales', 18, 4)->default(0);
            $table->decimal('was', 10, 4)->default(0); // Week Average Sales
            $table->integer('swc')->default(0); // Sales Week Coverage
            $table->integer('age_of_goods')->default(0); // in days
            $table->string('period', 7); // Y-m
            $table->timestamps();

            $table->index('period');
            $table->index('item_no');
            $table->index(['period', 'principal_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_per_stocks');
    }
};
