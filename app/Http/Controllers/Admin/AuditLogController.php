<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Auditoria (somente leitura). Bibliotecário vê apenas eventos do catálogo/acervo e os próprios. */
class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('audit.view');
        $all = $request->user()->can('audit.view-all');

        $filters = $request->validate([
            'evento' => ['nullable', 'string', 'max:60'],
            'usuario' => ['nullable', 'integer'],
            'de' => ['nullable', 'date'],
            'ate' => ['nullable', 'date'],
        ]);

        $logs = AuditLog::query()->with('user')
            ->when(! $all, fn ($q) => $q->where(fn ($w) => $w->where('user_id', $request->user()->id)
                ->orWhere('event', 'like', 'book.%')->orWhere('event', 'like', 'copy.%')->orWhere('event', 'like', 'category.%')))
            ->when($filters['evento'] ?? null, fn ($q, $v) => $q->where('event', 'like', $v.'%'))
            ->when($filters['usuario'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->when($filters['de'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filters['ate'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->latest('id')->paginate(30)->withQueryString();

        return view('admin.audit', [
            'logs' => $logs, 'filters' => $filters, 'users' => $all ? User::orderBy('name')->get() : collect(), 'restricted' => ! $all,
            'events' => ['auth' => 'Acesso', 'book' => 'Catálogo', 'copy' => 'Exemplares', 'loan' => 'Empréstimos', 'reservation' => 'Reservas',
                'borrower' => 'Leitores', 'user' => 'Usuários', 'loan_rule' => 'Regras', 'settings' => 'Configurações', 'category' => 'Categorias', 'report' => 'Relatórios'],
        ]);
    }
}
