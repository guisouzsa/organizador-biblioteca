<?php

namespace App\Http\Requests;

use App\Enums\CopyCondition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterCopiesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('copy.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')->where('active', true)],
            'condition' => ['required', Rule::enum(CopyCondition::class)],
            'acquired_at' => ['nullable', 'date', 'before_or_equal:today'],
            'acquisition_source' => ['nullable', 'string', 'max:120'],
            'accession_number' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return ['quantity' => 'quantidade', 'location_id' => 'localização', 'condition' => 'conservação',
            'acquired_at' => 'data de aquisição', 'acquisition_source' => 'origem', 'accession_number' => 'tombo', 'notes' => 'observações'];
    }
}
