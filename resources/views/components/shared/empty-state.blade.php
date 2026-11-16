@props(['title', 'text' => null, 'icon' => 'list'])

<div class="rounded-xl border border-dashed border-slate-300 bg-white p-10 text-center">
    <span class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-slate-100 text-slate-500"><x-shared.icon :name="$icon" class="h-6 w-6" /></span>
    <p class="mt-3 font-medium">{{ $title }}</p>
    @if ($text)<p class="mt-1 text-sm text-slate-600">{{ $text }}</p>@endif
    @if (! $slot->isEmpty())<div class="mt-4 flex flex-wrap justify-center gap-2">{{ $slot }}</div>@endif
</div>
