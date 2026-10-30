<?php

namespace App\Filament\Resources\Invitaciones\Pages;

use App\Filament\Resources\Invitaciones\InvitacionResource;
use App\Models\Invitacion;
use App\Models\Restaurante;
use App\Services\InvitacionService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateInvitacion extends CreateRecord
{
    protected static string $resource = InvitacionResource::class;

    protected static bool $canCreateAnother = false;

    /**
     * The token is generated, hashed and mailed by the service; it is never part of the form data.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var Restaurante $restaurante */
        $restaurante = Filament::getTenant();

        try {
            /** @var Invitacion $invitacion */
            $invitacion = app(InvitacionService::class)->invitar(
                $restaurante,
                $data['email'],
                $data['rol'],
                auth()->user(),
            );
        } catch (ValidationException $e) {
            if (! $restaurante->estaPausado()) {
                throw $e;
            }

            Notification::make()->danger()->title(__('panel.cuenta_pausada.invitar_bloqueado'))->send();
            $this->halt();
        }

        return $invitacion;
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Invitación enviada';
    }
}
