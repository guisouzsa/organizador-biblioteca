@if (session('status'))
    <div role="status" data-autohide class="mb-6 flex items-start gap-3 rounded-md border-l-4 border-emerald-600 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
        <x-shared.icon name="check" class="mt-0.5 h-4 w-4 shrink-0" />
        <span>{{ session('status') }}</span>
    </div>
@endif

@if (session('error'))
    <div role="alert" class="mb-6 flex items-start gap-3 rounded-md border-l-4 border-rose-600 bg-rose-50 px-4 py-3 text-sm text-rose-900">
        <x-shared.icon name="alert" class="mt-0.5 h-4 w-4 shrink-0" />
        <div>
            <p>{{ session('error') }}</p>
            @if (session('error_context.book_id'))
                <a href="{{ route('books.show', session('error_context.book_id')) }}" class="mt-1 inline-block font-medium underline">Abrir registro correspondente</a>
            @endif
            @if (session('error_context.copy_id'))
                <a href="{{ route('copies.show', session('error_context.copy_id')) }}" class="mt-1 inline-block font-medium underline">Abrir exemplar correspondente</a>
            @endif
            @if (session('error_context.borrower_id'))
                <a href="{{ route('borrowers.show', session('error_context.borrower_id')) }}" class="mt-1 inline-block font-medium underline">Abrir ficha do leitor</a>
            @endif
        </div>
    </div>
@endif
