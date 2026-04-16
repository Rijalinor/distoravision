<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ar_receivables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ar_import_log_id')->constrained('ar_import_logs')->onDelete('cascade');

            // Outlet info
            $table->string('outlet_code', 50);
            $table->string('outlet_name')->nullable();
            $table->string('outlet_ref', 100)->nullable();

            // Salesman & Supervisor
            $table->string('supervisor')->nullable();
            $table->string('salesman_code', 50)->nullable();
            $table->string('salesman_name')->nullable();

            // Principal
            $table->string('principal_code', 50)->nullable();
            $table->string('principal_name')->nullable();

            // Key document
            $table->string('pfi_sn', 100)->comment('PFI/SN - key utama');
            $table->date('doc_date')->nullable();
            $table->date('due_date')->nullable();
            $table->date('inv_exchange_date')->nullable();
            $table->unsignedSmallInteger('top')->nullable()->comment('Term of Payment (days)');
            $table->string('si_cn', 100)->nullable();

            // Balances
            $table->tinyInteger('cm')->default(0);
            $table->decimal('cn_balance', 18, 2)->default(0);
            $table->decimal('ar_amount', 18, 2)->default(0);
            $table->decimal('ar_paid', 18, 2)->default(0);
            $table->decimal('ar_balance', 18, 2)->default(0);
            $table->decimal('credit_limit', 18, 2)->default(0);

            // Payment & overdue
            $table->date('paid_date')->nullable();
            $table->integer('overdue_days')->default(0);

            // Giro info
            $table->string('giro_no', 100)->nullable();
            $table->string('bank_code', 20)->nullable();
            $table->string('bank_name')->nullable();
            $table->decimal('giro_amount', 18, 2)->nullable();
            $table->date('giro_due_date')->nullable();

            // Source tracking
            $table->string('branch_sheet', 50)->comment('Nama sheet asal, e.g. BJM');

            $table->timestamps();

            // Indexes
            $table->index('pfi_sn');
            $table->index('outlet_code');
            $table->index('salesman_code');
            $table->index('overdue_days');
            $table->index(['ar_import_log_id', 'pfi_sn']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ar_receivables');
    }
};
