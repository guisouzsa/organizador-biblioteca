@props(['tone' => 'slate'])

{{-- O estado nunca depende só da cor: cada tom tem uma marca de forma própria (círculo, meio-círculo, quadrado, anel, losango, traço). --}}
@php
    $tones = [
        'slate' => ['bg-slate-100 text-slate-700', 'M4 8h8'],
        'emerald' => ['bg-emerald-50 text-emerald-800', 'M8 3a5 5 0 1 0 0 10A5 5 0 0 0 8 3z'],
        'amber' => ['bg-amber-50 text-amber-800', 'M8 3a5 5 0 1 0 0 10zM8 3a5 5 0 0 1 0 10z'],
        'rose' => ['bg-rose-50 text-rose-800', 'M3.5 3.5h9v9h-9z'],
        'sky' => ['bg-sky-50 text-sky-700', 'M8 8m-4 0a4 4 0 1 0 8 0a4 4 0 1 0-8 0'],
        'indigo' => ['bg-indigo-50 text-indigo-800', 'M8 2.5 13.5 8 8 13.5 2.5 8z'],
    ];
    [$cls, $glyph] = $tones[$tone] ?? $tones['slate'];
    $filled = in_array($tone, ['emerald', 'rose', 'indigo']);
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-sm px-2 py-0.5 text-xs font-medium '.$cls]) }}>
    <svg viewBox="0 0 16 16" class="h-2.5 w-2.5 shrink-0" aria-hidden="true" fill="{{ $filled ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.6"><path d="{{ $glyph }}"/></svg>
    {{ $slot }}
</span>
