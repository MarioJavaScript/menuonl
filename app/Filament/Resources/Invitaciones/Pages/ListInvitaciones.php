<?php

namespace App\Filament\Resources\Invitaciones\Pages;

use App\Filament\Resources\Invitaciones\InvitacionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInvitaciones extends ListRecords
{
    protected static string $resource = InvitacionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
