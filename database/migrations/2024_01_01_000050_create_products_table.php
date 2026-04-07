<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('principal_id')->constrained()->onDelete('cascade');
            $table->string('item_no', 100);
            $table->string('name', 255);
            $table->string('uom_sku', 100)->nullable();
            $table->timestamps();

            $table->unique(['principal_id', 'item_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
