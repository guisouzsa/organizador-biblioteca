<?php

namespace App\Http\Requests;

use App\Enums\BorrowerType;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Regras por tipo de leitor. Não há valores padrão: se a regra do tipo está habilitada,
 * prazo e as duas decisões sobre atraso precisam ser escolhidos explicitamente.
 */
class LoanRulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('settings.manage') ?? false;
    }

    public function rules(): array
    {
        $rules = [];
        foreach (BorrowerType::cases() as $type) {
            $p = "rules.{$type->value}";
            $on = "required_if:{$p}.enabled,1";
            $rules["{$p}.enabled"] = ['nullable', 'boolean'];
            $rules["{$p}.loan_days"] = [$on, 'nullable', 'integer', 'min:1', 'max:365'];
            $rules["{$p}.max_active_loans"] = ['nullable', 'integer', 'min:1', 'max:200'];
            $rules["{$p}.max_renewals"] = ['nullable', 'integer', 'min:0', 'max:50'];
            $rules["{$p}.renewal_days"] = ['nullable', 'integer', 'min:1', 'max:365'];
            $rules["{$p}.renew_when_overdue"] = [$on, 'nullable', 'in:0,1'];
            $rules["{$p}.block_when_overdue"] = [$on, 'nullable', 'in:0,1'];
            $rules["{$p}.reservation_hold_hours"] = ['nullable', 'integer', 'min:1', 'max:720'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'rules.*.loan_days.required_if' => 'Informe o prazo de empréstimo (em dias).',
            'rules.*.renew_when_overdue.required_if' => 'Escolha se é permitido renovar empréstimo vencido.',
            'rules.*.block_when_overdue.required_if' => 'Escolha se leitores com atraso ficam bloqueados.',
        ];
    }
}
