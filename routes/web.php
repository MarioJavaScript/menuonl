<?php

use App\Http\Controllers\InvitacionAceptarController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Invitations (Mitad A). /m and /q belong to Mitad B.
Route::middleware('throttle:invitaciones')->prefix('invitaciones')->group(function (): void {
    Route::get('/{token}', [InvitacionAceptarController::class, 'mostrar'])->name('invitaciones.mostrar');
    Route::post('/{token}', [InvitacionAceptarController::class, 'aceptar'])->name('invitaciones.aceptar');
});
