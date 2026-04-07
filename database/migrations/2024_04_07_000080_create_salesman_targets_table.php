<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salesman_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salesman_id')->constrained()->onDelete('cascade');
            $table->string('period', 7); // YYYY-MM
            $table->decimal('target_amount', 18, 2);
            $table->timestamps();

            $table->unique(['salesman_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salesman_targets');
    }
};
