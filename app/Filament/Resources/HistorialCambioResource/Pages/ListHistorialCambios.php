<?php

namespace App\Filament\Resources\HistorialCambioResource\Pages;

use App\Filament\Resources\HistorialCambioResource;
use Filament\Resources\Pages\ListRecords;

class ListHistorialCambios extends ListRecords
{
    protected static string $resource = HistorialCambioResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
