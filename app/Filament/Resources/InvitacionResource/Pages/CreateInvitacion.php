<?php

namespace App\Filament\Resources\InvitacionResource\Pages;

use App\Filament\Resources\InvitacionResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateInvitacion extends CreateRecord
{
    protected static string $resource = InvitacionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['token'] = Str::random(64);
        $data['expira_en'] = now()->addDays(7);
        $data['invitado_por'] = auth()->id();

        return $data;
    }
}
