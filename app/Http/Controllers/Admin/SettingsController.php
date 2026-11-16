<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SettingsRequest;
use App\Services\Admin\AuditLogger;
use App\Services\Admin\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(SettingsService $settings): View
    {
        Gate::authorize('settings.manage');

        return view('admin.settings', [
            'libraryName' => $settings->libraryName(),
            'barcodePrefix' => $settings->barcodePrefix(),
            'accessionRequired' => $settings->accessionRequired(),
        ]);
    }

    public function update(SettingsRequest $request, SettingsService $settings, AuditLogger $audit): RedirectResponse
    {
        $new = [
            SettingsService::LIBRARY_NAME => $request->validated('library_name'),
            SettingsService::BARCODE_PREFIX => $request->validated('barcode_prefix'),
            SettingsService::ACCESSION_REQUIRED => $request->boolean('accession_required'),
        ];
        $old = array_map(fn ($key) => $settings->get($key), array_combine(array_keys($new), array_keys($new)));

        foreach ($new as $key => $value) {
            $settings->set($key, $value);
        }
        [$changedOld, $changedNew] = AuditLogger::diff($old, $new);
        if ($changedOld || $changedNew) {
            $audit->record('settings.updated', null, $changedOld, $changedNew);
        }

        return redirect()->route('settings.edit')->with('status', 'Configurações salvas. O novo prefixo vale apenas para códigos gerados daqui em diante.');
    }
}
