<?php

namespace Database\Seeders;

use App\Enums\BorrowerType;
use App\Enums\CategoryType;
use App\Enums\UserRole;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\BookSource;
use App\Models\BookWork;
use App\Models\Borrower;
use App\Models\Category;
use App\Models\CopyBarcode;
use App\Models\Location;
use App\Models\Loan;
use App\Models\LoanRenewal;
use App\Models\LoanRule;
use App\Models\Reservation;
use App\Models\User;
use App\Services\Catalog\OpenLibraryService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    public const BATCH = 'demo-catalogo-v1';

    private const BOOKS = [
        ['Biologia Hoje Sérgio Linhares', 'school', 'textbook', 'Biologia', 'discipline'],
        ['Química Martha Reis ensino médio', 'school', 'textbook', 'Química', 'discipline'],
        ['Física contexto aplicações Antônio Máximo', 'school', 'textbook', 'Física', 'discipline'],
        ['Matemática Paiva ensino médio', 'school', 'textbook', 'Matemática', 'discipline'],
        ['História Sociedade Cidadania Alfredo Boulos', 'school', 'textbook', 'História', 'discipline'],
        ['Geografia Geral Brasil Eustáquio de Sene', 'school', 'textbook', 'Geografia', 'discipline'],
        ['Filosofando Maria Lúcia de Arruda Aranha', 'school', 'textbook', 'Filosofia', 'discipline'],
        ['Sociologia para o ensino médio Nelson Tomazi', 'school', 'textbook', 'Sociologia', 'discipline'],
        ['Português Linguagens William Cereja', 'school', 'textbook', 'Língua Portuguesa', 'discipline'],
        ['Literatura Brasileira William Cereja', 'school', 'textbook', 'Literatura', 'discipline'],
        ['Biologia Campbell', 'school', 'support', 'Biologia', 'discipline'],
        ['Princípios de Química Atkins', 'school', 'support', 'Química', 'discipline'],
        ['Fundamentos de Física Halliday', 'school', 'support', 'Física', 'discipline'],
        ['Matemática Dante', 'school', 'textbook', 'Matemática', 'discipline'],
        ['História do Brasil Boris Fausto', 'school', 'support', 'História', 'discipline'],
        ['Geografia pequena história crítica', 'school', 'support', 'Geografia', 'discipline'],
        ['Convite à Filosofia Marilena Chaui', 'school', 'support', 'Filosofia', 'discipline'],
        ['Nova gramática do português contemporâneo Celso Cunha', 'school', 'reference', 'Língua Portuguesa', 'discipline'],
        ['Dom Casmurro Machado de Assis', 'general', 'literature', 'Romance', 'genre'],
        ['Quarto de Despejo Carolina Maria de Jesus', 'general', 'literature', 'Literatura brasileira', 'subject'],
        ['A Hora da Estrela Clarice Lispector', 'general', 'literature', 'Romance', 'genre'],
        ['Capitães da Areia Jorge Amado', 'general', 'literature', 'Literatura brasileira', 'subject'],
        ['Grande Sertão Veredas Guimarães Rosa', 'general', 'literature', 'Romance', 'genre'],
        ['O Pequeno Príncipe Antoine de Saint-Exupéry', 'general', 'literature', 'Literatura infantil', 'genre'],
        ['1984 George Orwell', 'general', 'literature', 'Ficção científica', 'genre'],
        ['O Mundo de Sofia Jostein Gaarder', 'general', 'literature', 'Filosofia', 'subject'],
        ['Cosmos Carl Sagan', 'general', 'popular_science', 'Divulgação científica', 'subject'],
        ['Sapiens Yuval Noah Harari', 'general', 'popular_science', 'História', 'subject'],
        ['Torto Arado Itamar Vieira Junior', 'general', 'literature', 'Literatura brasileira', 'subject'],
        ['Ideias para adiar o fim do mundo Ailton Krenak', 'general', 'literature', 'Política', 'subject'],
    ];

    public function run(): void
    {
        $this->ensureDemoLibraryDefaults();

        $locations = $this->locations();
        $provider = app(OpenLibraryService::class);
        $books = collect();

        foreach (self::BOOKS as $index => [$query, $group, $material, $categoryName, $categoryType]) {
            try {
                $candidate = collect($provider->search($query.' language:por', 8))
                    ->filter(fn ($item) => filled($item['isbn13'] ?? null))
                    ->map(fn ($item) => $provider->findByIsbn($item['isbn13']))
                    ->first(fn ($item) => is_array($item)
                        && filled($item['title'] ?? null)
                        && in_array(strtolower((string) ($item['language'] ?? '')), ['por', 'pt', 'pt-br'], true));
            } catch (\Throwable $e) {
                $this->command?->warn("Fonte indisponível para: {$query}");
                continue;
            }
            if (! $candidate) continue;

            $work = BookWork::firstOrCreate(
                ['preferred_title' => $candidate['title'], 'authors' => implode('; ', $candidate['authors'] ?? [])],
                ['original_language' => $candidate['language'], 'description' => $candidate['description']],
            );
            $book = Book::firstOrCreate(['isbn13' => $candidate['isbn13']], [
                'work_id' => $work->id, 'title' => $candidate['title'], 'subtitle' => $candidate['subtitle'],
                'authors' => implode('; ', $candidate['authors'] ?? []), 'publisher' => $candidate['publisher'],
                'isbn10' => $candidate['isbn10'], 'edition' => $candidate['edition'],
                'publication_year' => $candidate['publication_year'], 'pages' => $candidate['pages'],
                'description' => $candidate['description'], 'cover_url' => $candidate['cover_url'],
                'language' => $candidate['language'] ?: 'por', 'format' => null,
                'collection_group' => $group, 'material_type' => $material,
                'education_stage' => $group === 'school' ? 'Ensino Médio' : null,
                'demo_batch' => self::BATCH,
            ]);
            if ($book->demo_batch !== self::BATCH) continue;

            $category = Category::findOrCreateByName($categoryName, CategoryType::from($categoryType));
            $book->categories()->syncWithoutDetaching([$category->id]);
            $book->sources()->updateOrCreate(
                ['provider' => BookSource::PROVIDER_OPEN_LIBRARY, 'external_id' => $candidate['external_id']],
                ['source_url' => $candidate['source_url'], 'retrieved_at' => now(), 'raw_metadata' => $candidate['raw'], 'verification_status' => BookSource::STATUS_CONFIRMED],
            );
            $books->push($book);
            if (! $book->copies()->exists()) $this->copies($book, $index, $locations);
            usleep(400000);
        }

        $borrowers = Borrower::where('demo_batch', self::BATCH)->get();
        if ($borrowers->isEmpty()) $borrowers = $this->borrowers();
        $this->circulation($books, $borrowers);
    }

    private function ensureDemoLibraryDefaults(): void
    {
        User::firstOrCreate(
            ['email' => 'biblioteca.demo@example.invalid'],
            [
                'name' => 'Bibliotecária de Demonstração',
                'password' => Hash::make('demo-123456'),
                'role' => UserRole::Librarian,
                'active' => true,
                'must_change_password' => false,
            ],
        );

        foreach ($this->defaultLoanRules() as $rule) {
            LoanRule::updateOrCreate(
                ['borrower_type' => $rule['borrower_type']],
                [
                    'loan_days' => $rule['loan_days'],
                    'max_active_loans' => $rule['max_active_loans'],
                    'max_renewals' => $rule['max_renewals'],
                    'renewal_days' => $rule['renewal_days'],
                    'renew_when_overdue' => $rule['renew_when_overdue'],
                    'block_when_overdue' => $rule['block_when_overdue'],
                    'reservation_hold_hours' => $rule['reservation_hold_hours'],
                ],
            );
        }
    }

    /** @return list<array{borrower_type: string, loan_days: int, max_active_loans: int, max_renewals: int, renewal_days: int, renew_when_overdue: bool, block_when_overdue: bool, reservation_hold_hours: int}> */
    private function defaultLoanRules(): array
    {
        return [
            ['borrower_type' => BorrowerType::Student->value, 'loan_days' => 7, 'max_active_loans' => 3, 'max_renewals' => 1, 'renewal_days' => 7, 'renew_when_overdue' => false, 'block_when_overdue' => true, 'reservation_hold_hours' => 24],
            ['borrower_type' => BorrowerType::Staff->value, 'loan_days' => 14, 'max_active_loans' => 5, 'max_renewals' => 2, 'renewal_days' => 14, 'renew_when_overdue' => true, 'block_when_overdue' => false, 'reservation_hold_hours' => 48],
            ['borrower_type' => BorrowerType::Other->value, 'loan_days' => 10, 'max_active_loans' => 2, 'max_renewals' => 1, 'renewal_days' => 7, 'renew_when_overdue' => false, 'block_when_overdue' => true, 'reservation_hold_hours' => 24],
        ];
    }

    private function locations(): array
    {
        return collect([['Sala principal', 'DEMO-SALA'], ['Estante escolar', 'DEMO-ESC'], ['Estante literatura', 'DEMO-LIT'], ['Referência', 'DEMO-REF']])
            ->map(fn ($item) => Location::firstOrCreate(['code' => $item[1]], ['name' => $item[0], 'active' => true, 'demo_batch' => self::BATCH]))->all();
    }

    private function copies(Book $book, int $index, array $locations): void
    {
        $total = ($index % 4) + 1;
        foreach (range(1, $total) as $number) {
            $status = $number === $total && $index % 7 === 0 ? 'maintenance' : 'active';
            $condition = ['good', 'good', 'fair', 'poor'][($index + $number) % 4];
            $accession = sprintf('DEMO-B%05d-%02d', $book->id, $number);
            $copy = BookCopy::create([
                'book_id' => $book->id, 'accession_number' => $accession,
                'location_id' => $locations[$index % count($locations)]->id,
                'status' => $status, 'condition' => $condition, 'acquired_at' => now()->subMonths(($index % 18) + 1)->toDateString(),
                'acquisition_source' => 'Demonstração', 'notes' => 'Registro fictício para testes visuais.',
                'status_reason' => $status === 'maintenance' ? 'Reparo de demonstração' : null,
                'status_changed_at' => now(), 'demo_batch' => self::BATCH,
            ]);
            CopyBarcode::create(['copy_id' => $copy->id, 'code' => 'DEMOB'.str_pad((string) $book->id, 6, '0', STR_PAD_LEFT).str_pad((string) $number, 2, '0', STR_PAD_LEFT), 'active' => true]);
        }
    }

    private function borrowers()
    {
        $rows = [
            ['student','Raquel Evangelista','DEMO-A001','6º A','active'],
            ['student','Guilherme','DEMO-A002','7º B','active'],
            ['student','Emanuel','DEMO-A003','8º A','active'],
            ['student','Pamela','DEMO-A004','9º C','inactive'],
            ['student','Marianne','DEMO-A005','1º EM','active'],
            ['student','Larissa','DEMO-A006','2º EM','active'],
            ['student','Aluno Demonstração 07','DEMO-A007','3º EM','graduated'],
            ['staff','Professora Demonstração 01','DEMO-P001',null,'active'],
            ['staff','Professor Demonstração 02','DEMO-P002',null,'active'],
            ['staff','Servidora Demonstração 03','DEMO-S001',null,'active'],
            ['other','Visitante Demonstração 01','DEMO-V001',null,'active'],
            ['student','Aluna Demonstração 08','DEMO-A008','5º A','transferred'],
        ];
        return collect($rows)->map(fn ($r) => Borrower::create([
            'type'=>$r[0], 'name'=>$r[1], 'registration_number'=>$r[2], 'class_group'=>$r[3], 'status'=>$r[4],
            'email'=>strtolower(str_replace(' ', '.', $r[2])).'@example.invalid', 'demo_batch'=>self::BATCH,
        ]));
    }

    private function circulation($books, $borrowers): void
    {
        $user = User::where('active', true)->first();
        $rule = LoanRule::where('borrower_type', BorrowerType::Student->value)->first();
        if (! $user || ! $rule || $books->isEmpty()) return;

        $borrowers = $borrowers->values();
        $books = $books->values();
        $copies = BookCopy::where('demo_batch', self::BATCH)->where('status', 'active')->get();

        foreach ($copies->take(5) as $i => $copy) {
            $borrower = $borrowers->get($i);
            if (! $borrower) break;

            $daysAgo = [1, 3, 7, 15, 30][$i % 5] ?? 1;
            $loan = Loan::create([
                'copy_id' => $copy->id,
                'borrower_id' => $borrower->id,
                'loaned_by' => $user->id,
                'loaned_at' => now()->subDays($daysAgo + 1),
                'due_on' => now()->subDays($daysAgo)->toDateString(),
                'loan_rule_id' => $rule->id,
                'notes' => 'Movimentação fictícia de demonstração.',
                'demo_batch' => self::BATCH,
            ]);

            if ($i === 0 || $i === 2) {
                $previousDueOn = $loan->due_on->copy()->subDays(7);
                $newDueOn = $loan->due_on->copy()->addDays(7);
                LoanRenewal::create([
                    'loan_id' => $loan->id,
                    'renewed_by' => $user->id,
                    'previous_due_on' => $previousDueOn->toDateString(),
                    'new_due_on' => $newDueOn->toDateString(),
                ]);
            }
        }

        foreach ($copies->slice(5, 3) as $i => $copy) {
            $borrower = $borrowers->get($i + 5);
            if (! $borrower) break;

            $loanedAt = now()->subDays(25 + $i);
            $dueOn = now()->subDays(10 + $i);
            Loan::create([
                'copy_id' => $copy->id,
                'borrower_id' => $borrower->id,
                'loaned_by' => $user->id,
                'loaned_at' => $loanedAt,
                'due_on' => $dueOn->toDateString(),
                'returned_at' => now()->subDays(6 + $i),
                'returned_by' => $user->id,
                'outcome' => 'returned',
                'loan_rule_id' => $rule->id,
                'notes' => 'Empréstimo devolvido de demonstração.',
                'demo_batch' => self::BATCH,
            ]);
        }

        foreach ($books->take(3) as $i => $book) {
            $borrower = $borrowers->get($i + 8);
            if (! $borrower) break;

            Reservation::create([
                'book_id' => $book->id,
                'borrower_id' => $borrower->id,
                'status' => $i === 2 ? 'cancelled' : 'waiting',
                'requested_at' => now()->subDays($i + 1),
                'created_by' => $user->id,
                'cancel_reason' => $i === 2 ? 'Cancelado em demonstração' : null,
                'demo_batch' => self::BATCH,
            ]);
        }
    }
}
