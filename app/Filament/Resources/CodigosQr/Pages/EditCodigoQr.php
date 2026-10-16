<?php

namespace App\Filament\Resources\CodigosQr\Pages;

use App\Filament\Resources\CodigosQr\CodigoQrResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCodigoQr extends EditRecord
{
    protected static string $resource = CodigoQrResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
