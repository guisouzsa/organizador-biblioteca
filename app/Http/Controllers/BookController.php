<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Models\Book;
use App\Models\BookCollection;
use App\Models\Category;
use App\Services\BookCatalogService;
use App\Services\BookLookupService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\View\View;

class BookController extends Controller
{
    public function __construct(
        private BookCatalogService $catalog,
        private BookLookupService $lookup,
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'integer'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'year' => ['nullable', 'integer'],
            'collection' => ['nullable', 'integer'],
        ]);

        $books = Book::query()
            ->with('categories')
            ->filter($filters)
            ->orderBy('title')
            ->paginate(12)
            ->withQueryString();

        return view('catalog.index', [
            'books' => $books,
            'filters' => $filters,
            'categories' => Category::orderBy('name')->get(),
            'collections' => BookCollection::orderBy('name')->get(),
            'publishers' => Book::whereNotNull('publisher')->distinct()->orderBy('publisher')->pluck('publisher'),
            'years' => Book::whereNotNull('publication_year')->distinct()->orderByDesc('publication_year')->pluck('publication_year'),
        ]);
    }

    public function create(Request $request): View
    {
        $values = ['isbn' => $request->query('isbn')];

        return view('catalog.create', $this->formData($values));
    }

    public function store(StoreBookRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['isbn13'] = $request->input('isbn13');
        $data['isbn10'] = $request->input('isbn10');

        $duplicate = $this->catalog->findDuplicate($data['isbn13'], $data['isbn10']);
        $sources = $this->lookup->sourcesForToken($data['lookup_token'] ?? null);

        if ($duplicate) {
            if (! $request->boolean('confirm_update')) {
                return back()->withInput()->with('duplicate_book_id', $duplicate->id);
            }

            $this->catalog->update($duplicate, $data, $sources);

            return redirect()->route('books.show', $duplicate)->with('status', 'Registro existente atualizado com os novos dados.');
        }

        try {
            $book = $this->catalog->create($data, $sources);
        } catch (UniqueConstraintViolationException) {
            // Envio duplo/concorrente do mesmo ISBN: a restrição única do banco é a garantia final.
            $existing = $this->catalog->findDuplicate($data['isbn13'], $data['isbn10']);

            return back()->withInput()->with('duplicate_book_id', $existing?->id);
        }

        return redirect()->route('books.show', $book)->with('status', 'Livro cadastrado no catálogo.');
    }

    public function show(Book $book): View
    {
        $book->load(['categories', 'collection', 'sources']);

        return view('catalog.show', ['book' => $book]);
    }

    public function edit(Book $book): View
    {
        $book->load('categories', 'collection');

        $values = $book->only([
            'title', 'subtitle', 'authors', 'publisher', 'edition', 'publication_year',
            'pages', 'description', 'cover_url', 'language', 'format',
        ]);
        $values['isbn'] = $book->isbn13 ?? $book->isbn10;
        $values['collection_name'] = $book->collection?->name;
        $values['categories'] = $book->categories->pluck('id')->all();

        return view('catalog.edit', ['book' => $book] + $this->formData($values));
    }

    public function update(UpdateBookRequest $request, Book $book): RedirectResponse
    {
        $data = $request->validated();
        $data['isbn13'] = $request->input('isbn13');
        $data['isbn10'] = $request->input('isbn10');

        $this->catalog->update($book, $data);

        return redirect()->route('books.show', $book)->with('status', 'Registro atualizado.');
    }

    /** @return array<string, mixed> */
    private function formData(array $values): array
    {
        return [
            'values' => $values,
            'categories' => Category::orderBy('name')->get(),
            'collections' => BookCollection::orderBy('name')->pluck('name'),
            'duplicate' => ($id = session('duplicate_book_id')) ? Book::find($id) : null,
            'lookupToken' => Arr::get($values, 'lookup_token'),
        ];
    }
}
