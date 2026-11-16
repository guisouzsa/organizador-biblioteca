<?php

namespace App\Http\Controllers;

use App\Enums\ErrorCode;
use App\Exceptions\Domain\DomainException;
use App\Http\Requests\ReserveRequest;
use App\Models\Book;
use App\Models\Borrower;
use App\Models\Reservation;
use App\Services\Circulation\ReservationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReservationController extends Controller
{
    public function __construct(private ReservationService $reservations) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Reservation::class);

        $request->validate(['livro' => ['nullable', 'integer'], 'historico' => ['nullable', 'boolean']]);

        $reservations = Reservation::query()
            ->with(['book', 'borrower', 'copy.activeBarcode'])
            ->when(! $request->boolean('historico'), fn ($q) => $q->active())
            ->when($request->query('livro'), fn ($q, $id) => $q->where('book_id', $id))
            ->orderBy('book_id')->orderBy('requested_at')->orderBy('id')
            ->paginate(25)->withQueryString();

        return view('reservations.index', [
            'reservations' => $reservations,
            'book' => $request->filled('livro') ? Book::find($request->query('livro')) : null,
            'history' => $request->boolean('historico'),
            'reservable' => Book::active()
                ->whereHas('copies', fn ($q) => $q->where('status', 'active'))
                ->whereDoesntHave('copies', fn ($q) => $q->available())
                ->orderBy('title')->limit(300)->get(['id', 'title']),
        ]);
    }

    public function store(ReserveRequest $request): RedirectResponse
    {
        $book = Book::findOrFail($request->validated('book_id'));
        $term = trim($request->validated('borrower_q'));

        $matches = Borrower::where('registration_number', $term)->get();
        if ($matches->isEmpty()) {
            $matches = Borrower::search($term)->limit(2)->get();
        }
        if ($matches->isEmpty()) {
            throw DomainException::of(ErrorCode::LeitorNaoEncontrado, 'Nenhum leitor encontrado. Confira a matrícula ou pesquise o nome na tela de Leitores.');
        }
        if ($matches->count() > 1) {
            throw DomainException::of(ErrorCode::OperacaoInvalida, 'Mais de um leitor corresponde a essa busca. Informe a matrícula completa.');
        }

        $reservation = $this->reservations->reserve($book, $matches->first());

        return redirect()->route('reservations.index', ['livro' => $book->id])
            ->with('status', "Reserva registrada para {$reservation->borrower->name}. Posição na fila: {$reservation->queuePosition()}.");
    }

    public function cancel(Request $request, Reservation $reservation): RedirectResponse
    {
        $this->authorize('cancel', $reservation);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        $this->reservations->cancel($reservation, $data['reason'] ?? null);

        return back()->with('status', 'Reserva cancelada.');
    }
}
