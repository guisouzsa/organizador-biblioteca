<?php

namespace App\Http\Controllers;

use App\Http\Requests\LookupIsbnRequest;
use App\Models\BookCollection;
use App\Models\Category;
use App\Services\BookLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;

/** Endpoint usado pelo scanner e pelo campo de ISBN manual. */
class IsbnLookupController extends Controller
{
    public function __invoke(LookupIsbnRequest $request, BookLookupService $lookup): JsonResponse
    {
        $result = $lookup->lookup($request->validated('isbn'));

        // Categorias das fontes são apenas sugestões (texto); não viram categorias sem confirmação.
        $suggested = $result['merged']['categories'] ?? [];

        $values = Arr::except($result['merged'], ['categories']) + ['isbn' => $result['isbn13']];
        $values['authors'] = implode('; ', $result['merged']['authors'] ?? []);
        $values['lookup_token'] = $result['token'];

        $html = view('catalog.partials.lookup-result', [
            'result' => $result,
            'values' => $values,
            'suggestedCategories' => $suggested,
            'categories' => Category::orderBy('name')->get(),
            'collections' => BookCollection::orderBy('name')->pluck('name'),
            'duplicate' => null,
            'lookupToken' => $result['token'],
        ])->render();

        return response()->json([
            'status' => $result['status'],
            'message' => $result['message'],
            'isbn13' => $result['isbn13'],
            'html' => $html,
        ]);
    }
}
