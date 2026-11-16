<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('settings.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'library_name' => ['required', 'string', 'max:120'],
            'barcode_prefix' => ['required', 'string', 'regex:/^[A-Z0-9]{1,8}$/'],
            'accession_required' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return ['barcode_prefix.regex' => 'Use de 1 a 8 letras maiúsculas ou números, sem espaços.'];
    }

    public function attributes(): array
    {
        return ['library_name' => 'nome da biblioteca', 'barcode_prefix' => 'prefixo do código de barras'];
    }
}
