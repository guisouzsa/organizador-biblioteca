@props(['label', 'value', 'icon' => null, 'tone' => 'indigo', 'href' => null, 'hint' => null])

@php($tag = $href ? 'a' : 'div')

<{{ $tag }} @if ($href) href="{{ $href }}" @endif class="group block border-t border-slate-300 pt-3 {{ $href ? 'transition hover:border-slate-900' : '' }}">
    <span class="eyebrow block">{{ $label }}</span>
    <span class="mt-1 block font-serif text-4xl font-light leading-none tracking-tight tabular-nums {{ $tone === 'rose' && (int) $value > 0 ? 'text-rose-700' : '' }}">{{ $value }}</span>
    @if ($hint)<span class="mt-1.5 block text-xs text-slate-500">{{ $hint }}</span>@endif
</{{ $tag }}>
