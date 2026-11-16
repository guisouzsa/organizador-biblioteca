<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\View\View;

class ScannerController extends Controller
{
    public function index(): View
    {
        $this->authorize('create', Book::class);

        return view('catalog.scanner');
    }
}
