<?php

use App\Http\Controllers\{OfficeController,TagController};
use Illuminate\Support\Facades\Route;

// Tags...
Route::get('/tags', TagController::class);

// Offices...
Route::get('/offices', [OfficeController::class, 'index']);
