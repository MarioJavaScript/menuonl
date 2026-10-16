<?php

use App\Http\Controllers\InvitacionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/invitacion/{token}', [InvitacionController::class, 'show'])->name('invitacion.show');
Route::post('/invitacion/{token}/aceptar', [InvitacionController::class, 'accept'])->name('invitacion.accept');
