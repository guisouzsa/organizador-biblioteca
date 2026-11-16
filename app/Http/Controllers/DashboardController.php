<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\Reservation;
use App\Services\Reports\DashboardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(DashboardService $dashboard): View|RedirectResponse
    {
        // Perfil de consulta não tem painel: vai direto ao catálogo.
        if (auth()->user()->cannot('dashboard.view')) {
            return redirect()->route('books.index');
        }

        return view('dashboard', [
            'stats' => $dashboard->counters(),
            'typesWithoutRule' => $dashboard->typesWithoutRule(),
            'rulesConfigured' => $dashboard->ruleConfiguredAtAll(),
            'overdue' => Loan::overdue()->with(['copy.book', 'copy.activeBarcode', 'borrower'])->orderBy('due_on')->limit(6)->get(),
            'ready' => Reservation::where('status', 'ready')->with(['book', 'borrower', 'copy.activeBarcode'])->orderBy('ready_at')->limit(6)->get(),
            'dueToday' => Loan::active()->whereDate('due_on', today())->count(),
        ]);
    }
}
