<?php

namespace App\Filament\Resources\Categorias\Schemas;

use App\Filament\Components\TraduccionesRepeater;
use Filament\Forms\Components\TextInput;
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
                    ->helperText('Determina si la categoría y sus platillos se muestran en el menú público.')
                    ->default(true),
                TextInput::make('orden')
                    ->label('Orden de aparición')
                    ->placeholder('0')
                    ->numeric()
                    ->integer()
                    ->minValue(0)
                    ->maxValue(1000000)
                    ->default(0)
                    ->required()
                    ->helperText('Posición numérica para ordenar las secciones del menú público.'),
            ]);
    }
}
