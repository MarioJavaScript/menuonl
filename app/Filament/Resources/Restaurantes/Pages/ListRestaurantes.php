<?php

namespace App\Filament\Resources\Restaurantes\Pages;

use App\Filament\Resources\Restaurantes\RestauranteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRestaurantes extends ListRecords
{
    protected static string $resource = RestauranteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
