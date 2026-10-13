<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\LoanController;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// api-contract.md §3.1 — public, no token.
Route::get('/health', fn () => response()->json(['status' => 'ok']));
Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('jwt')->group(function () {
    Route::middleware('role:admin')->group(function () {
        Route::post('/users', [UserController::class, 'store']);
    });

    Route::get('/categories', [CategoryController::class, 'index']);

    Route::get('/books', [BookController::class, 'index']);
    Route::get('/books/{id}', [BookController::class, 'show']);
    Route::get('/books/{id}/cover', [BookController::class, 'showCover']);

    Route::middleware('role:admin')->group(function () {
        Route::post('/books', [BookController::class, 'store']);
        Route::put('/books/{id}', [BookController::class, 'update']);
        Route::delete('/books/{id}', [BookController::class, 'destroy']);
        Route::put('/books/{id}/cover', [BookController::class, 'uploadCover']);
        Route::delete('/books/{id}/cover', [BookController::class, 'deleteCover']);
    });

    Route::middleware('role:admin,librarian')->group(function () {
        Route::get('/members', [MemberController::class, 'index']);
        Route::get('/members/{id}', [MemberController::class, 'show']);
        Route::post('/members', [MemberController::class, 'store']);

        Route::post('/loans', [LoanController::class, 'store']);
        Route::get('/loans', [LoanController::class, 'index']);
        Route::get('/loans/overdue', [LoanController::class, 'overdue']);
        Route::get('/loans/{id}', [LoanController::class, 'show']);
        Route::post('/loans/{loanId}/items/{itemId}/return', [LoanController::class, 'returnItem']);
    });

    Route::middleware('role:admin')->group(function () {
        Route::get('/reports/most-borrowed', [ReportController::class, 'mostBorrowed']);
    });
});
