@props(['name', 'label', 'value' => '', 'type' => 'text', 'hint' => null, 'required' => false, 'list' => null])

@php($id = 'f-'.$name)

<div {{ $attributes->only('class') }}>
    <label for="{{ $id }}" class="mb-1 block text-sm font-medium text-slate-800">
        {{ $label }}@if ($required) <span class="required-mark" aria-hidden="true">*</span>@endif
    </label>
    <div @class(['password-field' => $type === 'password'])>
    <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($name, $value) }}"
           @if ($required) required @endif
           @if ($list) list="{{ $list }}" @endif
           aria-describedby="{{ $hint ? $id.'-hint ' : '' }}{{ $errors->has($name) ? $id.'-error' : '' }}"
           @error($name) aria-invalid="true" @enderror
           {{ $attributes->except('class') }}
           @if ($type === 'password') data-password-input @endif
           @class(['field', 'password-field__input' => $type === 'password'])>
    @if ($type === 'password')
        <button type="button" class="password-field__toggle" data-password-toggle aria-controls="{{ $id }}" aria-pressed="false" aria-label="Mostrar {{ strtolower($label) }}">
            <span data-password-label>Mostrar</span>
        </button>
    @endif
    </div>
    @if ($hint)
        <p id="{{ $id }}-hint" class="mt-1 text-xs text-slate-500">{{ $hint }}</p>
    @endif
    @error($name)
        <p id="{{ $id }}-error" role="alert" class="mt-1 flex items-center gap-1 text-xs font-medium text-rose-700"><x-shared.icon name="alert" class="h-3.5 w-3.5" />{{ $message }}</p>
    @enderror
</div>
