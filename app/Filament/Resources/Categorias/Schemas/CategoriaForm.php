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
                    ->helperText('Desmárcala si quieres ocultar temporalmente esta categoría y sus platillos de tu menú.')
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
                    ->helperText('Define la posición para ordenar las secciones de tu menú (por ejemplo: 1 para Entradas, 2 para Platos fuertes).')
                    ->validationAttribute('orden')
                    ->validationMessages([
                        'required' => 'Escribe el número de orden.',
                        'integer' => 'El orden debe ser un número entero.',
                        'min' => 'El orden no puede ser negativo.',
                    ]),
            ]);
    }
}
