<?php

namespace App\Http\Requests;

class StoreBookRequest extends BookRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('catalog.create') ?? false;
    }

    public function rules(): array
    {
        // A duplicação por ISBN é tratada no controller para oferecer abrir/atualizar o registro existente.
        return parent::rules() + [
            'confirm_update' => ['nullable', 'boolean'],
            'confirm_distinct' => ['nullable', 'boolean'],
        ];
    }
}
