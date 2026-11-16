<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = ['books', 'borrowers', 'book_copies', 'loans', 'reservations', 'locations'];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->string('demo_batch', 50)->nullable()->index());
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::TABLES) as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('demo_batch'));
        }
    }
};
