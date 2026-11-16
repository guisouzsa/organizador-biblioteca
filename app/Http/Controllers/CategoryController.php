<?php

namespace App\Http\Controllers;

use App\Enums\CategoryType;
use App\Enums\ErrorCode;
use App\Exceptions\Domain\DomainException;
use App\Models\BookCollection;
use App\Models\Category;
use App\Services\Admin\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Disciplinas, categorias e coleções didáticas (UC-ADM-001). */
class CategoryController extends Controller
{
    public function index(): View
    {
        $this->guard();

        return view('categories.index', [
            'categories' => Category::withCount('books')->orderBy('type')->orderBy('name')->get(),
            'collections' => BookCollection::withCount('books')->orderBy('name')->get(),
            'types' => collect(CategoryType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()])->all(),
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $this->guard();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(CategoryType::class)],
        ], [], ['name' => 'nome', 'type' => 'tipo']);

        $name = trim(preg_replace('/\s+/', ' ', $data['name']));
        if (Category::where('slug', Str::slug($name))->orWhere('name', $name)->exists()) {
            throw DomainException::of(ErrorCode::CategoriaDuplicada);
        }

        $category = Category::create(['name' => $name, 'slug' => Str::slug($name), 'type' => $data['type']]);
        $audit->record('category.created', $category, [], ['name' => $name, 'type' => $data['type']]);

        return redirect()->route('categories.index')->with('status', 'Categoria criada.');
    }

    public function update(Request $request, Category $category, AuditLogger $audit): RedirectResponse
    {
        $this->guard();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(CategoryType::class)],
        ], [], ['name' => 'nome', 'type' => 'tipo']);

        $name = trim(preg_replace('/\s+/', ' ', $data['name']));
        $slug = Str::slug($name);
        if (Category::whereKeyNot($category->id)->where(fn ($q) => $q->where('slug', $slug)->orWhere('name', $name))->exists()) {
            throw DomainException::of(ErrorCode::CategoriaDuplicada);
        }

        $before = ['name' => $category->name, 'type' => $category->type->value];
        $category->update(['name' => $name, 'slug' => $slug, 'type' => $data['type']]);
        [$old, $new] = AuditLogger::diff($before, ['name' => $name, 'type' => $data['type']]);
        if ($old || $new) {
            $audit->record('category.updated', $category, $old, $new);
        }

        return redirect()->route('categories.index')->with('status', 'Categoria atualizada.');
    }

    /** Em uso → arquiva (não exclui). Alterna arquivar/restaurar. */
    public function archive(Category $category, AuditLogger $audit): RedirectResponse
    {
        $this->guard();

        $archived = $category->archived_at === null;
        $category->update(['archived_at' => $archived ? now() : null]);
        $audit->record($archived ? 'category.archived' : 'category.restored', $category);

        return redirect()->route('categories.index')->with('status', $archived ? 'Categoria arquivada (continua nos livros já classificados).' : 'Categoria restaurada.');
    }

    private function guard(): void
    {
        abort_unless(auth()->user()->can('category.manage'), 403);
    }
}
