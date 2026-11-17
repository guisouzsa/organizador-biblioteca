@props(['name', 'label', 'options' => [], 'value' => '', 'placeholder' => null, 'required' => false, 'hint' => null])

@php($id = 'f-'.$name)

<div {{ $attributes->only('class') }}>
    <label for="{{ $id }}" class="mb-1 block text-sm font-medium text-slate-800">
        {{ $label }}@if ($required) <span class="required-mark" aria-hidden="true">*</span>@endif
    </label>
    <select id="{{ $id }}" name="{{ $name }}" @if ($required) required @endif {{ $attributes->except('class') }}
            @error($name) aria-invalid="true" @enderror class="field">
        @if ($placeholder !== null)<option value="">{{ $placeholder }}</option>@endif
        @foreach ($options as $key => $text)
            <option value="{{ $key }}" @selected((string) old($name, $value) === (string) $key)>{{ $text }}</option>
        @endforeach
    </select>
    @if ($hint)<p class="mt-1 text-xs text-slate-500">{{ $hint }}</p>@endif
    @error($name)<p role="alert" class="mt-1 flex items-center gap-1 text-xs font-medium text-rose-700"><x-shared.icon name="alert" class="h-3.5 w-3.5" />{{ $message }}</p>@enderror
</div>
