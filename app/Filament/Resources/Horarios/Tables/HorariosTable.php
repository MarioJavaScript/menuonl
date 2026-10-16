<?php

namespace App\Filament\Resources\Horarios\Tables;

use App\Services\Catalogos;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class HorariosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('dia_semana')->label('Día')->formatStateUsing(fn (int $state): string => Catalogos::diasSemana()[$state] ?? (string) $state)->sortable(),
                TextColumn::make('abre')->label('Abre'),
                TextColumn::make('cierra')->label('Cierra'),
                IconColumn::make('cerrado')->label('Cerrado')->boolean(),
            ])
            ->defaultSort('dia_semana')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
