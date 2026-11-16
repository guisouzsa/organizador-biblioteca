@props(['tone' => 'slate'])

@php
    $tones = [
        'slate' => 'bg-slate-100 text-slate-700',
        'emerald' => 'bg-emerald-50 text-emerald-700',
        'amber' => 'bg-amber-50 text-amber-800',
        'rose' => 'bg-rose-50 text-rose-700',
        'sky' => 'bg-sky-50 text-sky-700',
        'indigo' => 'bg-indigo-50 text-indigo-700',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium '.($tones[$tone] ?? $tones['slate'])]) }}>{{ $slot }}</span>
