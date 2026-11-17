@php
    $types = collect(\App\Enums\BorrowerType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()])->all();
@endphp

<form method="POST" action="{{ $action }}" class="space-y-4" novalidate data-once>
    @csrf
    <p class="required-note"><span class="required-mark" aria-hidden="true">*</span> indica campo obrigatório.</p>
    @if ($method !== 'POST') @method($method) @endif

    @if ($errors->any())<div role="alert" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">Corrija os campos destacados.</div>@endif

    <div class="grid gap-4 sm:grid-cols-2">
        <x-shared.select name="type" label="Tipo de leitor" :options="$types" :value="$borrower?->type->value ?? 'student'" required />
        <x-shared.input name="registration_number" label="Matrícula / registro funcional" :value="$borrower?->registration_number ?? ''" hint="Única por tipo de leitor." />
        <x-shared.input name="name" label="Nome completo" :value="$borrower?->name ?? ''" required class="sm:col-span-2" />
        <x-shared.input name="class_group" label="Turma / série" :value="$borrower?->class_group ?? ''" />
        @can('borrower.contacts')
            <x-shared.input name="phone" label="Telefone" :value="$borrower?->phone ?? ''" />
            <x-shared.input name="email" label="E-mail" type="email" :value="$borrower?->email ?? ''" class="sm:col-span-2" hint="Opcional. Colete apenas o necessário, principalmente de menores de idade." />
        @endcan
    </div>

    <div class="form-actions flex gap-3 border-t border-slate-200 pt-5" data-form-actions>
        <button type="submit" class="btn btn-primary">{{ $submitLabel }}</button>
        <a href="{{ $borrower ? route('borrowers.show', $borrower) : route('borrowers.index') }}" class="btn btn-secondary">Cancelar</a>
    </div>
</form>
