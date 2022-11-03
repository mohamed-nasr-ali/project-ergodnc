<?php

use App\Http\Controllers\{OfficeController,TagController};
use Illuminate\Support\Facades\Route;

// Tags...
Route::get('/tags', TagController::class);

// Offices...
Route::get('/offices', [OfficeController::class, 'index'])->name('offices.index');
Route::get('/offices/{office}', [OfficeController::class, 'show'])->name('offices.show');
Route::post('/offices', [OfficeController::class, 'create'])
    ->middleware(['auth:sanctum','verified','ability:office.create'])
    ->name('offices.create');
