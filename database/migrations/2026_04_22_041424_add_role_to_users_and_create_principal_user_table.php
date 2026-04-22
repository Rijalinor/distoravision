<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('admin'); // admin, supervisor, salesman
            $table->foreignId('salesman_id')->nullable()->constrained('salesmen')->nullOnDelete();
        });

        Schema::create('principal_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('principal_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('principal_user');
        
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'salesman_id')) {
                $table->dropForeign(['salesman_id']);
                $table->dropColumn(['role', 'salesman_id']);
            }
        });
    }
};
