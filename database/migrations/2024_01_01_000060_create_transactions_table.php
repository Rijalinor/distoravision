<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->onDelete('cascade');
            $table->foreignId('salesman_id')->constrained('salesmen')->onDelete('cascade');
            $table->foreignId('outlet_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');

            $table->enum('type', ['I', 'R'])->comment('I=Invoice, R=Return');
            $table->string('so_no', 100)->nullable()->comment('SO/SN No');
            $table->date('so_date')->nullable()->comment('SO/SN Date');
            $table->string('ref_no', 100)->nullable();
            $table->string('pfi_cn_no', 100)->nullable()->comment('PFI/CN No');
            $table->date('pfi_cn_date')->nullable();
            $table->string('gi_gr_no', 100)->nullable()->comment('GI/GR No');
            $table->date('gi_gr_date')->nullable();
            $table->string('si_cn_no', 100)->nullable()->comment('SI/CN No');
            $table->string('month', 10)->nullable();
            $table->unsignedTinyInteger('week')->nullable();
            $table->string('warehouse', 100)->nullable();
            $table->string('tax_invoice', 100)->nullable();

            $table->integer('qty_base')->default(0);
            $table->decimal('price_base', 18, 4)->default(0);
            $table->decimal('gross', 18, 4)->default(0);
            $table->decimal('disc_total', 18, 4)->default(0);
            $table->decimal('taxed_amt', 18, 4)->default(0);
            $table->decimal('vat', 18, 4)->default(0);
            $table->decimal('ar_amt', 18, 4)->default(0);
            $table->decimal('cogs', 18, 4)->default(0);

            $table->string('period', 7)->index()->comment('YYYY-MM format');

            $table->timestamps();

            $table->index(['type', 'period']);
            $table->index(['salesman_id', 'period']);
            $table->index(['outlet_id', 'period']);
            $table->index(['product_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
