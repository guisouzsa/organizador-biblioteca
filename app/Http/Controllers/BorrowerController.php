<?php

namespace App\Http\Controllers;

use App\Support\Feedback;
use App\Enums\BorrowerStatus;
use App\Enums\BorrowerType;
use App\Http\Requests\BorrowerRequest;
use App\Models\Borrower;
use App\Services\People\BorrowerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BorrowerController extends Controller
{
    public function __construct(private BorrowerService $borrowers) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Borrower::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(BorrowerStatus::class)],
            'type' => ['nullable', Rule::enum(BorrowerType::class)],
            'atraso' => ['nullable', 'boolean'],
        ]);

        $borrowers = Borrower::query()
            ->withCount(['loans as active_loans_count' => fn ($q) => $q->whereNull('returned_at')])
            ->withCount(['loans as overdue_loans_count' => fn ($q) => $q->overdue()])
            ->search($filters['q'] ?? null)
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['type'] ?? null, fn ($q, $v) => $q->where('type', $v))
            ->when($filters['atraso'] ?? false, fn ($q) => $q->whereHas('loans', fn ($l) => $l->overdue()))
            ->orderBy('name')->paginate(20)->withQueryString();

        return view('borrowers.index', ['borrowers' => $borrowers, 'filters' => $filters]);
    }

    public function create(): View
    {
        $this->authorize('create', Borrower::class);

        return view('borrowers.create');
    }

    public function store(BorrowerRequest $request): RedirectResponse
    {
        $borrower = $this->borrowers->create($request->validated());

        return redirect()->route('borrowers.show', $borrower)->with('status', 'Leitor cadastrado.')
            ->with('feedback', Feedback::success(
                'Leitor cadastrado',
                "{$borrower->name} foi cadastrado(a). O empréstimo depende das regras de circulação configuradas.",
                auth()->user()->can('loan.create') ? [['label' => 'Ir ao balcão', 'href' => route('circulation.desk', ['leitor' => $borrower->id]), 'variant' => 'secondary']] : [],
            ));
    }

    public function show(Borrower $borrower): View
    {
        $this->authorize('view', $borrower);

        return view('borrowers.show', [
            'borrower' => $borrower,
            'activeLoans' => $borrower->activeLoans()->with(['copy.book', 'copy.activeBarcode'])->orderBy('due_on')->get(),
            'history' => $borrower->loans()->whereNotNull('returned_at')->with(['copy.book', 'copy.activeBarcode'])->latest('returned_at')->limit(30)->get(),
            'reservations' => $borrower->reservations()->active()->with('book')->get(),
        ]);
    }

    public function edit(Borrower $borrower): View
    {
        $this->authorize('update', $borrower);

        return view('borrowers.edit', ['borrower' => $borrower]);
    }

    public function update(BorrowerRequest $request, Borrower $borrower): RedirectResponse
    {
        $this->borrowers->update($borrower, $request->validated());

        return redirect()->route('borrowers.show', $borrower)->with('status', 'Dados do leitor atualizados.');
    }

    public function status(Request $request, Borrower $borrower): RedirectResponse
    {
        $this->authorize('changeStatus', $borrower);
        $data = $request->validate([
            'status' => ['required', Rule::enum(BorrowerStatus::class)],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $status = BorrowerStatus::from($data['status']);
        $this->borrowers->changeStatus($borrower, $status, $data['reason'] ?? null);

        return redirect()->route('borrowers.show', $borrower)->with('status', 'Situação do leitor alterada para “'.$status->label().'”. O histórico foi preservado.');
    }

    public function anonymize(Borrower $borrower): RedirectResponse
    {
        $this->authorize('anonymize', $borrower);
        $this->borrowers->anonymize($borrower);

        return redirect()->route('borrowers.show', $borrower)->with('status', 'Dados pessoais removidos. O histórico de empréstimos foi mantido de forma anônima.');
    }
}
