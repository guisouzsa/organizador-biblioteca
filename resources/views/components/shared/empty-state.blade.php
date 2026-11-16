@props(['title', 'text' => null, 'icon' => 'list', 'gato' => null])

<div class="rounded-lg border border-dashed border-cinza-3 px-6 py-10 text-center">
    @if ($gato)
        <x-shared.gato :nome="$gato" class="mx-auto h-24 w-auto" />
    @else
        <x-shared.icon :name="$icon" class="mx-auto h-7 w-7 text-cinza-1" />
    @endif
    <p class="mt-3 font-display text-lg font-semibold">{{ $title }}</p>
    @if ($text)<p class="mx-auto mt-1 max-w-sm text-sm text-slate-600">{{ $text }}</p>@endif
    @if (! $slot->isEmpty())<div class="mt-5 flex flex-wrap justify-center gap-2">{{ $slot }}</div>@endif
</div>
