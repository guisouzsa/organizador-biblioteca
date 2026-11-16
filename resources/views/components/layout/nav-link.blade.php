@props(['route', 'icon' => 'list', 'active' => false])

<a href="{{ route($route) }}" @if ($active) aria-current="page" @endif
   class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ $active ? 'bg-indigo-50 text-indigo-700' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
    <x-shared.icon :name="$icon" class="h-5 w-5 shrink-0" />
    <span class="truncate">{{ $slot }}</span>
</a>
