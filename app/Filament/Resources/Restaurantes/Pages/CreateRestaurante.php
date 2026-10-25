<?php

namespace App\Filament\Resources\Restaurantes\Pages;

use App\Enums\PlanRestaurante;
use App\Filament\Resources\Restaurantes\RestauranteResource;
use App\Models\Restaurante;
use App\Models\RestauranteIdioma;
use App\Services\InvitacionService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CreateRestaurante extends CreateRecord
{
    protected static string $resource = RestauranteResource::class;

    /**
     * El plan no está en $fillable: se asigna con forceFill, solo si el usuario puede crear restaurantes y
     * únicamente con alguno de los tres valores del enum.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        Gate::authorize('create', Restaurante::class);

        $plan = PlanRestaurante::deFormulario($data['plan'] ?? PlanRestaurante::Completo);
        unset($data['plan']);

        if ($plan === null) {
            throw ValidationException::withMessages(['data.plan' => __('validation.in', ['attribute' => __('panel.planes.cambiar.campo')])]);
        }

        $restaurante = new Restaurante($data);
        $restaurante->forceFill(['plan' => $plan])->save();

        return $restaurante;
    }

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
