<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('code', 30)->nullable()->unique();
            $table->foreignId('parent_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('book_copies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->restrictOnDelete();
            $table->string('accession_number', 40)->nullable()->unique(); // tombo
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('status', 20)->default('active');
            $table->string('condition', 20)->default('good');
            $table->date('acquired_at')->nullable();
            $table->string('acquisition_source', 120)->nullable();
            $table->text('notes')->nullable();
            $table->string('status_reason', 255)->nullable();
            $table->timestamp('status_changed_at')->nullable();
            $table->timestamps();

            $table->index(['book_id', 'status']);
        });

        Schema::create('copy_barcodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('copy_id')->constrained('book_copies')->restrictOnDelete();
            $table->string('code', 40)->unique(); // único globalmente, inclusive códigos antigos
            $table->boolean('active')->default(true);
            $table->timestamp('replaced_at')->nullable();
            $table->string('replaced_reason', 120)->nullable();
            $table->timestamps();
        });

        // Exatamente um código ativo por exemplar.
        DB::statement('create unique index copy_barcodes_one_active_per_copy on copy_barcodes (copy_id) where active = true');
    }

    public function down(): void
    {
        Schema::dropIfExists('copy_barcodes');
        Schema::dropIfExists('book_copies');
        Schema::dropIfExists('locations');
    }
};
