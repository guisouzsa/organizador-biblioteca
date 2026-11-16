<?php

use App\Http\Controllers\BookController;
use App\Http\Controllers\IsbnLookupController;
use App\Http\Controllers\ScannerController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/catalogo')->name('home');

Route::prefix('catalogo')->name('books.')->group(function () {
    Route::get('/', [BookController::class, 'index'])->name('index');
    Route::get('novo', [BookController::class, 'create'])->name('create');
    Route::get('scanner', [ScannerController::class, 'index'])->name('scanner');
    Route::post('isbn', IsbnLookupController::class)->name('lookup');
    Route::post('/', [BookController::class, 'store'])->name('store');

    Route::get('{book}', [BookController::class, 'show'])->whereNumber('book')->name('show');
    Route::get('{book}/editar', [BookController::class, 'edit'])->whereNumber('book')->name('edit');
    Route::put('{book}', [BookController::class, 'update'])->whereNumber('book')->name('update');
});
