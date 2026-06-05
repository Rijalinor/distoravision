<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_per_stocks', function (Blueprint $table) {
            // Change SWC from integer to decimal to preserve precision from ERP data
            // Values like 3.7 weeks were being truncated to 3, causing items with
            // SWC 0.5 to be misclassified as "No Sales" (SWC = 0)
            $table->decimal('swc', 8, 2)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('sales_per_stocks', function (Blueprint $table) {
            $table->integer('swc')->default(0)->change();
        });
    }
};
