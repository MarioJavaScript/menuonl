<?php

namespace App\Filament\Resources\Categorias\Schemas;

use App\Filament\Components\TraduccionesRepeater;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CategoriaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TraduccionesRepeater::make('traducciones', 120)
                    ->label('Nombre y descripción')
                    ->columnSpanFull(),
                Toggle::make('visible')
                    ->label('Mostrar en el menú público')
                    ->helperText(__('panel.menu.categoria.visible_ayuda'))
                    ->default(true),
            ]);
    }
}
