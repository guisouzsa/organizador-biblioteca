<?php

namespace App\Services\Catalog;

use App\Exceptions\ExternalLookupException;
use App\Models\BookSource;
use App\Services\Catalog\Concerns\SanitizesExternalData;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** Consulta o Open Library por ISBN (edição) e normaliza o resultado. */
class OpenLibraryService
{
    use SanitizesExternalData;

    private const MAX_AUTHOR_LOOKUPS = 4;

    public function __construct(private IsbnValidatorService $isbn) {}

    /**
     * @return array<string, mixed>|null null quando o ISBN não é encontrado
     *
     * @throws ExternalLookupException
     */
    public function findByIsbn(string $isbn13): ?array
    {
        $config = config('services.open_library');
        $base = rtrim($config['base_url'], '/');
        $timeout = $config['timeout'] ?? 8;

        try {
            $response = Http::timeout($timeout)->acceptJson()->get("{$base}/isbn/{$isbn13}.json");

            if ($response->status() === 404) {
                return null;
            }
            if ($response->status() === 429) {
                throw new ExternalLookupException('Open Library: limite de requisições atingido (HTTP 429).');
            }
            if ($response->failed()) {
                throw new ExternalLookupException('Open Library respondeu HTTP '.$response->status().'.');
            }

            $edition = $response->json();
            if (! is_array($edition)) {
                throw new ExternalLookupException('Open Library retornou uma resposta que não é JSON válido.');
            }
            if ($this->text($edition['title'] ?? null) === null) {
                return null;
            }

            $authors = $this->authors($edition, $base, $timeout);
        } catch (ConnectionException) {
            throw new ExternalLookupException('Open Library indisponível (falha de conexão ou tempo esgotado).');
        }

        return $this->normalize($edition, $authors, $isbn13, $base, $config['covers_url'] ?? 'https://covers.openlibrary.org');
    }

    /**
     * Resolve nomes dos autores (a edição só traz chaves). Falhas aqui não derrubam a consulta.
     *
     * @return list<string>
     */
    private function authors(array $edition, string $base, int $timeout): array
    {
        $names = [];
        $refs = is_array($edition['authors'] ?? null) ? array_slice($edition['authors'], 0, self::MAX_AUTHOR_LOOKUPS) : [];

        foreach ($refs as $ref) {
            $key = is_array($ref) ? ($ref['key'] ?? null) : null;
            if (! is_string($key) || ! preg_match('#^/authors/[A-Za-z0-9]+$#', $key)) {
                continue;
            }

            try {
                $author = Http::timeout($timeout)->acceptJson()->get("{$base}{$key}.json");
                $name = $author->successful() ? $this->text($author->json('name'), 255) : null;
                if ($name !== null) {
                    $names[] = $name;
                }
            } catch (ConnectionException) {
                // Mantém os demais autores.
            }
        }

        return $names;
    }

    private function normalize(array $edition, array $authors, string $isbn13, string $base, string $coversUrl): array
    {
        $isbn10 = null;
        foreach ($this->textList($edition['isbn_10'] ?? null, 40) as $candidate) {
            $value = $this->isbn->normalize($candidate);
            if ($this->isbn->isValidIsbn10($value)) {
                $isbn10 = $value;
                break;
            }
        }

        $coverId = collect(is_array($edition['covers'] ?? null) ? $edition['covers'] : [])
            ->first(fn ($id) => is_int($id) && $id > 0);

        $description = $edition['description'] ?? null;
        if (is_array($description)) {
            $description = $description['value'] ?? null;
        }

        $subjects = collect(is_array($edition['subjects'] ?? null) ? $edition['subjects'] : [])
            ->map(fn ($s) => $this->text(is_array($s) ? ($s['name'] ?? null) : $s, 255))
            ->filter()->unique()->values()->all();

        $key = $this->text($edition['key'] ?? null, 100);
        $validKey = $key !== null && preg_match('#^/books/[A-Za-z0-9]+$#', $key) ? $key : null;

        $language = $edition['languages'][0]['key'] ?? null;
        $language = is_string($language) && preg_match('#^/languages/([a-z]{2,3})$#', $language, $m) ? $m[1] : null;

        return [
            'provider' => BookSource::PROVIDER_OPEN_LIBRARY,
            'external_id' => $validKey ? basename($validKey) : null,
            'source_url' => $validKey ? $base.$validKey : null,
            'title' => $this->text($edition['title'] ?? null, 255),
            'subtitle' => $this->text($edition['subtitle'] ?? null, 255),
            'authors' => $authors,
            'publisher' => $this->textList($edition['publishers'] ?? null)[0] ?? null,
            'publication_year' => $this->yearFrom($edition['publish_date'] ?? null),
            'isbn10' => $isbn10,
            'isbn13' => $isbn13,
            'edition' => $this->text($edition['edition_name'] ?? null, 100),
            'pages' => $this->positiveInt($edition['number_of_pages'] ?? null),
            'description' => $this->text($description),
            'categories' => $subjects,
            'cover_url' => $coverId ? rtrim($coversUrl, '/')."/b/id/{$coverId}-L.jpg" : null,
            'language' => $language,
            'raw' => $edition,
        ];
    }
}
