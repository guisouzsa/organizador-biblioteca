<?php

namespace App\Http\Controllers;

use App\Enums\CopyCondition;
use App\Enums\CopyStatus;
use App\Http\Requests\RegisterCopiesRequest;
use App\Http\Requests\UpdateCopyRequest;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Location;
use App\Services\Inventory\CopyBarcodeService;
use App\Services\Inventory\CopyLifecycleService;
use App\Services\Inventory\CopyRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CopyController extends Controller
{
    public function __construct(
        private CopyBarcodeService $barcodes,
        private CopyRegistrationService $registration,
        private CopyLifecycleService $lifecycle,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $this->authorize('viewAny', BookCopy::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'livro' => ['nullable', 'integer'],
            'local' => ['nullable', 'integer'],
            'situacao' => ['nullable', Rule::in(['available', 'on_loan', 'held', 'maintenance', 'lost', 'written_off', 'no_location'])],
        ]);

        // Código de barras/tombo exato: abre direto a ficha do exemplar.
        if (filled($filters['q'] ?? null) && ($resolved = $this->barcodes->resolve($filters['q']))) {
            $redirect = redirect()->route('copies.show', $resolved['copy']);

            return $resolved['stale']
                ? $redirect->with('error', 'Etiqueta antiga: use a etiqueta atual '.$resolved['copy']->code().'.')
                : $redirect;
        }

        $like = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        $copies = BookCopy::query()
            ->with(['book', 'activeBarcode', 'location', 'activeLoan', 'heldReservation'])
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w
                ->whereHas('book', fn ($b) => $b->where('title', $like, "%{$term}%")->orWhere('authors', $like, "%{$term}%")->orWhere('isbn13', $like, "%{$term}%"))
                ->orWhereHas('barcodes', fn ($b) => $b->where('code', $like, "%{$term}%"))
                ->orWhere('accession_number', $like, "%{$term}%")))
            ->when($filters['livro'] ?? null, fn ($q, $id) => $q->where('book_id', $id))
            ->when($filters['local'] ?? null, fn ($q, $id) => $q->where('location_id', $id))
            ->when($filters['situacao'] ?? null, function ($q, $s) {
                return match ($s) {
                    'available' => $q->available(),
                    'on_loan' => $q->where('status', 'active')->whereHas('loans', fn ($l) => $l->whereNull('returned_at')),
                    'held' => $q->where('status', 'active')->whereHas('reservations', fn ($r) => $r->where('status', 'ready'))
                        ->whereDoesntHave('loans', fn ($l) => $l->whereNull('returned_at')),
                    'no_location' => $q->whereNull('location_id'),
                    default => $q->where('status', $s),
                };
            })
            ->orderByDesc('id')
            ->paginate(20)->withQueryString();

        return view('copies.index', [
            'copies' => $copies,
            'filters' => $filters,
            'locations' => Location::orderBy('name')->get(),
            'book' => ($filters['livro'] ?? null) ? Book::find($filters['livro']) : null,
        ]);
    }

    public function create(Book $book): View
    {
        $this->authorize('create', BookCopy::class);

        return view('copies.create', [
            'book' => $book,
            'locations' => Location::where('active', true)->orderBy('name')->get(),
            'conditions' => collect(CopyCondition::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all(),
        ]);
    }

    public function store(RegisterCopiesRequest $request, Book $book): RedirectResponse
    {
        $copies = $this->registration->register($book, (int) $request->validated('quantity'), $request->validated());

        return redirect()->route('copies.labels', ['ids' => $copies->pluck('id')->all(), 'novo' => 1])
            ->with('status', $copies->count().' exemplar(es) registrado(s): '.$copies->first()->activeBarcode->code.($copies->count() > 1 ? ' a '.$copies->last()->activeBarcode->code : '').'.');
    }

    public function show(BookCopy $copy): View
    {
        $this->authorize('view', $copy);

        $copy->load(['book', 'location.parent', 'barcodes', 'activeBarcode', 'activeLoan.borrower', 'heldReservation.borrower']);

        return view('copies.show', [
            'copy' => $copy,
            'situation' => $copy->situation(),
            'events' => $copy->events()->with(['user', 'loan.borrower'])->limit(60)->get(),
            'loans' => $copy->loans()->with('borrower')->latest('loaned_at')->limit(10)->get(),
            'locations' => Location::where('active', true)->orWhere('id', $copy->location_id)->orderBy('name')->get(),
            'conditions' => collect(CopyCondition::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all(),
            'statuses' => collect(CopyStatus::cases())->filter(fn ($s) => $copy->status->canTransitionTo($s))->values(),
        ]);
    }

    public function update(UpdateCopyRequest $request, BookCopy $copy): RedirectResponse
    {
        $this->lifecycle->update($copy, $request->validated());

        return redirect()->route('copies.show', $copy)->with('status', 'Exemplar atualizado.');
    }

    public function transition(Request $request, BookCopy $copy): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(CopyStatus::class)],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);
        $to = CopyStatus::from($data['status']);

        // Restaurar exemplar baixado é exclusivo do Administrador (RN-ACE-06).
        $this->authorize($copy->status === CopyStatus::WrittenOff ? 'restore' : 'transition', $copy);

        $this->lifecycle->transition($copy, $to, $data['reason'] ?? null);

        return redirect()->route('copies.show', $copy)->with('status', 'Situação alterada para “'.$to->label().'”.');
    }

    public function relabel(Request $request, BookCopy $copy): RedirectResponse
    {
        $this->authorize('create', BookCopy::class);
        $data = $request->validate(['reason' => ['required', 'string', 'max:120']], ['reason.required' => 'Informe o motivo da nova etiqueta.']);

        $copy = $this->lifecycle->relabel($copy, $data['reason']);

        return redirect()->route('copies.labels', ['ids' => [$copy->id]])->with('status', 'Nova etiqueta gerada: '.$copy->code().'. A etiqueta antiga continua identificando este exemplar.');
    }

    /** Página imprimível de etiquetas (código de barras Code 128 gerado no navegador). */
    public function labels(Request $request): View
    {
        $this->authorize('viewAny', BookCopy::class);

        $ids = collect($request->query('ids', []))->map(fn ($i) => (int) $i)->filter()->take(200);
        $copies = BookCopy::with(['book', 'activeBarcode', 'location'])->whereIn('id', $ids)->orderBy('id')->get();

        return view('copies.labels', ['copies' => $copies, 'fresh' => $request->boolean('novo')]);
    }

    /** JSON usado pela câmera/leitor no balcão e na busca de exemplares. */
    public function lookup(Request $request): JsonResponse
    {
        $this->authorize('viewAny', BookCopy::class);
        $request->validate(['code' => ['required', 'string', 'max:60']]);

        $resolved = $this->barcodes->resolve($request->input('code'));
        if (! $resolved) {
            return response()->json(['found' => false, 'message' => 'Código não encontrado. Confira o número ou busque o livro.'], 404);
        }

        $copy = $resolved['copy']->load(['book', 'activeBarcode', 'activeLoan', 'heldReservation']);
        $s = $copy->situation();

        return response()->json([
            'found' => true, 'copy_id' => $copy->id, 'code' => $copy->code(), 'title' => $copy->book->title,
            'situation' => $s['label'], 'stale_label' => $resolved['stale'], 'url' => route('copies.show', $copy),
        ]);
    }
}
