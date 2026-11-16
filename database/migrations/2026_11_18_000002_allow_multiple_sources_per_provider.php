<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('book_sources', function (Blueprint $table) {
            $table->dropUnique(['book_id', 'provider']);
            $table->unique(['book_id', 'provider', 'external_id'], 'book_sources_identity_unique');
            $table->index(['provider', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::table('book_sources', function (Blueprint $table) {
            $table->dropIndex(['provider', 'external_id']);
            $table->dropUnique('book_sources_identity_unique');
            $table->unique(['book_id', 'provider']);
        });
    }
};
