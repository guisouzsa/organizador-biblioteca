@props(['book', 'class' => 'h-40 w-28'])

@if ($book->cover_url)
    <img src="{{ $book->cover_url }}" alt="Capa de {{ $book->title }}" loading="lazy" referrerpolicy="no-referrer"
         {{ $attributes->merge(['class' => $class.' rounded-md bg-slate-100 object-cover shadow-sm']) }}>
@else
    <div role="img" aria-label="Sem capa"
         {{ $attributes->merge(['class' => $class.' flex items-center justify-center rounded-md bg-gradient-to-br from-slate-200 to-slate-300 p-2 text-center text-xs font-medium text-slate-600']) }}>
        {{ \Illuminate\Support\Str::limit($book->title, 40) }}
    </div>
@endif
