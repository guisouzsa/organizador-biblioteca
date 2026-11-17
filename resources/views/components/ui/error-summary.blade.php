{{-- Resumo de validação: só aparece com 2+ campos inválidos; cada item leva ao campo. Um único erro fica só junto ao campo. --}}
@if (isset($errors) && $errors->count() > 1)
    @php($keys = array_slice(array_values(array_unique(array_map(fn ($k) => explode('.', $k)[0], $errors->keys()))), 0, 8))
    <div id="error-summary" role="alert" tabindex="-1" class="alert alert--error mb-6">
        <x-shared.icon name="xcircle" class="alert__icon" />
        <div>
            <p class="font-semibold">Corrija {{ count($keys) === 1 ? 'o campo destacado' : 'os campos destacados' }} para continuar</p>
            <ul class="mt-1 space-y-0.5">
                @foreach ($keys as $key)
                    <li><a href="#f-{{ $key }}" class="underline">{{ $errors->first($key) }}</a></li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
