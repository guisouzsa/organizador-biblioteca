<?php

namespace App\Http\Requests;

use App\Enums\CopyCondition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('loan.return') ?? false;
    }

    public function rules(): array
    {
        return [
            'copy_code' => ['required', 'string', 'max:60'],
            'condition' => ['nullable', Rule::enum(CopyCondition::class)],
            'hint_borrower_id' => ['nullable', 'integer', Rule::exists('borrowers', 'id')],
        ];
    }

    public function attributes(): array
    {
        return ['copy_code' => 'código do exemplar', 'condition' => 'conservação'];
    }
}
