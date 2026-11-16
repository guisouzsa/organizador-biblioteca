<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SEEDED_DISCIPLINES = [
        'lingua-portuguesa', 'literatura', 'redacao', 'matematica', 'historia', 'geografia', 'filosofia',
        'sociologia', 'biologia', 'fisica', 'quimica', 'ingles', 'espanhol', 'educacao-fisica',
        'educacao-digital', 'outros',
    ];

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('viewer')->after('password');
            $table->boolean('active')->default(true)->after('role');
            $table->boolean('must_change_password')->default(false)->after('active');
            $table->timestamp('last_login_at')->nullable()->after('must_change_password');
        });

        Schema::table('books', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->index()->after('collection_id');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->string('type', 20)->default('theme')->after('slug');
            $table->timestamp('archived_at')->nullable()->after('type');
        });

        // As categorias semeadas são disciplinas escolares.
        DB::table('categories')->whereIn('slug', self::SEEDED_DISCIPLINES)->update(['type' => 'discipline']);
    }

    public function down(): void
    {
        Schema::table('categories', fn (Blueprint $t) => $t->dropColumn(['type', 'archived_at']));
        Schema::table('books', function (Blueprint $t) {
            $t->dropIndex(['archived_at']);
            $t->dropColumn('archived_at');
        });
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['role', 'active', 'must_change_password', 'last_login_at']));
    }
};
