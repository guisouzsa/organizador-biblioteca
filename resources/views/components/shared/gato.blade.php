{{--
    Imagem decorativa de gato. Sempre alt="" + aria-hidden: leitores de tela ignoram.
    Uso: <x-shared.gato nome="dormindo" class="h-28 w-auto" />
    Nomes: dormindo | espiando | lapis | patinha
--}}
@props(['nome' => 'dormindo'])
@php
    $arquivo = match ($nome) {
        'dormindo' => 'gato-dormindo.svg',
        'espiando' => 'gato-espiando.svg',
        'lapis' => 'lapis-gato.svg',
        'patinha' => 'patinha.svg',
        default => 'gato-dormindo.svg',
    };
@endphp
<img src="{{ asset('decor/'.$arquivo) }}" alt="" aria-hidden="true" loading="lazy" decoding="async" {{ $attributes->merge(['class' => 'pointer-events-none select-none']) }}>
