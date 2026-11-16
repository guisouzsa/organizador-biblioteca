@props(['tone' => 'info', 'title' => null])

{{-- Alerta persistente (problema que precisa continuar visível). Mesma identidade dos modais: ícone + título + texto. --}}
@php
    $icons = ['success' => 'check', 'info' => 'info', 'warning' => 'alert', 'error' => 'xcircle', 'denied' => 'lock'];
@endphp
<div role="{{ in_array($tone, ['error', 'warning'], true) ? 'alert' : 'status' }}" {{ $attributes->merge(['class' => 'alert alert--'.$tone]) }}>
    <x-shared.icon :name="$icons[$tone] ?? 'info'" class="alert__icon" />
    <div class="min-w-0">
        @if ($title)<p class="font-semibold">{{ $title }}</p>@endif
        <div class="{{ $title ? 'mt-0.5' : '' }}">{{ $slot }}</div>
    </div>
</div>
