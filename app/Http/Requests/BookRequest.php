<?php

namespace App\Http\Requests;

use App\DTOs\Catalog\BookData;
use App\Services\Catalog\IsbnValidatorService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Regras comuns de cadastro e edição de livros. */
abstract class BookRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $parsed = app(IsbnValidatorService::class)->parse($this->input('isbn'));

        $this->merge([
            'isbn13' => $parsed['isbn13'],
            'isbn10' => $parsed['isbn10'],
            'authors' => $this->normalizeAuthors($this->input('authors')),
            'collection_group' => $this->input('collection_group', 'general'),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'authors' => ['nullable', 'string', 'max:1000'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'isbn' => ['nullable', 'string', 'max:40', function (string $attribute, mixed $value, \Closure $fail) {
                if (filled($value)) {
                    $parsed = app(IsbnValidatorService::class)->parse($value);
                    if (! $parsed['valid']) {
                        $fail($parsed['error']);
                    }
                }
            }],
            'edition' => ['nullable', 'string', 'max:100'],
            'publication_year' => ['nullable', 'integer', 'min:1400', 'max:'.(now()->year + 1)],
            'pages' => ['nullable', 'integer', 'min:1', 'max:20000'],
            'description' => ['nullable', 'string', 'max:10000'],
            'cover_url' => ['nullable', 'url:http,https', 'max:2048'],
            'language' => ['nullable', 'string', 'max:10'],
            'format' => ['nullable', 'string', 'max:50'],
            'collection_group' => ['required', Rule::in(['school', 'general'])],
            'material_type' => ['nullable', 'string', 'max:40'],
            'audience' => ['nullable', 'string', 'max:60'],
            'education_stage' => ['nullable', 'string', 'max:60'],
            'work_id' => ['nullable', 'integer', Rule::exists('book_works', 'id')],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['integer', Rule::exists('categories', 'id')],
            'new_categories' => ['nullable', 'string', 'max:500'],
            'collection_id' => ['nullable', 'integer', Rule::exists('collections', 'id')],
            'lookup_token' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'título', 'subtitle' => 'subtítulo', 'authors' => 'autores', 'publisher' => 'editora',
            'isbn' => 'ISBN', 'edition' => 'edição', 'publication_year' => 'ano de publicação',
            'pages' => 'páginas', 'description' => 'descrição', 'cover_url' => 'URL da capa',
            'language' => 'idioma', 'format' => 'formato', 'collection_group' => 'grupo do acervo',
            'material_type' => 'tipo de material', 'audience' => 'público',
            'education_stage' => 'etapa de ensino', 'work_id' => 'obra', 'collection_id' => 'coleção',
        ];
    }

    public function toDto(): BookData
    {
        return BookData::fromArray([
            ...$this->validated(),
            'isbn10' => $this->input('isbn10'),
            'isbn13' => $this->input('isbn13'),
        ]);
    }

    /** "A; B" ou uma linha por autor → "A; B". */
    private function normalizeAuthors(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $names = preg_split('/[;\r\n]+/', $value) ?: [];
        $names = array_values(array_unique(array_filter(array_map('trim', $names))));

        return $names === [] ? null : implode('; ', $names);
    }
}
