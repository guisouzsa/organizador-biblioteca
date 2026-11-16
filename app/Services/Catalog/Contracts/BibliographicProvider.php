<?php

namespace App\Services\Catalog\Contracts;

interface BibliographicProvider
{
    public function name(): string;

    /** @return array<string, mixed>|null */
    public function findByIsbn(string $isbn13): ?array;

    /** @return list<array<string, mixed>> */
    public function search(string $query, int $limit = 10): array;
}
