<?php

namespace App\Http\Requests;

use App\Models\Loan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Loan::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'copy_code' => ['required', 'string', 'max:60'],
            'borrower_id' => ['required', 'integer', Rule::exists('borrowers', 'id')],
            'acknowledge_overdue' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['copy_code' => 'código do exemplar', 'borrower_id' => 'leitor'];
    }
}
