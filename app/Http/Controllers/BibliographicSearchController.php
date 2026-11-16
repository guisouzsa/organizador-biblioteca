<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Services\Catalog\BookSearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BibliographicSearchController extends Controller
{
    public function __invoke(Request $request, BookSearchService $search): View
    {
        $this->authorize('create', Book::class);

        $data = $request->validate(['q' => ['nullable', 'string', 'max:160']]);
        $query = trim((string) ($data['q'] ?? ''));
        $results = $query === '' ? ['local' => [], 'external' => [], 'errors' => []] : $search->search($query, 12);

        foreach ($results['external'] as &$item) {
            $token = (string) Str::uuid();
            Cache::put('book-lookup:'.$token, [$item], now()->addMinutes(30));
            $item['lookup_token'] = $token;
        }
        unset($item);

        return view('catalog.search', ['query' => $query] + $results);
    }
}
