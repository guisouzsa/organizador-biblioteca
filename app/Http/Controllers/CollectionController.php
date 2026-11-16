<?php

namespace App\Http\Controllers;

use App\Models\BookCollection;
use App\Services\Admin\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Coleções didáticas. A situação de verificação oficial (FNDE/Seduc) é registrada pelo operador
 * com a fonte; o sistema não importa coleções em massa.
 */
class CollectionController extends Controller
{
    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->can('category.manage'), 403);
        $data = $this->validated($request);

        $collection = BookCollection::create($data + ['verification_status' => $data['verification_status'] ?? 'unverified']);
        $audit->record('collection.created', $collection, [], $data);

        return redirect()->route('categories.index')->with('status', 'Coleção cadastrada.');
    }

    public function update(Request $request, BookCollection $collection, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->can('category.manage'), 403);
        $data = $this->validated($request, $collection);

        $before = $collection->only(array_keys($data));
        $collection->update($data);
        [$old, $new] = AuditLogger::diff($before, $collection->only(array_keys($data)));
        if ($old || $new) {
            $audit->record('collection.updated', $collection, $old, $new);
        }

        return redirect()->route('categories.index')->with('status', 'Coleção atualizada.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?BookCollection $collection = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('collections', 'name')->ignore($collection)],
            'publisher' => ['nullable', 'string', 'max:255'],
            'fnde_code' => ['nullable', 'string', 'max:50'],
            'program' => ['nullable', 'string', 'max:255'],
            'cycle' => ['nullable', 'string', 'max:50'],
            'knowledge_area' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:255'],
            'verification_status' => ['nullable', Rule::in(['unverified', 'confirmed', 'official'])],
        ], ['name.unique' => 'Já existe uma coleção com este nome.'], ['name' => 'nome']);
    }
}
