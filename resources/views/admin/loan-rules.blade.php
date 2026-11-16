@extends('layouts.app')

@section('title', 'Regras de empréstimo — '.config('app.name'))

@php
    $inp = 'field';
@endphp

@section('content')
    <x-shared.page-header title="Regras de empréstimo" subtitle="Defina as regras da sua escola para cada tipo de leitor. O sistema não sugere valores: sem regra, não há empréstimo." />

    <div class="mb-6 rounded-lg bg-slate-50 p-4 text-sm text-slate-700">
        As regras valem para <strong>novos</strong> empréstimos. Empréstimos já registrados mantêm o prazo original.
        Não há cobrança de multas no sistema.
    </div>

    <form method="POST" action="{{ route('loan-rules.update') }}" class="space-y-6" novalidate data-once>
        @csrf @method('PUT')
        @if ($errors->any())<div role="alert" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">Corrija os campos destacados.</div>@endif

        @foreach ($types as $type)
            @php($t = $type->value)
            @php($rule = $rules[$t] ?? null)
            @php($enabled = (bool) old("rules.$t.enabled", $rule !== null))
            <fieldset class="panel p-5">
                <legend class="px-1 text-base font-semibold">{{ $type->label() }}</legend>
                <label class="mb-4 flex items-center gap-2 text-sm font-medium">
                    <input type="checkbox" name="rules[{{ $t }}][enabled]" value="1" @checked($enabled) class="rounded border-slate-300 text-indigo-600">
                    Permitir empréstimos para este tipo de leitor
                </label>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <label for="ld-{{ $t }}" class="block text-sm font-medium">Prazo de empréstimo (dias) <span class="text-rose-600">*</span></label>
                        <input id="ld-{{ $t }}" type="number" min="1" max="365" name="rules[{{ $t }}][loan_days]" value="{{ old("rules.$t.loan_days", $rule?->loan_days) }}" class="{{ $inp }}">
                        @error("rules.$t.loan_days")<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="ma-{{ $t }}" class="block text-sm font-medium">Máx. de empréstimos simultâneos</label>
                        <input id="ma-{{ $t }}" type="number" min="1" name="rules[{{ $t }}][max_active_loans]" value="{{ old("rules.$t.max_active_loans", $rule?->max_active_loans) }}" class="{{ $inp }}">
                        <p class="mt-1 text-xs text-slate-500">Vazio = sem limite.</p>
                    </div>
                    <div>
                        <label for="mr-{{ $t }}" class="block text-sm font-medium">Máx. de renovações</label>
                        <input id="mr-{{ $t }}" type="number" min="0" name="rules[{{ $t }}][max_renewals]" value="{{ old("rules.$t.max_renewals", $rule?->max_renewals) }}" class="{{ $inp }}">
                        <p class="mt-1 text-xs text-slate-500">0 = não renova · vazio = sem limite.</p>
                    </div>
                    <div>
                        <label for="rd-{{ $t }}" class="block text-sm font-medium">Dias por renovação</label>
                        <input id="rd-{{ $t }}" type="number" min="1" name="rules[{{ $t }}][renewal_days]" value="{{ old("rules.$t.renewal_days", $rule?->renewal_days) }}" class="{{ $inp }}">
                        <p class="mt-1 text-xs text-slate-500">Vazio = igual ao prazo de empréstimo.</p>
                    </div>
                    <div>
                        <label for="ro-{{ $t }}" class="block text-sm font-medium">Renovar empréstimo vencido? <span class="text-rose-600">*</span></label>
                        <select id="ro-{{ $t }}" name="rules[{{ $t }}][renew_when_overdue]" class="{{ $inp }}">
                            <option value="">Escolha…</option>
                            <option value="1" @selected((string) old("rules.$t.renew_when_overdue", $rule ? (int) $rule->renew_when_overdue : '') === '1')>Sim, permitir</option>
                            <option value="0" @selected((string) old("rules.$t.renew_when_overdue", $rule ? (int) $rule->renew_when_overdue : '') === '0')>Não</option>
                        </select>
                        @error("rules.$t.renew_when_overdue")<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="bo-{{ $t }}" class="block text-sm font-medium">Bloquear novos empréstimos com atraso? <span class="text-rose-600">*</span></label>
                        <select id="bo-{{ $t }}" name="rules[{{ $t }}][block_when_overdue]" class="{{ $inp }}">
                            <option value="">Escolha…</option>
                            <option value="1" @selected((string) old("rules.$t.block_when_overdue", $rule ? (int) $rule->block_when_overdue : '') === '1')>Sim, bloquear</option>
                            <option value="0" @selected((string) old("rules.$t.block_when_overdue", $rule ? (int) $rule->block_when_overdue : '') === '0')>Não: apenas avisar</option>
                        </select>
                        @error("rules.$t.block_when_overdue")<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="rh-{{ $t }}" class="block text-sm font-medium">Retenção de reserva (horas)</label>
                        <input id="rh-{{ $t }}" type="number" min="1" name="rules[{{ $t }}][reservation_hold_hours]" value="{{ old("rules.$t.reservation_hold_hours", $rule?->reservation_hold_hours) }}" class="{{ $inp }}">
                        <p class="mt-1 text-xs text-slate-500">Quanto tempo o exemplar fica separado para quem reservou. Vazio = sem prazo.</p>
                    </div>
                </div>
            </fieldset>
        @endforeach

        <p class="text-xs text-slate-500">Desmarcar um tipo remove a regra e bloqueia novos empréstimos para ele (os empréstimos existentes continuam).</p>
        <button type="submit" class="btn btn-primary">Salvar regras</button>
    </form>
@endsection
