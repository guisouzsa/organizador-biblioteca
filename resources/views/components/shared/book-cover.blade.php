@props(['book', 'class' => 'w-24'])

{{-- Proporção 2:3 fixa (sem salto de layout). Sem capa: lombada tipográfica com o título. --}}
@if ($book->cover_url)
    <img src="{{ $book->cover_url }}" alt="Capa de {{ $book->title }}" loading="lazy" decoding="async" width="200" height="300" referrerpolicy="no-referrer"
         {{ $attributes->merge(['class' => $class.' cover']) }}>
@else
    <div role="img" aria-label="Sem capa: {{ $book->title }}"
         {{ $attributes->merge(['class' => $class.' cover flex flex-col justify-between border-l-4 border-indigo-700 bg-slate-100 p-2.5']) }}>
        <span class="font-display text-[0.8rem] leading-snug text-slate-800">{{ \Illuminate\Support\Str::limit($book->title, 44) }}</span>
        <span class="eyebrow truncate !text-[0.55rem]">{{ \Illuminate\Support\Str::limit($book->authors ?? '', 28) }}</span>
    </div>
@endif
