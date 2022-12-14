<?php

use App\Http\Controllers\{HostReservationController,
    OfficeController,
    OfficeImageController,
    TagController,
    UserReservationController};
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

//Office Photos...
Route::post('offices/{office}/images',[OfficeImageController::class,'store'])
    ->middleware(['auth:sanctum','verified','ability:office.update','can:update,office'])
    ->name('offices.images.store');
Route::delete('offices/{office}/images/{image:id}',[OfficeImageController::class,'destroy'])
    ->middleware(['auth:sanctum','verified','ability:office.update','can:update,office'])
    ->name('offices.images.destroy');


//User Reservations
Route::get('/user/reservations',[UserReservationController::class,'index'])
    ->middleware(['auth:sanctum','verified','ability:reservation.show'])
    ->name('user.reservations.show');
Route::post('/user/reservations',[UserReservationController::class,'create'])
    ->middleware(['auth:sanctum','verified','ability:reservation.create'])
    ->name('user.reservations.create');

//Host Reservations
Route::get('/host/reservations',[HostReservationController::class,'index'])
    ->middleware(['auth:sanctum','verified','ability:reservation.show'])
    ->name('host.reservations.show');
