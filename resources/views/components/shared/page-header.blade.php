@props(['title', 'subtitle' => null, 'eyebrow' => null])

<header class="mb-6 sm:mb-8">
    @if ($eyebrow)<p class="eyebrow mb-1">{{ $eyebrow }}</p>@endif
    <div class="flex flex-wrap items-end justify-between gap-x-6 gap-y-3">
        <div class="min-w-0">
            <h1 class="text-[1.75rem] leading-tight sm:text-4xl">{{ $title }}</h1>
            @if ($subtitle)
                <p class="mt-1.5 max-w-prose text-sm text-slate-600">{{ $subtitle }}</p>
            @endif
        </div>
        @if (! $slot->isEmpty())
            <div class="flex w-full flex-wrap gap-2 sm:w-auto">{{ $slot }}</div>
        @endif
    </div>
    <div class="rule mt-4"></div>
</header>
