<?php

namespace App\Filament\Resources\Productos\Pages;

use App\Filament\Resources\Productos\ProductoResource;
use App\Models\Producto;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditProducto extends EditRecord
{
    protected static string $resource = ProductoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label('Eliminar platillo')
                ->modalHeading(fn (Producto $record): string => '¿Eliminar "'.($record->traduccion()?->nombre ?? 'este platillo').'"?')
                ->modalDescription('Dejará de verse en tu menú. Puedes recuperarlo desde "Eliminados".')
                ->modalSubmitActionLabel('Eliminar platillo')
                ->modalSubmitAction(fn (Action $action): Action => $action->color('danger')),
            RestoreAction::make()
                ->label('Recuperar platillo')
                ->modalHeading(fn (Producto $record): string => '¿Recuperar "'.($record->traduccion()?->nombre ?? 'este platillo').'"?')
                ->modalDescription('Volverá a tu menú con el mismo precio y foto.')
                ->modalSubmitActionLabel('Recuperar platillo'),
        ];
    }
}
