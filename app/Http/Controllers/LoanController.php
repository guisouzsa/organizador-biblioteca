<?php

namespace App\Http\Controllers;

use App\Enums\CopyCondition;
use App\Http\Requests\CheckoutRequest;
use App\Http\Requests\ReturnRequest;
use App\Models\Borrower;
use App\Models\Loan;
use App\Services\Circulation\LoanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LoanController extends Controller
{
    public function __construct(private LoanService $loans) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Loan::class);

        $filters = $request->validate([
            'situacao' => ['nullable', Rule::in(['active', 'overdue', 'returned', 'all'])],
            'q' => ['nullable', 'string', 'max:100'],
            'de' => ['nullable', 'date'],
            'ate' => ['nullable', 'date'],
        ]);
        $situation = $filters['situacao'] ?? 'active';
        $like = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        $loans = Loan::query()
            ->with(['copy.book', 'copy.activeBarcode', 'borrower', 'loanedBy'])
            ->when($situation === 'active', fn ($q) => $q->active())
            ->when($situation === 'overdue', fn ($q) => $q->overdue())
            ->when($situation === 'returned', fn ($q) => $q->whereNotNull('returned_at'))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w
                ->whereHas('borrower', fn ($b) => $b->where('name', $like, "%{$term}%")->orWhere('registration_number', $like, "%{$term}%"))
                ->orWhereHas('copy.book', fn ($b) => $b->where('title', $like, "%{$term}%"))
                ->orWhereHas('copy.barcodes', fn ($b) => $b->where('code', $like, "%{$term}%"))))
            ->when($filters['de'] ?? null, fn ($q, $d) => $q->whereDate('loaned_at', '>=', $d))
            ->when($filters['ate'] ?? null, fn ($q, $d) => $q->whereDate('loaned_at', '<=', $d))
            ->orderByRaw('returned_at is null desc')->orderBy('due_on')->orderByDesc('id')
            ->paginate(20)->withQueryString();

        return view('loans.index', ['loans' => $loans, 'filters' => $filters, 'situation' => $situation]);
    }

    public function show(Loan $loan): View
    {
        $this->authorize('view', $loan);
        $loan->load(['copy.book', 'copy.activeBarcode', 'borrower', 'loanedBy', 'returnedBy', 'renewals.renewedBy', 'rule']);

        return view('loans.show', ['loan' => $loan]);
    }

    public function store(CheckoutRequest $request): RedirectResponse
    {
        $borrower = Borrower::findOrFail($request->validated('borrower_id'));

        $loan = $this->loans->checkout($request->validated('copy_code'), $borrower, $request->boolean('acknowledge_overdue'));

        return redirect()->route('circulation.desk', ['leitor' => $borrower->id])
            ->with('status', "Empréstimo registrado: {$loan->copy->book->title} ({$loan->copy->code()}). Devolver até ".$loan->due_on->format('d/m/Y').'.');
    }

    public function return(ReturnRequest $request): RedirectResponse
    {
        $hint = $request->filled('hint_borrower_id') ? Borrower::find($request->validated('hint_borrower_id')) : null;
        $condition = $request->filled('condition') ? CopyCondition::from($request->validated('condition')) : null;

        $result = $this->loans->return($request->validated('copy_code'), $condition, $hint);

        $message = "Devolução registrada: {$result['loan']->copy->book->title} ({$result['loan']->copy->code()}).";
        if ($result['days_overdue'] > 0) {
            $message .= " Devolvido com {$result['days_overdue']} dia(s) de atraso.";
        }
        if ($result['held_for']) {
            $message .= " ATENÇÃO: reservado para {$result['held_for']->name} — separe o exemplar.";
        }

        return redirect()->route('circulation.desk', $hint ? ['leitor' => $hint->id] : [])->with('status', $message);
    }

    public function renew(Loan $loan): RedirectResponse
    {
        $this->authorize('renew', $loan);
        $loan = $this->loans->renew($loan);

        return back()->with('status', 'Empréstimo renovado. Nova data de devolução: '.$loan->due_on->format('d/m/Y').'.');
    }

    public function void(Request $request, Loan $loan): RedirectResponse
    {
        $this->authorize('void', $loan);
        $data = $request->validate(['reason' => ['required', 'string', 'max:200']], ['reason.required' => 'Informe o motivo do estorno.']);

        $this->loans->void($loan, $data['reason']);

        return redirect()->route('loans.show', $loan)->with('status', 'Empréstimo estornado. O registro foi mantido no histórico.');
    }
}
