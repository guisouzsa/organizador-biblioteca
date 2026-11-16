<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Coleções didáticas (ex.: obras de um programa como o PNLD).
        Schema::create('collections', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('publisher')->nullable();
            $table->string('fnde_code', 50)->nullable();
            $table->string('program')->nullable();
            $table->string('cycle', 50)->nullable();
            $table->string('knowledge_area')->nullable();
            $table->string('source')->nullable();
            $table->string('verification_status', 30)->default('unverified');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collections');
    }
};
