<?php

namespace App\Filament\Resources\Horarios\Schemas;

use App\Services\Catalogos;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class HorarioForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('dia_semana')
                    ->label('Día')
                    ->options(Catalogos::diasSemana())
                    ->in(array_keys(Catalogos::diasSemana()))
                    ->required(),
                Toggle::make('cerrado')->label('Cerrado todo el día')->live(),
                TimePicker::make('abre')->label('Abre')->seconds(false)->required(fn (Get $get): bool => ! $get('cerrado')),
                TimePicker::make('cierra')->label('Cierra (si es menor que la apertura, cierra al día siguiente)')->seconds(false)->required(fn (Get $get): bool => ! $get('cerrado')),
            ]);
    }
}
