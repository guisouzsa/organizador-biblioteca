<?php

namespace App\Http\Requests;

use App\Enums\CopyCondition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCopyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('copy.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')],
            'condition' => ['required', Rule::enum(CopyCondition::class)],
            'acquired_at' => ['nullable', 'date', 'before_or_equal:today'],
            'acquisition_source' => ['nullable', 'string', 'max:120'],
            'accession_number' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return ['location_id' => 'localização', 'condition' => 'conservação', 'acquired_at' => 'data de aquisição',
            'acquisition_source' => 'origem', 'accession_number' => 'tombo', 'notes' => 'observações'];
    }
}
