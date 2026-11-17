<?php

namespace App\Services\Catalog;

use App\Exceptions\ExternalLookupException;
use App\Models\Book;
use App\Models\BookSource;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Orquestra a identificação de um livro a partir de um ISBN:
 * validação → catálogo local → fontes externas → comparação.
 */
class BookLookupService
{
    public const STATUS_INVALID = 'invalid';

    public const STATUS_EXISTS = 'exists';

    public const STATUS_FOUND = 'found';

    public const STATUS_NOT_FOUND = 'not_found';

    public const STATUS_UNAVAILABLE = 'unavailable';

    private const CACHE_TTL_SECONDS = 1800;

    public function __construct(
        private IsbnValidatorService $isbn,
        private GoogleBooksService $google,
        private OpenLibraryService $openLibrary,
        private MetadataComparisonService $comparison,
    ) {}

    /**
     * @return array{
     *   status: string, message: ?string, isbn13: ?string, isbn10: ?string,
     *   book: ?Book, sources: list<array<string, mixed>>, merged: array<string, mixed>,
     *   divergences: array<string, mixed>, errors: array<string, string>, token: ?string
     * }
     */
    public function lookup(?string $input): array
    {
        $parsed = $this->isbn->parse($input);

        $result = [
            'status' => self::STATUS_INVALID,
            'message' => $parsed['error'],
            'isbn13' => $parsed['isbn13'],
            'isbn10' => $parsed['isbn10'],
            'book' => null,
            'sources' => [],
            'merged' => [],
            'divergences' => [],
            'errors' => [],
            'token' => null,
        ];

        if (! $parsed['valid']) {
            return $result;
        }

        // Mesma regra de duplicidade usada no cadastro (Book::withIsbn).
        $existing = Book::query()->withIsbn($parsed['isbn13'], $parsed['isbn10'])->first();

        if ($existing) {
            return [...$result, 'status' => self::STATUS_EXISTS, 'message' => null, 'book' => $existing];
        }

        [$sources, $errors] = $this->queryProviders($parsed['isbn13']);

        if ($sources === []) {
            $allFailed = count($errors) === 2;

            return [
                ...$result,
                'status' => $allFailed ? self::STATUS_UNAVAILABLE : self::STATUS_NOT_FOUND,
                'message' => $allFailed
                    ? 'As fontes bibliográficas estão indisponíveis no momento. Tente novamente ou cadastre manualmente.'
                    : 'Nenhuma fonte encontrou este ISBN. Você pode cadastrar o livro manualmente.',
                'errors' => $errors,
            ];
        }

        $merged = $this->comparison->merge($sources);
        $merged['isbn13'] = $parsed['isbn13'];
        $merged['isbn10'] = $merged['isbn10'] ?? $parsed['isbn10'];

        $token = (string) Str::uuid();
        Cache::put($this->cacheKey($token), $sources, self::CACHE_TTL_SECONDS);

        return [
            ...$result,
            'status' => self::STATUS_FOUND,
            'message' => null,
            'sources' => $sources,
            'merged' => $merged,
            'divergences' => $this->comparison->divergences($sources),
            'errors' => $errors,
            'token' => $token,
        ];
    }

    /**
     * Valores iniciais do formulário de confirmação (a apresentação fica fora do controller).
     *
     * @param  array<string, mixed>  $result  retorno de lookup()
     * @return array<string, mixed>
     */
    public function formValues(array $result): array
    {
        $values = Arr::except($result['merged'], ['categories']) + ['isbn' => $result['isbn13']];
        $values['authors'] = implode('; ', $result['merged']['authors'] ?? []);
        $values['lookup_token'] = $result['token'];

        return $values;
    }

    /** Fontes guardadas de uma consulta anterior (para gravar a procedência ao salvar). */
    public function sourcesForToken(?string $token): array
    {
        if (blank($token)) {
            return [];
        }

        return Cache::get($this->cacheKey($token), []);
    }

    /** @return array<string, mixed> */
    public function formValuesForToken(?string $token): array
    {
        $sources = $this->sourcesForToken($token);
        if ($sources === []) {
            return [];
        }

        $merged = $this->comparison->merge($sources);
        $merged['authors'] = implode('; ', $merged['authors'] ?? []);
        $merged['isbn'] = $merged['isbn13'] ?? $merged['isbn10'] ?? null;
        $merged['lookup_token'] = $token;

        return $merged;
    }

    /**
     * Consulta cada provedor isoladamente: a falha de um não impede o outro.
     *
     * @return array{0: list<array<string, mixed>>, 1: array<string, string>}
     */
    private function queryProviders(string $isbn13): array
    {
        $providers = [
            BookSource::PROVIDER_GOOGLE_BOOKS => $this->google,
            BookSource::PROVIDER_OPEN_LIBRARY => $this->openLibrary,
        ];

        $sources = [];
        $errors = [];

        foreach ($providers as $name => $service) {
            try {
                $providerKey = 'bibliographic-isbn:'.$name.':'.$isbn13;
                $cached = Cache::remember($providerKey, self::CACHE_TTL_SECONDS, fn () => ['result' => $service->findByIsbn($isbn13)]);
                $found = $cached['result'];
                if ($found !== null) {
                    $sources[] = $found;
                }
            } catch (ExternalLookupException $e) {
                $errors[$name] = $e->getMessage();
            }
        }

        return [$sources, $errors];
    }

    private function cacheKey(string $token): string
    {
        return 'book-lookup:'.$token;
    }
}
