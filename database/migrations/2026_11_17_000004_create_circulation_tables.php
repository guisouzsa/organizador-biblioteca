<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('borrowers', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);
            $table->string('name');
            $table->string('registration_number', 40)->nullable();
            $table->string('class_group', 60)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamp('status_changed_at')->nullable();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->timestamp('anonymized_at')->nullable();
            $table->timestamps();

            $table->unique(['type', 'registration_number']); // vários NULL permitidos
            $table->index('name');
        });

        // Regras definidas pela escola: sem valores padrão (RN-CIR-03).
        Schema::create('loan_rules', function (Blueprint $table) {
            $table->id();
            $table->string('borrower_type', 20)->unique();
            $table->unsignedSmallInteger('loan_days');
            $table->unsignedSmallInteger('max_active_loans')->nullable();
            $table->unsignedSmallInteger('max_renewals')->nullable();
            $table->unsignedSmallInteger('renewal_days')->nullable();
            $table->boolean('renew_when_overdue');
            $table->boolean('block_when_overdue');
            $table->unsignedSmallInteger('reservation_hold_hours')->nullable();
            $table->timestamps();
        });

        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('copy_id')->constrained('book_copies')->restrictOnDelete();
            $table->foreignId('borrower_id')->constrained('borrowers')->restrictOnDelete();
            $table->foreignId('loaned_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('loaned_at');
            $table->date('due_on');
            $table->timestamp('returned_at')->nullable();
            $table->foreignId('returned_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('outcome', 20)->nullable();
            $table->foreignId('loan_rule_id')->nullable()->constrained('loan_rules')->nullOnDelete();
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index('due_on');
            $table->index(['borrower_id', 'returned_at']);
        });

        // No máximo UM empréstimo ativo por exemplar (barreira final contra corrida).
        DB::statement('create unique index loans_one_active_per_copy on loans (copy_id) where returned_at is null');

        Schema::create('loan_renewals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->restrictOnDelete();
            $table->foreignId('renewed_by')->constrained('users')->restrictOnDelete();
            $table->date('previous_due_on');
            $table->date('new_due_on');
            $table->timestamp('created_at')->useCurrent();
        });

        // Histórico do exemplar: append-only.
        Schema::create('copy_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('copy_id')->constrained('book_copies')->restrictOnDelete();
            $table->string('type', 30);
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('loan_id')->nullable()->constrained('loans')->restrictOnDelete();
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['copy_id', 'created_at']);
        });

        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->restrictOnDelete();
            $table->foreignId('borrower_id')->constrained('borrowers')->restrictOnDelete();
            $table->string('status', 20)->default('waiting');
            $table->timestamp('requested_at');
            $table->foreignId('copy_id')->nullable()->constrained('book_copies')->restrictOnDelete();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('fulfilled_loan_id')->nullable()->constrained('loans')->restrictOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('cancel_reason', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['book_id', 'status', 'requested_at']);
        });

        // Uma reserva ativa por leitor e livro.
        DB::statement("create unique index reservations_one_active_per_pair on reservations (borrower_id, book_id) where status in ('waiting', 'ready')");
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
        Schema::dropIfExists('copy_events');
        Schema::dropIfExists('loan_renewals');
        Schema::dropIfExists('loans');
        Schema::dropIfExists('loan_rules');
        Schema::dropIfExists('borrowers');
    }
};
