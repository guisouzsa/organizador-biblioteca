<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Services\Admin\AuditLogger;
use App\Services\Reports\ReportService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function __construct(private ReportService $reports) {}

    public function index(Request $request): View
    {
        Gate::authorize('report.view');
        $filters = $this->filters($request);

        return view('reports.index', [
            'type' => $filters['tipo'],
            'types' => ReportService::TYPES,
            'filters' => $filters,
            'report' => $this->reports->build($filters['tipo'], $filters),
            'categories' => Category::active()->orderBy('name')->get(),
        ]);
    }

    /** Exportação CSV (UTF-8 com BOM, para o Excel). Células que começam com =,+,-,@ são neutralizadas. */
    public function export(Request $request, AuditLogger $audit): Response
    {
        Gate::authorize('report.export');
        $filters = $this->filters($request);
        $report = $this->reports->build($filters['tipo'], $filters);

        $out = fopen('php://temp', 'r+');
        fputcsv($out, $report['columns'], ';');
        foreach ($report['rows'] as $row) {
            fputcsv($out, array_map(fn ($cell) => $this->safeCell($cell), $row), ';');
        }
        rewind($out);
        $csv = "\xEF\xBB\xBF".stream_get_contents($out);
        fclose($out);

        $audit->record('report.exported', null, [], ['tipo' => $filters['tipo'], 'linhas' => count($report['rows'])]);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="relatorio-'.$filters['tipo'].'-'.now()->format('Ymd-His').'.csv"',
        ]);
    }

    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        $data = $request->validate([
            'tipo' => ['nullable', Rule::in(array_keys(ReportService::TYPES))],
            'de' => ['nullable', 'date'],
            'ate' => ['nullable', 'date', 'after_or_equal:de'],
            'categoria' => ['nullable', 'integer'],
        ]);
        $data['tipo'] ??= 'atrasos';

        return $data;
    }

    private function safeCell(mixed $cell): mixed
    {
        return is_string($cell) && preg_match('/^[=+\-@\t\r]/', $cell) ? "'".$cell : $cell;
    }
}
