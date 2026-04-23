<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('closing_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accounting_period_id')->constrained()->onDelete('cascade');

            // === SALES KPI ===
            $table->decimal('total_sales', 18, 2)->default(0);
            $table->decimal('total_returns', 18, 2)->default(0);
            $table->decimal('net_sales', 18, 2)->default(0);
            $table->decimal('total_cogs', 18, 2)->default(0);
            $table->unsignedInteger('invoice_count')->default(0);
            $table->unsignedInteger('return_count')->default(0);
            $table->decimal('return_rate', 8, 2)->default(0);
            $table->decimal('margin', 8, 2)->default(0);
            $table->json('top_products')->nullable();
            $table->json('top_outlets')->nullable();
            $table->json('principal_breakdown')->nullable();
            $table->json('salesman_sales_data')->nullable();

            // === AR KPI ===
            $table->decimal('total_outstanding', 18, 2)->default(0);
            $table->decimal('total_overdue', 18, 2)->default(0);
            $table->decimal('total_ar_amount', 18, 2)->default(0);
            $table->decimal('total_ar_paid', 18, 2)->default(0);
            $table->unsignedInteger('ar_outlet_count')->default(0);
            $table->unsignedInteger('ar_invoice_count')->default(0);
            $table->unsignedInteger('avg_overdue_days')->default(0);
            $table->unsignedInteger('max_overdue_days')->default(0);
            $table->json('aging_data')->nullable();
            $table->json('salesman_ar_data')->nullable();

            $table->timestamp('snapshot_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('closing_snapshots');
    }
};
