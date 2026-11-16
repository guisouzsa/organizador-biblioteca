<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LookupIsbnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // A validade do ISBN é verificada pelo BookLookupService (status "invalid").
        return ['isbn' => ['required', 'string', 'max:40']];
    }
}
