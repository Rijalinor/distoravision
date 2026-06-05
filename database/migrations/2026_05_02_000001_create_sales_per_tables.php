<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_per_import_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('filename');
            $table->string('period', 7); // Y-m
            $table->string('status', 20)->default('pending'); // pending, processing, completed, failed
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('imported_rows')->default(0);
            $table->unsignedInteger('skipped_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->text('errors')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('sales_per_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_per_import_log_id')->constrained('sales_per_import_logs')->onDelete('cascade');
            $table->string('type', 1)->default('I'); // I=Invoice, R=Return
            $table->string('branch_code', 50)->nullable();
            $table->string('sales_code', 50)->nullable();
            $table->string('sales_name')->nullable();
            $table->string('outlet_code', 50)->nullable();
            $table->string('outlet_name')->nullable();
            $table->string('principal_code', 50)->nullable();
            $table->string('principal_name')->nullable();
            $table->string('item_no', 50)->nullable();
            $table->string('item_name')->nullable();
            $table->string('so_no', 100)->nullable();
            $table->string('pfi_no', 100)->nullable();
            $table->date('so_date')->nullable();
            $table->integer('qty')->default(0);
            $table->decimal('subtotal', 18, 4)->default(0);
            $table->decimal('vat', 18, 4)->default(0);
            $table->string('period', 7); // Y-m
            $table->timestamps();

            $table->index('period');
            $table->index('sales_code');
            $table->index('type');
            $table->index(['period', 'type', 'sales_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_per_transactions');
        Schema::dropIfExists('sales_per_import_logs');
    }
};
