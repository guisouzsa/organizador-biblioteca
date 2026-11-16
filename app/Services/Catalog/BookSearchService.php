<?php

namespace App\Services\Catalog;

use App\Exceptions\ExternalLookupException;
use App\Models\Book;
use App\Services\Catalog\Contracts\BibliographicProvider;

class BookSearchService
{
    /** @var list<BibliographicProvider> */
    private array $providers;

    public function __construct(GoogleBooksService $google, OpenLibraryService $openLibrary)
    {
        $this->providers = [$google, $openLibrary];
    }

    /** @return array{local: list<Book>, external: list<array<string, mixed>>, errors: array<string, string>} */
    public function search(string $query, int $limit = 10): array
    {
        $query = trim($query);
        if ($query === '') {
            return ['local' => [], 'external' => [], 'errors' => []];
        }

        $local = Book::query()->active()->search($query)->limit($limit)->get()->all();
        $external = [];
        $errors = [];

        foreach ($this->providers as $provider) {
            try {
                $external = [...$external, ...$provider->search($query, $limit)];
            } catch (ExternalLookupException $e) {
                $errors[$provider->name()] = $e->getMessage();
            }
        }

        $external = collect($external)
            ->unique(fn (array $item) => ($item['isbn13'] ?? null) ?: mb_strtolower(($item['title'] ?? '').'|'.implode(';', $item['authors'] ?? [])))
            ->sortByDesc(fn (array $item) => in_array(strtolower((string) ($item['language'] ?? '')), ['pt', 'por', 'pt-br'], true) ? 1 : 0)
            ->take($limit)->values()->all();

        return ['local' => $local, 'external' => $external, 'errors' => $errors];
    }
}
