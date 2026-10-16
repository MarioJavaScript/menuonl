<?php

namespace App\Filament\Resources\CodigosQr\Pages;

use App\Filament\Resources\CodigosQr\CodigoQrResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCodigosQr extends ListRecords
{
    protected static string $resource = CodigoQrResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
