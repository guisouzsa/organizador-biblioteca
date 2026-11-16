<?php

namespace App\Http\Controllers\Admin;

use App\Support\Feedback;
use App\Enums\BorrowerType;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoanRulesRequest;
use App\Models\LoanRule;
use App\Services\Admin\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Regras de empréstimo por tipo de leitor, definidas pela escola (sem valores padrão). */
class LoanRuleController extends Controller
{
    public function edit(): View
    {
        Gate::authorize('settings.manage');

        return view('admin.loan-rules', ['rules' => LoanRule::all()->keyBy(fn ($r) => $r->borrower_type->value), 'types' => BorrowerType::cases()]);
    }

    public function update(LoanRulesRequest $request, AuditLogger $audit): RedirectResponse
    {
        $input = $request->validated()['rules'] ?? [];

        DB::transaction(function () use ($input, $audit) {
            foreach (BorrowerType::cases() as $type) {
                $row = $input[$type->value] ?? [];
                $existing = LoanRule::where('borrower_type', $type->value)->first();

                if (! ($row['enabled'] ?? false)) {
                    if ($existing) {
                        $audit->record('loan_rule.removed', $existing, $existing->only(['loan_days', 'max_active_loans', 'max_renewals']));
                        $existing->delete();
                    }

                    continue;
                }

                $data = [
                    'loan_days' => (int) $row['loan_days'],
                    'max_active_loans' => $this->nullableInt($row['max_active_loans'] ?? null),
                    'max_renewals' => $this->nullableInt($row['max_renewals'] ?? null),
                    'renewal_days' => $this->nullableInt($row['renewal_days'] ?? null),
                    'renew_when_overdue' => (bool) $row['renew_when_overdue'],
                    'block_when_overdue' => (bool) $row['block_when_overdue'],
                    'reservation_hold_hours' => $this->nullableInt($row['reservation_hold_hours'] ?? null),
                ];

                $rule = LoanRule::updateOrCreate(['borrower_type' => $type->value], $data);
                [$old, $new] = AuditLogger::diff($existing?->only(array_keys($data)) ?? [], $data);
                if ($old || $new) {
                    $audit->record($existing ? 'loan_rule.updated' : 'loan_rule.created', $rule, $old, $new);
                }
            }
        });

        return redirect()->route('loan-rules.edit')->with('status', 'Regras de empréstimo salvas. Valem para novos empréstimos; os já registrados mantêm o prazo original.')
            ->with('feedback', Feedback::success('Regras salvas', 'As regras valem para os próximos empréstimos. Os empréstimos já registrados mantêm o prazo original.'));
    }

    private function nullableInt(mixed $v): ?int
    {
        return $v === null || $v === '' ? null : (int) $v;
    }
}
