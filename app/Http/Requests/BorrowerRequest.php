<?php

namespace App\Http\Requests;

use App\Enums\BorrowerType;
use App\Models\Borrower;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BorrowerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $borrower = $this->route('borrower');

        return $borrower
            ? ($this->user()?->can('update', $borrower) ?? false)
            : ($this->user()?->can('create', Borrower::class) ?? false);
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(BorrowerType::class)],
            'name' => ['required', 'string', 'max:255'],
            'registration_number' => ['nullable', 'string', 'max:40'],
            'class_group' => ['nullable', 'string', 'max:60'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
        ];
    }

    public function attributes(): array
    {
        return ['type' => 'tipo', 'name' => 'nome', 'registration_number' => 'matrícula', 'class_group' => 'turma', 'email' => 'e-mail', 'phone' => 'telefone'];
    }
}
