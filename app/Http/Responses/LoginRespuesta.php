<?php

namespace App\Http\Responses;

use App\Filament\Pages\Reservas;
use App\Models\Restaurante;
use App\Models\User;
use App\Services\PermisosPanel;
use Filament\Auth\Http\Responses\LoginResponse;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

/**
 * After the panel login, users who only hold the operational role (recepcion) land on Reservas of the first restaurant
 * (by name) where they have the panel. If there is none, they get Filament's usual destination, which /admin resolves
 * (login notice, tenant picker or dashboard) and never sends back here.
 */
class LoginRespuesta extends LoginResponse
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        $usuario = $request->user();

        if ($usuario instanceof User && $usuario->esSoloOperativo()) {
            $restaurante = $this->primerRestauranteConReservas($usuario);

            if ($restaurante instanceof Restaurante) {
                return redirect()->to(Reservas::getUrl(tenant: $restaurante));
            }
        }

        return parent::toResponse($request);
    }

    private function primerRestauranteConReservas(User $usuario): ?Restaurante
    {
        return $usuario->getTenants(Filament::getCurrentOrDefaultPanel())
            ->sortBy('nombre')
            ->first(fn (Restaurante $restaurante): bool => PermisosPanel::permite($usuario, $restaurante, 'reservas'));
    }
}
