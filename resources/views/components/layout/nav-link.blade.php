@props(['route', 'icon' => 'list', 'active' => false])

<a href="{{ route($route) }}" @if ($active) aria-current="page" @endif
   class="relative flex min-h-11 items-center gap-3 rounded-md px-3 py-2 text-sm {{ $active ? 'bg-slate-100 font-semibold text-slate-900' : 'font-medium text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}">
    @if ($active)<span class="absolute inset-y-2 left-0 w-0.5 rounded bg-accent"></span>@endif
    <x-shared.icon :name="$icon" class="h-5 w-5 shrink-0 {{ $active ? 'text-indigo-700' : '' }}" />
    <span class="truncate">{{ $slot }}</span>
</a>
