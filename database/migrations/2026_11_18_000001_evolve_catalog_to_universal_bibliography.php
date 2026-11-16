<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_works', function (Blueprint $table) {
            $table->id();
            $table->string('preferred_title');
            $table->text('authors')->nullable();
            $table->string('original_language', 10)->nullable();
            $table->unsignedSmallInteger('first_publication_year')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->index('preferred_title');
        });

        Schema::table('books', function (Blueprint $table) {
            $table->foreignId('work_id')->nullable()->after('id')->constrained('book_works')->nullOnDelete();
            $table->string('collection_group', 20)->default('general')->after('format');
            $table->string('material_type', 40)->nullable()->after('collection_group');
            $table->string('audience', 60)->nullable()->after('material_type');
            $table->string('education_stage', 60)->nullable()->after('audience');
            $table->index(['collection_group', 'material_type']);
            $table->index(['language', 'education_stage']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('type')->constrained('categories')->nullOnDelete();
            $table->index(['type', 'archived_at']);
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex(['type', 'archived_at']);
            $table->dropConstrainedForeignId('parent_id');
        });
        Schema::table('books', function (Blueprint $table) {
            $table->dropIndex(['collection_group', 'material_type']);
            $table->dropIndex(['language', 'education_stage']);
            $table->dropConstrainedForeignId('work_id');
            $table->dropColumn(['collection_group', 'material_type', 'audience', 'education_stage']);
        });
        Schema::dropIfExists('book_works');
    }
};
