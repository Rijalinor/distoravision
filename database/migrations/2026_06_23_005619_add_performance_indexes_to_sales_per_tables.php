<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add performance indexes to sales_per_transactions and sales_per_stocks tables.
     * These columns are frequently used in WHERE, GROUP BY, and ACL scoping queries.
     */
    public function up(): void
    {
        Schema::table('sales_per_transactions', function (Blueprint $table) {
            $table->index(['period', 'type'], 'spt_period_type_idx');
            $table->index('sales_code', 'spt_sales_code_idx');
            $table->index('principal_name', 'spt_principal_name_idx');
            $table->index('outlet_code', 'spt_outlet_code_idx');
        });

        Schema::table('sales_per_stocks', function (Blueprint $table) {
            $table->index(['period', 'principal_name'], 'sps_period_principal_idx');
            $table->index('swc', 'sps_swc_idx');
        });
    }

    public function down(): void
    {
        Schema::table('sales_per_transactions', function (Blueprint $table) {
            $table->dropIndex('spt_period_type_idx');
            $table->dropIndex('spt_sales_code_idx');
            $table->dropIndex('spt_principal_name_idx');
            $table->dropIndex('spt_outlet_code_idx');
        });

        Schema::table('sales_per_stocks', function (Blueprint $table) {
            $table->dropIndex('sps_period_principal_idx');
            $table->dropIndex('sps_swc_idx');
        });
    }
};
