<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateBookRequest extends BookRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('catalog.update') ?? false;
    }

    public function rules(): array
    {
        $book = $this->route('book');

        return parent::rules() + [
            'isbn13' => ['nullable', Rule::unique('books', 'isbn13')->ignore($book)],
            'isbn10' => ['nullable', Rule::unique('books', 'isbn10')->ignore($book)],
            'version' => ['nullable', 'integer'],
            'confirm_isbn_change' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'isbn13.unique' => 'Já existe outro livro cadastrado com este ISBN.',
            'isbn10.unique' => 'Já existe outro livro cadastrado com este ISBN.',
        ];
    }
}
