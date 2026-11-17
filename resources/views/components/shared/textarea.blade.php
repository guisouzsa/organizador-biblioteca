@props(['name', 'label', 'value' => '', 'rows' => 3, 'hint' => null, 'required' => false])

@php($id = 'f-'.$name)

<div {{ $attributes->only('class') }}>
    <label for="{{ $id }}" class="mb-1 block text-sm font-medium text-slate-800">{{ $label }}@if ($required) <span class="required-mark" aria-hidden="true">*</span>@endif</label>
    <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $rows }}" @if ($required) required @endif {{ $attributes->except('class') }} @error($name) aria-invalid="true" @enderror class="field">{{ old($name, $value) }}</textarea>
    @if ($hint)<p class="mt-1 text-xs text-slate-500">{{ $hint }}</p>@endif
    @error($name)<p role="alert" class="mt-1 text-xs font-medium text-rose-700">{{ $message }}</p>@enderror
</div>
