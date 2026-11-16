<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Registro bibliográfico (obra/edição). Não representa cópias físicas.
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->text('authors')->nullable(); // nomes separados por "; "
            $table->string('publisher')->nullable();
            $table->string('isbn10', 10)->nullable()->unique();
            $table->string('isbn13', 13)->nullable()->unique();
            $table->string('edition', 100)->nullable();
            $table->unsignedSmallInteger('publication_year')->nullable();
            $table->unsignedSmallInteger('pages')->nullable();
            $table->text('description')->nullable();
            $table->string('cover_url', 2048)->nullable();
            $table->string('language', 10)->nullable();
            $table->string('format', 50)->nullable();
            $table->foreignId('collection_id')->nullable()->constrained('collections')->nullOnDelete();
            $table->timestamps();

            $table->index('title');
            $table->index('publisher');
            $table->index('publication_year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
