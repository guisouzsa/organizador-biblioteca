<?php

namespace App\Http\Requests;

use App\Services\IsbnValidatorService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Regras comuns de cadastro e edição de livros. */
abstract class BookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Autenticação/permissões serão tratadas em etapa própria.
    }

    protected function prepareForValidation(): void
    {
        $parsed = app(IsbnValidatorService::class)->parse($this->input('isbn'));

        $this->merge([
            'isbn13' => $parsed['isbn13'],
            'isbn10' => $parsed['isbn10'],
            'authors' => $this->normalizeAuthors($this->input('authors')),
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
            'categories' => ['nullable', 'array'],
            'categories.*' => ['integer', Rule::exists('categories', 'id')],
            'new_categories' => ['nullable', 'string', 'max:500'],
            'collection_name' => ['nullable', 'string', 'max:255'],
            'lookup_token' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'título',
            'subtitle' => 'subtítulo',
            'authors' => 'autores',
            'publisher' => 'editora',
            'isbn' => 'ISBN',
            'edition' => 'edição',
            'publication_year' => 'ano de publicação',
            'pages' => 'páginas',
            'description' => 'descrição',
            'cover_url' => 'URL da capa',
            'language' => 'idioma',
            'format' => 'formato',
        ];
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
