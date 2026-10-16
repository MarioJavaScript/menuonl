<?php

namespace App\Filament\Resources\Restaurantes\Pages;

use App\Filament\Resources\Restaurantes\RestauranteResource;
use App\Models\Restaurante;
use App\Models\RestauranteIdioma;
use App\Services\InvitacionService;
use Filament\Resources\Pages\CreateRecord;

class CreateRestaurante extends CreateRecord
{
    protected static string $resource = RestauranteResource::class;

    /**
     * Seed the default language and invite the first propietario (flow 6.3).
     *
     * Events are muted for the language row because the superadmin may be working in another
     * restaurant, and BelongsToRestaurante rejects rows outside the active one.
     */
    protected function afterCreate(): void
    {
        /** @var Restaurante $restaurante */
        $restaurante = $this->record;

        RestauranteIdioma::withoutEvents(fn () => RestauranteIdioma::create([
            'restaurante_id' => $restaurante->getKey(),
            'idioma' => $restaurante->idioma_default,
            'orden' => 0,
        ]));

        app(InvitacionService::class)->invitar(
            $restaurante,
            $this->data['email_propietario'],
            'propietario',
            auth()->user(),
        );
    }
}
