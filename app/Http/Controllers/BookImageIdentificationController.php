<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Services\Catalog\OcrIdentificationService;
use App\Support\Feedback;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BookImageIdentificationController extends Controller
{
    public function __invoke(Request $request, OcrIdentificationService $ocr): RedirectResponse
    {
        $this->authorize('create', Book::class);
        $data = $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:10240'],
            'image_type' => ['required', 'in:cover,credits'],
        ]);

        $query = $ocr->extract($data['image']);
        if ($query === '') {
            return back()->with('feedback', Feedback::make('warning',
                'Não foi possível ler a imagem',
                'Tente uma foto mais nítida ou pesquise manualmente. Nenhum dado foi salvo.',
            ));
        }

        return redirect()->route('books.search', ['q' => $query])->with('feedback', Feedback::make('info',
            'Texto extraído para revisão',
            'Confira os resultados: a fotografia é apenas uma ajuda e não confirma a identidade do livro.',
        ));
    }
}
