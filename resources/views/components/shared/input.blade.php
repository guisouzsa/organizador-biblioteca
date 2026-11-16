@props(['name', 'label', 'value' => '', 'type' => 'text', 'hint' => null, 'required' => false, 'list' => null])

@php($id = 'f-'.$name)

<div {{ $attributes->only('class') }}>
    <label for="{{ $id }}" class="block text-sm font-medium text-slate-700">
        {{ $label }}@if ($required) <span class="text-rose-600" aria-hidden="true">*</span>@endif
    </label>
    <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($name, $value) }}"
           @if ($required) required @endif
           @if ($list) list="{{ $list }}" @endif
           @if ($hint) aria-describedby="{{ $id }}-hint" @endif
           @error($name) aria-invalid="true" @enderror
           {{ $attributes->except('class') }}
           class="mt-1 block w-full rounded-md border-0 bg-white px-3 py-2 text-sm shadow-sm ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 @error($name) ring-rose-400 @enderror">
    @if ($hint)
        <p id="{{ $id }}-hint" class="mt-1 text-xs text-slate-500">{{ $hint }}</p>
    @endif
    @error($name)
        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
    @enderror
</div>
