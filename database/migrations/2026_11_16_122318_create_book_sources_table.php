<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Procedência dos metadados de cada livro.
        Schema::create('book_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 50); // google_books, open_library, manual...
            $table->string('external_id')->nullable();
            $table->text('source_url')->nullable();
            $table->timestamp('retrieved_at')->nullable();
            $table->json('raw_metadata')->nullable();
            $table->string('verification_status', 30)->default('unverified');
            $table->timestamps();

            $table->unique(['book_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_sources');
    }
};
