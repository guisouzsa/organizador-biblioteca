<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Services\Admin\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Localizações físicas (sala › estante › prateleira). CRUD simples: sem serviço. */
class LocationController extends Controller
{
    public function index(): View
    {
        $this->authorizeAbility();

        return view('locations.index', [
            'locations' => Location::with('parent')->withCount('copies')->orderBy('name')->get(),
            'parents' => Location::whereNull('parent_id')->where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeAbility();
        $data = $this->validated($request);

        $location = Location::create($data + ['active' => true]);
        $audit->record('location.created', $location, [], $data);

        return redirect()->route('locations.index')->with('status', 'Localização cadastrada.');
    }

    public function update(Request $request, Location $location, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeAbility();
        $data = $this->validated($request, $location) + ['active' => $request->boolean('active')];

        if ($data['parent_id'] ?? null) {
            abort_if((int) $data['parent_id'] === $location->id, 422, 'Uma localização não pode ser filha dela mesma.');
        }

        $before = $location->only(['name', 'code', 'parent_id', 'active']);
        $location->update($data);
        [$old, $new] = AuditLogger::diff($before, $location->only(['name', 'code', 'parent_id', 'active']));
        if ($old || $new) {
            $audit->record('location.updated', $location, $old, $new);
        }

        return redirect()->route('locations.index')->with('status', 'Localização atualizada.');
    }

    private function authorizeAbility(): void
    {
        abort_unless(auth()->user()->can('location.manage'), 403);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Location $location = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['nullable', 'string', 'max:30', Rule::unique('locations', 'code')->ignore($location)],
            'parent_id' => ['nullable', 'integer', Rule::exists('locations', 'id')],
        ], ['code.unique' => 'Já existe uma localização com este código.'], ['name' => 'nome', 'code' => 'código']);
    }
}
