@props(['name', 'label', 'options' => [], 'value' => '', 'placeholder' => null, 'required' => false, 'hint' => null])

@php($id = 'f-'.$name)

<div {{ $attributes->only('class') }}>
    <label for="{{ $id }}" class="block text-sm font-medium text-slate-700">
        {{ $label }}@if ($required) <span class="text-rose-600" aria-hidden="true">*</span>@endif
    </label>
    <select id="{{ $id }}" name="{{ $name }}" @if ($required) required @endif {{ $attributes->except('class') }}
            class="mt-1 block w-full rounded-md border-0 bg-white px-3 py-2 text-sm shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 @error($name) ring-rose-400 @enderror">
        @if ($placeholder !== null)<option value="">{{ $placeholder }}</option>@endif
        @foreach ($options as $key => $text)
            <option value="{{ $key }}" @selected((string) old($name, $value) === (string) $key)>{{ $text }}</option>
        @endforeach
    </select>
    @if ($hint)<p class="mt-1 text-xs text-slate-500">{{ $hint }}</p>@endif
    @error($name)<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
</div>
