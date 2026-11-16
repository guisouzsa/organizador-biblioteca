<?php

namespace App\Console\Commands;

use Database\Seeders\DemoDataSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReportDemoData extends Command
{
    protected $signature = 'demo:report {--books : Lista as edições importadas}';
    protected $description = 'Exibe as quantidades do lote de demonstração';

    public function handle(): int
    {
        $batch = DemoDataSeeder::BATCH;
        $books = DB::table('books')->where('demo_batch', $batch);
        $rows = [
            'Livros' => (clone $books)->count(),
            'Livros escolares' => (clone $books)->where('collection_group', 'school')->count(),
            'Livros gerais' => (clone $books)->where('collection_group', 'general')->count(),
            'Leitores' => DB::table('borrowers')->where('demo_batch', $batch)->count(),
            'Exemplares' => DB::table('book_copies')->where('demo_batch', $batch)->count(),
            'Exemplares administrativos indisponíveis' => DB::table('book_copies')->where('demo_batch', $batch)->where('status', '!=', 'active')->count(),
            'Empréstimos' => DB::table('loans')->where('demo_batch', $batch)->count(),
            'Empréstimos ativos' => DB::table('loans')->where('demo_batch', $batch)->whereNull('returned_at')->count(),
            'Atrasos ativos' => DB::table('loans')->where('demo_batch', $batch)->whereNull('returned_at')->where('due_on', '<', now()->toDateString())->count(),
            'Reservas' => DB::table('reservations')->where('demo_batch', $batch)->count(),
        ];
        $this->table(['Indicador', 'Quantidade'], collect($rows)->map(fn ($value, $label) => [$label, $value]));
        $categories = DB::table('categories')->join('book_category', 'categories.id', '=', 'book_category.category_id')
            ->whereIn('book_category.book_id', DB::table('books')->where('demo_batch', $batch)->select('id'))
            ->distinct()->orderBy('categories.name')->pluck('categories.name')->implode(', ');
        $this->line('Classificações: '.$categories);
        if ($this->option('books')) {
            $this->newLine();
            $this->table(['Título', 'ISBN-13', 'Editora', 'Ano'], DB::table('books')->where('demo_batch', $batch)
                ->orderBy('collection_group')->orderBy('title')->get(['title', 'isbn13', 'publisher', 'publication_year'])
                ->map(fn ($book) => [(string) $book->title, (string) $book->isbn13, (string) $book->publisher, (string) $book->publication_year]));
        }

        return self::SUCCESS;
    }
}
