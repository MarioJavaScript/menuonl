<?php

namespace App\Http\Responses;

use App\Models\User;
use Filament\Auth\Http\Responses\LoginResponse;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

/**
 * After the panel login, users who only hold operational roles (recepcion, cocina) land on the service panel.
 */
class LoginRespuesta extends LoginResponse
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        $usuario = $request->user();

        if ($usuario instanceof User && $usuario->esSoloOperativo()) {
            return redirect()->to(route('panel'));
        }

        return parent::toResponse($request);
    }
}
