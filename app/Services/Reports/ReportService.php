<?php

namespace App\Services\Reports;

use App\Models\Book;
use App\Models\Borrower;
use App\Models\CopyEvent;
use App\Models\Loan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Relatórios somente leitura. Cada relatório devolve colunas e linhas já formatadas,
 * usadas pela tela e pela exportação CSV. Todos os números vêm de consultas (RN-GES-02).
 */
class ReportService
{
    public const TYPES = [
        'atrasos' => 'Empréstimos em atraso',
        'mais-emprestados' => 'Livros mais emprestados',
        'disponibilidade' => 'Disponibilidade do acervo',
        'movimentacoes' => 'Movimentações de exemplares',
        'turmas' => 'Empréstimos por turma',
    ];

    /**
     * @param  array{de?: ?string, ate?: ?string, categoria?: ?int}  $filters
     * @return array{title: string, columns: list<string>, rows: list<list<string|int>>, period: ?string}
     */
    public function build(string $type, array $filters): array
    {
        [$from, $to] = $this->period($filters);

        $data = match ($type) {
            'atrasos' => $this->overdue(),
            'mais-emprestados' => $this->mostBorrowed($from, $to),
            'disponibilidade' => $this->availability($filters['categoria'] ?? null),
            'movimentacoes' => $this->movements($from, $to),
            'turmas' => $this->byClass($from, $to),
        };

        $usesPeriod = in_array($type, ['mais-emprestados', 'movimentacoes', 'turmas'], true);

        return ['title' => self::TYPES[$type], 'period' => $usesPeriod ? $from->format('d/m/Y').' a '.$to->format('d/m/Y') : null] + $data;
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function period(array $filters): array
    {
        $to = filled($filters['ate'] ?? null) ? Carbon::parse($filters['ate'])->endOfDay() : now()->endOfDay();
        $from = filled($filters['de'] ?? null) ? Carbon::parse($filters['de'])->startOfDay() : $to->copy()->subDays(30)->startOfDay();

        return [$from, $to];
    }

    private function overdue(): array
    {
        $rows = Loan::overdue()->with(['copy.book', 'copy.activeBarcode', 'borrower'])->orderBy('due_on')->get()->map(fn (Loan $l) => [
            $l->borrower->name, $l->borrower->class_group ?? '—', $l->copy->book->title, $l->copy->code() ?? '—',
            $l->loaned_at->format('d/m/Y'), $l->due_on->format('d/m/Y'), $l->daysOverdue(),
        ])->all();

        return ['columns' => ['Leitor', 'Turma', 'Livro', 'Exemplar', 'Emprestado em', 'Vencimento', 'Dias de atraso'], 'rows' => $rows];
    }

    private function mostBorrowed(Carbon $from, Carbon $to): array
    {
        $rows = DB::table('loans')
            ->join('book_copies', 'loans.copy_id', '=', 'book_copies.id')
            ->join('books', 'book_copies.book_id', '=', 'books.id')
            ->whereBetween('loans.loaned_at', [$from, $to])
            ->where(fn ($q) => $q->whereNull('loans.outcome')->orWhere('loans.outcome', '!=', 'voided'))
            ->groupBy('books.id', 'books.title', 'books.authors')
            ->select('books.title', 'books.authors', DB::raw('count(*) as total'), DB::raw('count(distinct loans.borrower_id) as readers'))
            ->orderByDesc('total')->orderBy('books.title')->limit(50)->get()
            ->map(fn ($r) => [$r->title, $r->authors ?? '—', (int) $r->total, (int) $r->readers])->all();

        return ['columns' => ['Livro', 'Autores', 'Empréstimos', 'Leitores distintos'], 'rows' => $rows];
    }

    private function availability(?int $categoryId): array
    {
        $books = Book::active()
            ->when($categoryId, fn ($q, $id) => $q->whereHas('categories', fn ($c) => $c->where('categories.id', $id)))
            ->withCount([
                'copies',
                'copies as available_count' => fn ($q) => $q->available(),
                'copies as on_loan_count' => fn ($q) => $q->where('status', 'active')->whereHas('loans', fn ($l) => $l->whereNull('returned_at')),
                'copies as maintenance_count' => fn ($q) => $q->where('status', 'maintenance'),
                'copies as lost_count' => fn ($q) => $q->where('status', 'lost'),
                'copies as written_off_count' => fn ($q) => $q->where('status', 'written_off'),
            ])
            ->orderBy('title')->get();

        $rows = $books->map(fn (Book $b) => [
            $b->title, $b->isbn13 ?? $b->isbn10 ?? '—', $b->copies_count, $b->available_count, $b->on_loan_count,
            $b->maintenance_count, $b->lost_count, $b->written_off_count,
        ])->all();

        return ['columns' => ['Livro', 'ISBN', 'Exemplares', 'Disponíveis', 'Emprestados', 'Manutenção', 'Perdidos', 'Baixados'], 'rows' => $rows];
    }

    private function movements(Carbon $from, Carbon $to): array
    {
        $rows = CopyEvent::with(['copy.book', 'copy.activeBarcode', 'user'])
            ->whereBetween('created_at', [$from, $to])->latest('created_at')->latest('id')->limit(500)->get()
            ->map(fn (CopyEvent $e) => [
                $e->created_at->format('d/m/Y H:i'), $e->copy?->activeBarcode?->code ?? '—',
                $e->copy?->book?->title ?? '—', $e->type->label(), $e->user?->name ?? 'Sistema',
            ])->all();

        return ['columns' => ['Data', 'Exemplar', 'Livro', 'Evento', 'Responsável'], 'rows' => $rows];
    }

    private function byClass(Carbon $from, Carbon $to): array
    {
        $loans = DB::table('loans')->join('borrowers', 'loans.borrower_id', '=', 'borrowers.id')
            ->whereBetween('loans.loaned_at', [$from, $to])
            ->where(fn ($q) => $q->whereNull('loans.outcome')->orWhere('loans.outcome', '!=', 'voided'))
            ->groupBy('borrowers.class_group')->select('borrowers.class_group', DB::raw('count(*) as total'), DB::raw('count(distinct borrowers.id) as readers'))
            ->get()->keyBy(fn ($r) => $r->class_group ?? '');

        $active = Borrower::where('status', 'active')->select('class_group', DB::raw('count(*) as total'))->groupBy('class_group')->get()->keyBy(fn ($r) => $r->class_group ?? '');

        $rows = $active->keys()->merge($loans->keys())->unique()->sort()->map(fn ($group) => [
            $group === '' ? 'Sem turma' : $group, (int) ($active[$group]->total ?? 0), (int) ($loans[$group]->readers ?? 0), (int) ($loans[$group]->total ?? 0),
        ])->values()->all();

        return ['columns' => ['Turma', 'Leitores ativos', 'Leitores que emprestaram', 'Empréstimos'], 'rows' => $rows];
    }
}
