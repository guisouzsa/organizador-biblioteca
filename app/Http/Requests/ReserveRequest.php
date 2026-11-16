<?php

namespace App\Http\Requests;

use App\Models\Reservation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReserveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Reservation::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'book_id' => ['required', 'integer', Rule::exists('books', 'id')],
            'borrower_q' => ['required', 'string', 'max:100'],
        ];
    }

    public function attributes(): array
    {
        return ['book_id' => 'livro', 'borrower_q' => 'matrícula ou nome do leitor'];
    }
}
