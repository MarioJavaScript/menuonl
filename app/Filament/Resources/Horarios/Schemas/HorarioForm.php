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
                    ->label('Día de la semana')
                    ->placeholder('Selecciona un día')
                    ->options(Catalogos::diasSemana())
                    ->in(array_keys(Catalogos::diasSemana()))
                    ->required(),
                Toggle::make('cerrado')
                    ->label('Cerrado todo el día')
                    ->helperText('Marca esta opción si el restaurante no abre al público en este día.')
                    ->live(),
                TimePicker::make('abre')
                    ->label('Hora de apertura')
                    ->placeholder('09:00')
                    ->helperText('Hora en que inicia el servicio.')
                    ->seconds(false)
                    ->disabled(fn (Get $get): bool => (bool) $get('cerrado'))
                    ->required(fn (Get $get): bool => ! $get('cerrado')),
                TimePicker::make('cierra')
                    ->label('Hora de cierre')
                    ->placeholder('23:00')
                    ->helperText('Si la hora de cierre es menor que la de apertura (ej. 02:00), el turno cerrará al día siguiente.')
                    ->seconds(false)
                    ->disabled(fn (Get $get): bool => (bool) $get('cerrado'))
                    ->required(fn (Get $get): bool => ! $get('cerrado')),
            ]);
    }
}
