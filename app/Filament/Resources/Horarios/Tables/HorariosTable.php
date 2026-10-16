<?php

namespace App\Filament\Resources\Horarios\Tables;

use App\Filament\Resources\Horarios\HorarioResource;
use App\Services\Catalogos;
use Filament\Actions\Action;
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
            ->emptyStateHeading('Sin horarios de atención configurados')
            ->emptyStateDescription('Establece los días de la semana y turnos de atención para que tus comensales sepan cuándo ordenar.')
            ->emptyStateIcon('heroicon-o-clock')
            ->emptyStateActions([
                Action::make('crear')
                    ->label('Agregar horario')
                    ->icon('heroicon-m-plus')
                    ->button()
                    ->url(fn (): string => HorarioResource::getUrl('create')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
