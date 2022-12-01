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
Route::put('/offices/{office}', [OfficeController::class,'update'])
    ->middleware(['auth:sanctum','verified','ability:office.update','can:update,office'])
    ->name('offices.update');
Route::delete('/offices/{office}', [OfficeController::class,'destroy'])
    ->middleware(['auth:sanctum','verified','ability:office.destroy','can:destroy,office'])
    ->name('offices.destroy');
