@props(['label', 'value', 'icon' => 'list', 'tone' => 'indigo', 'href' => null, 'hint' => null])

@php
    $tones = ['indigo' => 'bg-indigo-50 text-indigo-600', 'emerald' => 'bg-emerald-50 text-emerald-600', 'amber' => 'bg-amber-50 text-amber-600', 'rose' => 'bg-rose-50 text-rose-600', 'sky' => 'bg-sky-50 text-sky-600'];
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" @endif class="flex items-center gap-4 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200 {{ $href ? 'transition hover:ring-indigo-300' : '' }}">
    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-lg {{ $tones[$tone] ?? $tones['indigo'] }}"><x-shared.icon :name="$icon" class="h-6 w-6" /></span>
    <span class="min-w-0">
        <span class="block text-2xl font-semibold leading-none tracking-tight">{{ $value }}</span>
        <span class="mt-1 block truncate text-sm text-slate-600">{{ $label }}</span>
        @if ($hint)<span class="block text-xs text-slate-400">{{ $hint }}</span>@endif
    </span>
</{{ $tag }}>
