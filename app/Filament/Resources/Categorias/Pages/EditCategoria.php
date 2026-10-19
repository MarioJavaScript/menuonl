<?php

namespace App\Filament\Resources\Categorias\Pages;

use App\Filament\Resources\Categorias\CategoriaResource;
use App\Models\Categoria;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditCategoria extends EditRecord
{
    protected static string $resource = CategoriaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label('Eliminar categoría')
                ->modalHeading(fn (Categoria $record): string => '¿Eliminar "'.($record->traduccion()?->nombre ?? 'esta categoría').'"?')
                ->modalDescription('Los platillos dentro de esta categoría dejarán de mostrarse en tu menú. Puedes recuperarla desde "Eliminados".')
                ->modalSubmitActionLabel('Eliminar categoría')
                ->modalSubmitAction(fn (Action $action): Action => $action->color('danger')),
            RestoreAction::make()
                ->label('Recuperar categoría')
                ->modalHeading(fn (Categoria $record): string => '¿Recuperar "'.($record->traduccion()?->nombre ?? 'esta categoría').'"?')
                ->modalDescription('Volverá a mostrarse en tu menú junto con sus platillos.')
                ->modalSubmitActionLabel('Recuperar categoría'),
        ];
    }
}
