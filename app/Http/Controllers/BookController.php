<?php

namespace App\Http\Controllers;

use App\Support\Feedback;
use App\Enums\ErrorCode;
use App\Exceptions\Domain\DomainException;
use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Models\Book;
use App\Models\BookCollection;
use App\Models\Category;
use App\Services\Catalog\BookCatalogService;
use App\Services\Catalog\BookLookupService;
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
        $this->authorize('viewAny', Book::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'integer'],
            'discipline' => ['nullable', 'integer'],
            'subject' => ['nullable', 'integer'],
            'genre' => ['nullable', 'integer'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'year' => ['nullable', 'integer'],
            'collection' => ['nullable', 'integer'],
            'collection_group' => ['nullable', 'in:school,general'],
            'material_type' => ['nullable', 'string', 'max:40'],
            'language' => ['nullable', 'string', 'max:10'],
            'education_stage' => ['nullable', 'string', 'max:60'],
            'archived' => ['nullable', 'boolean'],
        ]);

        $books = Book::query()
            ->with('categories')
            ->withCopyCounts()
            ->filter($filters)
            ->orderBy('title')
            ->paginate(12)
            ->withQueryString();

        return view('catalog.index', [
            'books' => $books,
            'filters' => $filters,
            'categories' => Category::active()->orderBy('name')->get(),
            'disciplines' => Category::active()->where('type', 'discipline')->orderBy('name')->get(),
            'subjects' => Category::active()->whereIn('type', ['subject', 'theme'])->orderBy('name')->get(),
            'genres' => Category::active()->where('type', 'genre')->orderBy('name')->get(),
            'collections' => BookCollection::orderBy('name')->get(),
            'publishers' => Book::whereNotNull('publisher')->distinct()->orderBy('publisher')->pluck('publisher'),
            'years' => Book::whereNotNull('publication_year')->distinct()->orderByDesc('publication_year')->pluck('publication_year'),
            'materialTypes' => Book::whereNotNull('material_type')->distinct()->orderBy('material_type')->pluck('material_type'),
            'languages' => Book::whereNotNull('language')->distinct()->orderBy('language')->pluck('language'),
            'educationStages' => Book::whereNotNull('education_stage')->distinct()->orderBy('education_stage')->pluck('education_stage'),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Book::class);

        $token = $request->query('lookup_token');
        $values = $this->lookup->formValuesForToken(is_string($token) ? $token : null);
        $values['isbn'] = $values['isbn'] ?? $request->query('isbn');

        return view('catalog.create', $this->formData($values));
    }

    public function store(StoreBookRequest $request): RedirectResponse
    {
        $data = $request->toDto();
        $sources = $this->lookup->sourcesForToken($data->lookupToken);

        $duplicate = $this->catalog->findDuplicate($data->isbn13, $data->isbn10);

        if ($duplicate) {
            if (! $request->boolean('confirm_update')) {
                return back()->withInput()->with('duplicate_book_id', $duplicate->id);
            }

            $this->authorize('update', $duplicate);
            $this->catalog->update($duplicate, $data, $sources, confirmIsbnChange: true);

            return redirect()->route('books.show', $duplicate)->with('status', 'Registro existente atualizado com os novos dados.')
                ->with('feedback', Feedback::success('Registro atualizado', "O livro “{$duplicate->title}” já existia e foi atualizado com os novos dados."));
        }

        // Sem ISBN: avisa sobre possíveis duplicatas, sem bloquear (RN-CAT-04).
        if (! $data->isbn13 && ! $data->isbn10 && ! $request->boolean('confirm_distinct')) {
            $similar = $this->catalog->similarBooks($data);
            if ($similar->isNotEmpty()) {
                return back()->withInput()->with('similar_book_ids', $similar->pluck('id')->all());
            }
        }

        try {
            $book = $this->catalog->create($data, $sources);
        } catch (UniqueConstraintViolationException) {
            // Envio duplo/concorrente do mesmo ISBN: a restrição única do banco é a garantia final.
            $existing = $this->catalog->findDuplicate($data->isbn13, $data->isbn10);

            return back()->withInput()->with('duplicate_book_id', $existing?->id);
        }

        return redirect()->route('books.show', $book)->with('status', 'Livro cadastrado no catálogo.')
            ->with('feedback', Feedback::success(
                'Livro cadastrado',
                "“{$book->title}” foi adicionado ao catálogo e já está disponível para consulta.",
                auth()->user()->can('copy.create') ? [['label' => 'Registrar exemplares', 'href' => route('books.copies.create', $book), 'variant' => 'secondary']] : [],
            ));
    }

    public function show(Book $book): View
    {
        $this->authorize('view', $book);

        $book->load(['categories', 'collection', 'sources']);

        $copies = auth()->user()->can('copy.view')
            ? $book->copies()->with(['activeBarcode', 'location', 'activeLoan.borrower', 'heldReservation'])->orderBy('id')->get()
            : collect();

        return view('catalog.show', [
            'book' => $book,
            'copies' => $copies,
            'availableCount' => $book->copies()->available()->count(),
            'copiesCount' => $book->copies()->count(),
            'reservationsCount' => $book->reservations()->active()->count(),
        ]);
    }

    public function edit(Book $book): View
    {
        $this->authorize('update', $book);

        $book->load('categories');

        $values = $book->only([
            'title', 'subtitle', 'authors', 'publisher', 'edition', 'publication_year',
            'pages', 'description', 'cover_url', 'language', 'format', 'collection_group',
            'material_type', 'audience', 'education_stage', 'work_id', 'collection_id',
        ]);
        $values['isbn'] = $book->isbn13 ?? $book->isbn10;
        $values['categories'] = $book->categories->pluck('id')->all();

        return view('catalog.edit', ['book' => $book, 'hasCopies' => $book->copies()->exists()] + $this->formData($values));
    }

    public function update(UpdateBookRequest $request, Book $book): RedirectResponse
    {
        // Edição concorrente (RN do catálogo): o formulário carrega a versão (updated_at) que o operador viu.
        if ($request->filled('version') && (int) $request->input('version') !== $book->updated_at->timestamp) {
            throw DomainException::of(ErrorCode::ConflitoEdicao);
        }

        $this->catalog->update($book, $request->toDto(), confirmIsbnChange: $request->boolean('confirm_isbn_change'));

        return redirect()->route('books.show', $book)->with('status', 'Registro atualizado.')
            ->with('feedback', Feedback::success('Livro atualizado', "As alterações em “{$book->title}” foram salvas."));
    }

    public function archive(Book $book): RedirectResponse
    {
        $this->authorize('archive', $book);
        $this->catalog->archive($book);

        return redirect()->route('books.show', $book)->with('status', 'Livro arquivado. Os exemplares existentes continuam no acervo.');
    }

    public function restore(Book $book): RedirectResponse
    {
        $this->authorize('archive', $book);
        $this->catalog->restore($book);

        return redirect()->route('books.show', $book)->with('status', 'Livro restaurado.');
    }

    public function destroy(Book $book): RedirectResponse
    {
        $this->authorize('delete', $book);
        $this->catalog->delete($book);

        return redirect()->route('books.index')->with('status', 'Livro excluído (não tinha exemplares nem reservas).');
    }

    /** @return array<string, mixed> */
    private function formData(array $values): array
    {
        return [
            'values' => $values,
            'categories' => Category::active()->orderBy('name')->get(),
            'collections' => BookCollection::orderBy('name')->pluck('name', 'id'),
            'duplicate' => ($id = session('duplicate_book_id')) ? Book::find($id) : null,
            'similar' => ($ids = session('similar_book_ids')) ? Book::whereIn('id', $ids)->get() : collect(),
            'lookupToken' => Arr::get($values, 'lookup_token'),
        ];
    }
}
