<?php

namespace App\Http\Controllers;

use App\Http\Requests\LookupIsbnRequest;
use App\Models\BookCollection;
use App\Models\Category;
use App\Services\Catalog\BookLookupService;
use Illuminate\Http\JsonResponse;

/** Endpoint usado pelo scanner e pelo campo de ISBN manual: só orquestra e renderiza. */
class IsbnLookupController extends Controller
{
    public function __invoke(LookupIsbnRequest $request, BookLookupService $lookup): JsonResponse
    {
        $result = $lookup->lookup($request->validated('isbn'));

        $html = view('catalog.partials.lookup-result', [
            'result' => $result,
            'values' => $lookup->formValues($result),
            // Categorias das fontes são apenas sugestões (texto); não viram categorias sem confirmação.
            'suggestedCategories' => $result['merged']['categories'] ?? [],
            'categories' => Category::active()->orderBy('name')->get(),
            'collections' => BookCollection::orderBy('name')->pluck('name', 'id'),
            'duplicate' => null,
            'similar' => collect(),
            'lookupToken' => $result['token'],
            'book' => null,
        ])->render();

        return response()->json([
            'status' => $result['status'],
            'message' => $result['message'],
            'isbn13' => $result['isbn13'],
            'html' => $html,
        ]);
    }
}
