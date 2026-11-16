@props(['name', 'label', 'value' => '', 'rows' => 3, 'hint' => null])

@php($id = 'f-'.$name)

<div {{ $attributes->only('class') }}>
    <label for="{{ $id }}" class="block text-sm font-medium text-slate-700">{{ $label }}</label>
    <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $rows }}" {{ $attributes->except('class') }}
              class="mt-1 block w-full rounded-md border-0 bg-white px-3 py-2 text-sm shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 @error($name) ring-rose-400 @enderror">{{ old($name, $value) }}</textarea>
    @if ($hint)<p class="mt-1 text-xs text-slate-500">{{ $hint }}</p>@endif
    @error($name)<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
</div>
