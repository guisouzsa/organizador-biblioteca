@props(['title', 'text' => null, 'icon' => 'list'])

<div class="rounded-lg border border-dashed border-slate-300 px-6 py-10 text-center">
    <x-shared.icon :name="$icon" class="mx-auto h-7 w-7 text-slate-400" />
    <p class="mt-3 font-serif text-lg">{{ $title }}</p>
    @if ($text)<p class="mx-auto mt-1 max-w-sm text-sm text-slate-600">{{ $text }}</p>@endif
    @if (! $slot->isEmpty())<div class="mt-5 flex flex-wrap justify-center gap-2">{{ $slot }}</div>@endif
</div>
