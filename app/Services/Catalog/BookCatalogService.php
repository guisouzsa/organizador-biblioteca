<?php

namespace App\Services\Catalog;

use App\DTOs\Catalog\BookData;
use App\Enums\ErrorCode;
use App\Exceptions\Domain\DomainException;
use App\Models\Book;
use App\Models\BookSource;
use App\Models\Category;
use App\Services\Admin\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Persistência do catálogo: criação, atualização, duplicidade, categorias, procedência e auditoria. */
class BookCatalogService
{
    public function __construct(private AuditLogger $audit) {}

    /** Livro já cadastrado com o mesmo ISBN (13 ou 10). */
    public function findDuplicate(?string $isbn13, ?string $isbn10, ?int $exceptId = null): ?Book
    {
        return Book::query()
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->withIsbn($isbn13, $isbn10)
            ->first();
    }

    /**
     * Possíveis duplicatas SEM ISBN: mesmo título normalizado e ao menos um outro dado igual
     * (autores, editora ou ano). Só avisa; nunca bloqueia (RN-CAT-04).
     *
     * @return Collection<int, Book>
     */
    public function similarBooks(BookData $data, ?int $exceptId = null): Collection
    {
        $title = $this->normalize($data->title);
        if ($title === '') {
            return collect();
        }

        $firstWord = explode(' ', $title)[0];
        $like = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        return Book::query()
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->where('title', $like, '%'.$firstWord.'%')
            ->limit(50)->get()
            ->filter(function (Book $book) use ($data, $title) {
                if ($this->normalize($book->title) !== $title) {
                    return false;
                }
                $sameAuthors = $data->authors && $this->normalize((string) $book->authors) === $this->normalize($data->authors);
                $samePublisher = $data->publisher && $this->normalize((string) $book->publisher) === $this->normalize($data->publisher);
                $sameYear = $data->publicationYear && $book->publication_year === $data->publicationYear;
                $noExtraInfo = ! $data->authors && ! $data->publisher && ! $data->publicationYear;

                return $sameAuthors || $samePublisher || $sameYear || $noExtraInfo;
            })->take(5)->values();
    }

    /** @param  list<array<string, mixed>>  $sources  fontes da consulta (vazio = cadastro manual) */
    public function create(BookData $data, array $sources = []): Book
    {
        return DB::transaction(function () use ($data, $sources) {
            $book = Book::create($data->attributes());
            $this->syncCategories($book, $data);
            $this->recordSources($book, $sources);
            $this->audit->record('book.created', $book, [], $data->attributes());

            return $book;
        });
    }

    /**
     * @param  list<array<string, mixed>>  $sources
     */
    public function update(Book $book, BookData $data, array $sources = [], bool $confirmIsbnChange = false): Book
    {
        $this->guardIsbnChange($book, $data, $confirmIsbnChange);

        return DB::transaction(function () use ($book, $data, $sources) {
            $before = $this->snapshot($book);

            $book->update($data->attributes());
            $this->syncCategories($book, $data);
            $this->recordSources($book, $sources);

            [$old, $new] = AuditLogger::diff($before, $this->snapshot($book->refresh()));
            if ($old || $new) {
                $this->audit->record('book.updated', $book, $old, $new);
            }

            return $book;
        });
    }

    public function archive(Book $book): void
    {
        if ($book->isArchived()) {
            return;
        }
        DB::transaction(function () use ($book) {
            $book->update(['archived_at' => now()]);
            $this->audit->record('book.archived', $book);
        });
    }

    public function restore(Book $book): void
    {
        DB::transaction(function () use ($book) {
            $book->update(['archived_at' => null]);
            $this->audit->record('book.restored', $book);
        });
    }

    /** Só livros sem exemplares nem reservas (RN-CAT-09); a Policy já exige, o banco (RESTRICT) garante. */
    public function delete(Book $book): void
    {
        DB::transaction(function () use ($book) {
            $this->audit->record('book.deleted', $book, $this->snapshot($book));
            $book->delete();
        });
    }

    /** RN-CAT-10: alterar ISBN de livro com exemplares exige confirmação explícita. */
    private function guardIsbnChange(Book $book, BookData $data, bool $confirmed): void
    {
        $changed = $book->isbn13 !== $data->isbn13 || $book->isbn10 !== $data->isbn10;

        if ($changed && ! $confirmed && $book->copies()->exists()) {
            throw DomainException::of(
                ErrorCode::OperacaoInvalida,
                'Este livro já tem exemplares. Para alterar o ISBN, marque a caixa de confirmação na tela de edição.',
            );
        }
    }

    /** @return array<string, mixed> */
    private function snapshot(Book $book): array
    {
        return [
            ...$book->only(['title', 'subtitle', 'authors', 'publisher', 'isbn10', 'isbn13', 'edition', 'publication_year',
                'pages', 'description', 'cover_url', 'language', 'format', 'collection_id']),
            'categories' => $book->categories()->orderBy('name')->pluck('name')->implode('; '),
        ];
    }

    private function syncCategories(Book $book, BookData $data): void
    {
        $ids = collect($data->categoryIds);
        foreach ($data->newCategories as $name) {
            $ids->push(Category::findOrCreateByName($name)->id);
        }
        $book->categories()->sync($ids->unique()->values()->all());
    }

    /**
     * Registra a procedência. Sem fontes externas, grava origem "manual".
     * O usuário revisou os dados ao salvar, então a fonte passa a "confirmed".
     *
     * @param  list<array<string, mixed>>  $sources
     */
    private function recordSources(Book $book, array $sources): void
    {
        if ($sources === []) {
            if (! $book->sources()->exists()) {
                $book->sources()->create([
                    'provider' => BookSource::PROVIDER_MANUAL,
                    'retrieved_at' => now(),
                    'verification_status' => BookSource::STATUS_CONFIRMED,
                ]);
            }

            return;
        }

        foreach ($sources as $source) {
            $book->sources()->updateOrCreate(
                ['provider' => $source['provider']],
                [
                    'external_id' => $source['external_id'] ?? null,
                    'source_url' => $source['source_url'] ?? null,
                    'retrieved_at' => now(),
                    'raw_metadata' => $source['raw'] ?? null,
                    'verification_status' => BookSource::STATUS_CONFIRMED,
                ]
            );
        }
    }

    /** minúsculas, sem acentos, pontuação ou espaços repetidos. */
    private function normalize(string $text): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii($text))) ?? '');
    }
}
