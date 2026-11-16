<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Combina e compara metadados vindos de fontes diferentes.
 *
 * As fontes são aplicadas na ordem recebida: o primeiro valor não vazio de
 * cada campo prevalece. Nenhum valor é inventado.
 */
class MetadataComparisonService
{
    /** Campos comparados entre fontes e seus rótulos. */
    public const COMPARED_FIELDS = [
        'title' => 'Título',
        'subtitle' => 'Subtítulo',
        'authors' => 'Autores',
        'publisher' => 'Editora',
        'publication_year' => 'Ano de publicação',
        'pages' => 'Páginas',
    ];

    private const MERGED_FIELDS = [
        'title', 'subtitle', 'authors', 'publisher', 'isbn10', 'isbn13', 'edition',
        'publication_year', 'pages', 'description', 'cover_url', 'language', 'categories',
    ];

    /**
     * @param  list<array<string, mixed>>  $sources  resultados normalizados, por ordem de prioridade
     * @return array<string, mixed>
     */
    public function merge(array $sources): array
    {
        $merged = array_fill_keys(self::MERGED_FIELDS, null);
        $merged['authors'] = [];
        $merged['categories'] = [];

        foreach ($sources as $source) {
            foreach (self::MERGED_FIELDS as $field) {
                if ($this->isEmpty($merged[$field] ?? null) && ! $this->isEmpty($source[$field] ?? null)) {
                    $merged[$field] = $source[$field];
                }
            }
        }

        return $merged;
    }

    /**
     * Campos em que duas ou mais fontes trazem valores diferentes (após normalização).
     *
     * @param  list<array<string, mixed>>  $sources
     * @return array<string, array{label: string, values: array<string, string>}>
     */
    public function divergences(array $sources): array
    {
        $divergences = [];

        foreach (self::COMPARED_FIELDS as $field => $label) {
            $values = [];
            foreach ($sources as $source) {
                $value = $source[$field] ?? null;
                if (! $this->isEmpty($value)) {
                    $values[$source['provider']] = $value;
                }
            }

            if (count($values) < 2) {
                continue;
            }

            $comparable = array_unique(array_map(fn ($v) => $this->comparable($field, $v), $values));
            if (count($comparable) > 1) {
                $divergences[$field] = [
                    'label' => $label,
                    'values' => array_map(fn ($v) => is_array($v) ? implode('; ', $v) : (string) $v, $values),
                ];
            }
        }

        return $divergences;
    }

    private function comparable(string $field, mixed $value): string
    {
        if ($field === 'authors') {
            $names = array_map(fn ($n) => $this->canonical((string) $n), (array) $value);
            sort($names);

            return implode('|', $names);
        }

        return $this->canonical((string) $value);
    }

    /** minúsculas, sem acentos, pontuação ou espaços repetidos. */
    private function canonical(string $text): string
    {
        $text = Str::lower(Str::ascii($text));
        $text = preg_replace('/[^a-z0-9]+/', ' ', $text) ?? '';

        return trim($text);
    }

    private function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }
}
