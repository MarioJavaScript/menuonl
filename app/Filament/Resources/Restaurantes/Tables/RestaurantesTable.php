<?php

namespace App\Filament\Resources\Restaurantes\Tables;

use App\Models\Restaurante;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RestaurantesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')->label('Nombre')->searchable()->sortable(),
                TextColumn::make('slug')->label('Slug')->searchable(),
                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'activo' ? 'success' : 'danger'),
                TextColumn::make('created_at')->label('Alta')->dateTime('d/m/Y')->sortable(),
            ])
            ->defaultSort('nombre')
            ->recordActions([
                EditAction::make(),
                Action::make('suspender')
                    ->label('Suspender')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Restaurante $record): bool => $record->isActivo())
                    ->authorize('update')
                    ->action(fn (Restaurante $record): bool => $record->update(['estado' => 'suspendido'])),
                Action::make('reactivar')
                    ->label('Reactivar')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Restaurante $record): bool => ! $record->isActivo())
                    ->authorize('update')
                    ->action(fn (Restaurante $record): bool => $record->update(['estado' => 'activo'])),
            ]);
    }
}
