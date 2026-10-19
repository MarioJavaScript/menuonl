<?php

use App\Http\Controllers\Api\V1\MenuApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::middleware('throttle:menu-publico')
        ->get('/menu/{slug}', [MenuApiController::class, 'show'])
        ->name('api.v1.menu.show');
});
