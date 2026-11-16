<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\LoanRuleController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\BorrowerController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CirculationController;
use App\Http\Controllers\CollectionController;
use App\Http\Controllers\CopyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IsbnLookupController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\ScannerController;
use Illuminate\Support\Facades\Route;

// ---------- Acesso (público) ----------
Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->middleware('throttle:20,1')->name('login.store');
    Route::get('esqueci-a-senha', [PasswordController::class, 'forgotForm'])->name('password.request');
    Route::post('esqueci-a-senha', [PasswordController::class, 'sendLink'])->middleware('throttle:5,1')->name('password.email');
    Route::get('redefinir-senha/{token}', [PasswordController::class, 'resetForm'])->name('password.reset');
    Route::post('redefinir-senha', [PasswordController::class, 'reset'])->name('password.update');
});

Route::post('logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

// ---------- Área autenticada ----------
Route::middleware(['auth', 'active', 'password.changed'])->group(function () {
    Route::redirect('/', '/painel')->name('home');

    Route::get('minha-conta/senha', [PasswordController::class, 'changeForm'])->name('account.password');
    Route::put('minha-conta/senha', [PasswordController::class, 'change'])->name('account.password.update');

    Route::get('painel', DashboardController::class)->name('dashboard');

    // Catálogo
    Route::prefix('catalogo')->name('books.')->group(function () {
        Route::get('/', [BookController::class, 'index'])->name('index');
        Route::get('novo', [BookController::class, 'create'])->name('create');
        Route::get('scanner', [ScannerController::class, 'index'])->name('scanner');
        // Limite por IP: protege a cota das APIs externas.
        Route::post('isbn', IsbnLookupController::class)->middleware('throttle:30,1')->name('lookup');
        Route::post('/', [BookController::class, 'store'])->name('store');

        Route::get('{book}', [BookController::class, 'show'])->whereNumber('book')->name('show');
        Route::get('{book}/editar', [BookController::class, 'edit'])->whereNumber('book')->name('edit');
        Route::put('{book}', [BookController::class, 'update'])->whereNumber('book')->name('update');
        Route::post('{book}/arquivar', [BookController::class, 'archive'])->whereNumber('book')->name('archive');
        Route::post('{book}/restaurar', [BookController::class, 'restore'])->whereNumber('book')->name('restore');
        Route::delete('{book}', [BookController::class, 'destroy'])->whereNumber('book')->name('destroy');

        // Exemplares de um livro
        Route::get('{book}/exemplares/novo', [CopyController::class, 'create'])->whereNumber('book')->name('copies.create');
        Route::post('{book}/exemplares', [CopyController::class, 'store'])->whereNumber('book')->name('copies.store');
    });

    // Acervo físico
    Route::get('acervo', [CopyController::class, 'index'])->name('copies.index');
    Route::post('acervo/lookup', [CopyController::class, 'lookup'])->middleware('throttle:120,1')->name('copies.lookup');
    Route::get('exemplares/etiquetas', [CopyController::class, 'labels'])->name('copies.labels');
    Route::get('exemplares/{copy}', [CopyController::class, 'show'])->whereNumber('copy')->name('copies.show');
    Route::put('exemplares/{copy}', [CopyController::class, 'update'])->whereNumber('copy')->name('copies.update');
    Route::post('exemplares/{copy}/estado', [CopyController::class, 'transition'])->whereNumber('copy')->name('copies.transition');
    Route::post('exemplares/{copy}/etiqueta', [CopyController::class, 'relabel'])->whereNumber('copy')->name('copies.relabel');

    // Leitores
    Route::get('leitores', [BorrowerController::class, 'index'])->name('borrowers.index');
    Route::get('leitores/novo', [BorrowerController::class, 'create'])->name('borrowers.create');
    Route::post('leitores', [BorrowerController::class, 'store'])->name('borrowers.store');
    Route::get('leitores/{borrower}', [BorrowerController::class, 'show'])->whereNumber('borrower')->name('borrowers.show');
    Route::get('leitores/{borrower}/editar', [BorrowerController::class, 'edit'])->whereNumber('borrower')->name('borrowers.edit');
    Route::put('leitores/{borrower}', [BorrowerController::class, 'update'])->whereNumber('borrower')->name('borrowers.update');
    Route::post('leitores/{borrower}/situacao', [BorrowerController::class, 'status'])->whereNumber('borrower')->name('borrowers.status');
    Route::post('leitores/{borrower}/anonimizar', [BorrowerController::class, 'anonymize'])->whereNumber('borrower')->name('borrowers.anonymize');

    // Circulação
    Route::prefix('circulacao')->group(function () {
        Route::get('/', [CirculationController::class, 'desk'])->name('circulation.desk');
        Route::get('emprestimos', [LoanController::class, 'index'])->name('loans.index');
        Route::post('emprestimos', [LoanController::class, 'store'])->name('loans.store');
        Route::get('emprestimos/{loan}', [LoanController::class, 'show'])->whereNumber('loan')->name('loans.show');
        Route::post('emprestimos/{loan}/renovar', [LoanController::class, 'renew'])->whereNumber('loan')->name('loans.renew');
        Route::post('emprestimos/{loan}/estornar', [LoanController::class, 'void'])->whereNumber('loan')->name('loans.void');
        Route::post('devolucoes', [LoanController::class, 'return'])->name('returns.store');

        Route::get('reservas', [ReservationController::class, 'index'])->name('reservations.index');
        Route::post('reservas', [ReservationController::class, 'store'])->name('reservations.store');
        Route::post('reservas/{reservation}/cancelar', [ReservationController::class, 'cancel'])->whereNumber('reservation')->name('reservations.cancel');
    });

    // Relatórios
    Route::get('relatorios', [ReportController::class, 'index'])->name('reports.index');
    Route::get('relatorios/exportar', [ReportController::class, 'export'])->name('reports.export');

    // Administração
    Route::get('categorias', [CategoryController::class, 'index'])->name('categories.index');
    Route::post('categorias', [CategoryController::class, 'store'])->name('categories.store');
    Route::put('categorias/{category}', [CategoryController::class, 'update'])->whereNumber('category')->name('categories.update');
    Route::post('categorias/{category}/arquivar', [CategoryController::class, 'archive'])->whereNumber('category')->name('categories.archive');

    Route::post('colecoes', [CollectionController::class, 'store'])->name('collections.store');
    Route::put('colecoes/{collection}', [CollectionController::class, 'update'])->whereNumber('collection')->name('collections.update');

    Route::get('localizacoes', [LocationController::class, 'index'])->name('locations.index');
    Route::post('localizacoes', [LocationController::class, 'store'])->name('locations.store');
    Route::put('localizacoes/{location}', [LocationController::class, 'update'])->whereNumber('location')->name('locations.update');

    Route::prefix('admin')->group(function () {
        Route::get('regras-de-emprestimo', [LoanRuleController::class, 'edit'])->name('loan-rules.edit');
        Route::put('regras-de-emprestimo', [LoanRuleController::class, 'update'])->name('loan-rules.update');
        Route::get('configuracoes', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('configuracoes', [SettingsController::class, 'update'])->name('settings.update');
        Route::get('auditoria', [AuditLogController::class, 'index'])->name('audit.index');

        Route::get('usuarios', [UserController::class, 'index'])->name('users.index');
        Route::get('usuarios/novo', [UserController::class, 'create'])->name('users.create');
        Route::post('usuarios', [UserController::class, 'store'])->name('users.store');
        Route::get('usuarios/{user}/editar', [UserController::class, 'edit'])->whereNumber('user')->name('users.edit');
        Route::put('usuarios/{user}', [UserController::class, 'update'])->whereNumber('user')->name('users.update');
        Route::post('usuarios/{user}/senha', [UserController::class, 'resetPassword'])->whereNumber('user')->name('users.reset-password');
    });
});
