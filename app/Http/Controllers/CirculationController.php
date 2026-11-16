<?php

namespace App\Http\Controllers;

use App\Enums\CopyCondition;
use App\Models\Borrower;
use App\Models\Loan;
use App\Models\LoanRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Balcão: identificar o leitor, emprestar e devolver. A regra fica em LoanService. */
class CirculationController extends Controller
{
    public function desk(Request $request): View|RedirectResponse
    {
        $this->authorize('create', Loan::class);

        $request->validate(['q' => ['nullable', 'string', 'max:100'], 'leitor' => ['nullable', 'integer']]);

        $borrower = null;
        $candidates = collect();
        $q = trim((string) $request->query('q', ''));

        if ($request->filled('leitor')) {
            $borrower = Borrower::find($request->query('leitor'));
        } elseif ($q !== '') {
            // Matrícula exata (de um único leitor) abre direto; senão, lista para escolher.
            $exact = Borrower::where('registration_number', $q)->get();
            if ($exact->count() === 1) {
                return redirect()->route('circulation.desk', ['leitor' => $exact->first()->id]);
            }
            $candidates = $exact->isNotEmpty() ? $exact : Borrower::search($q)->orderBy('name')->limit(10)->get();
        }

        $rule = $borrower ? LoanRule::where('borrower_type', $borrower->type->value)->first() : null;

        return view('circulation.desk', [
            'q' => $q,
            'borrower' => $borrower,
            'candidates' => $candidates,
            'rule' => $rule,
            'activeLoans' => $borrower?->activeLoans()->with(['copy.book', 'copy.activeBarcode'])->orderBy('due_on')->get() ?? collect(),
            'recent' => Loan::with(['copy.book', 'copy.activeBarcode', 'borrower'])->latest('loaned_at')->latest('id')->limit(8)->get(),
            'conditions' => collect(CopyCondition::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all(),
        ]);
    }
}
