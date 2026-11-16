<?php

namespace App\Services;

use App\Models\Book;
use App\Models\BookCollection;
use App\Models\BookSource;
use App\Models\Category;
use Illuminate\Support\Facades\DB;

/** Persistência do catálogo: criação, atualização, duplicidade, categorias e procedência. */
class BookCatalogService
{
    private const BOOK_FIELDS = [
        'title', 'subtitle', 'authors', 'publisher', 'isbn10', 'isbn13', 'edition',
        'publication_year', 'pages', 'description', 'cover_url', 'language', 'format',
    ];

    /** Livro já cadastrado com o mesmo ISBN (13 ou 10). */
    public function findDuplicate(?string $isbn13, ?string $isbn10, ?int $exceptId = null): ?Book
    {
        if (blank($isbn13) && blank($isbn10)) {
            return null;
        }

        return Book::query()
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->where(function ($q) use ($isbn13, $isbn10) {
                $q->when($isbn13, fn ($q) => $q->orWhere('isbn13', $isbn13))
                    ->when($isbn10, fn ($q) => $q->orWhere('isbn10', $isbn10));
            })
            ->first();
    }

    /**
     * @param  array<string, mixed>  $data  dados validados
     * @param  list<array<string, mixed>>  $sources  fontes da consulta (vazio = cadastro manual)
     */
    public function create(array $data, array $sources = []): Book
    {
        return DB::transaction(function () use ($data, $sources) {
            $book = Book::create($this->bookAttributes($data));
            $this->syncRelations($book, $data);
            $this->recordSources($book, $sources);

            return $book;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $sources
     */
    public function update(Book $book, array $data, array $sources = []): Book
    {
        return DB::transaction(function () use ($book, $data, $sources) {
            $book->update($this->bookAttributes($data));
            $this->syncRelations($book, $data);
            $this->recordSources($book, $sources);

            return $book;
        });
    }

    /** @return array<string, mixed> */
    private function bookAttributes(array $data): array
    {
        $attributes = [];
        foreach (self::BOOK_FIELDS as $field) {
            $value = $data[$field] ?? null;
            $attributes[$field] = is_string($value) && trim($value) === '' ? null : $value;
        }

        return $attributes;
    }

    private function syncRelations(Book $book, array $data): void
    {
        $ids = collect($data['categories'] ?? [])->map(fn ($id) => (int) $id);

        $names = preg_split('/[;,\r\n]+/', (string) ($data['new_categories'] ?? '')) ?: [];
        foreach ($names as $name) {
            if (trim($name) !== '') {
                $ids->push(Category::findOrCreateByName($name)->id);
            }
        }

        $book->categories()->sync($ids->unique()->values()->all());

        $collectionName = trim((string) ($data['collection_name'] ?? ''));
        $book->collection()->associate(
            $collectionName === ''
                ? null
                : BookCollection::firstOrCreate(['name' => $collectionName], ['verification_status' => 'unverified'])
        )->save();
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
}
