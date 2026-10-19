<?php

namespace App\Filament\Resources\Restaurantes\Tables;

use App\Filament\Resources\Restaurantes\RestauranteResource;
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
                Action::make('verMenu')
                    ->label('Ver menú')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (Restaurante $record): string => route('menu.publico', ['slug' => $record->slug]))
                    ->openUrlInNewTab(),
                Action::make('sitioWeb')
                    ->label('Sitio web')
                    ->icon('heroicon-o-globe-alt')
                    ->color('gray')
                    ->url(fn (Restaurante $record): string => RestauranteResource::getUrl('sitio', ['record' => $record])),
                EditAction::make(),
                Action::make('suspender')
                    ->label('Suspender')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('¿Suspender restaurante?')
                    ->modalDescription('El menú público y el acceso al panel para este restaurante quedarán inactivos.')
                    ->modalSubmitActionLabel('Sí, suspender')
                    ->visible(fn (Restaurante $record): bool => $record->isActivo())
                    ->authorize('update')
                    ->action(fn (Restaurante $record): bool => $record->update(['estado' => 'suspendido'])),
                Action::make('reactivar')
                    ->label('Reactivar')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('¿Reactivar restaurante?')
                    ->modalDescription('El menú público y las operaciones del restaurante volverán a estar activas.')
                    ->modalSubmitActionLabel('Sí, reactivar')
                    ->visible(fn (Restaurante $record): bool => ! $record->isActivo())
                    ->authorize('update')
                    ->action(fn (Restaurante $record): bool => $record->update(['estado' => 'activo'])),
            ])
            ->emptyStateHeading('No hay restaurantes registrados')
            ->emptyStateDescription('Registra un nuevo restaurante en la plataforma para iniciar sus operaciones.')
            ->emptyStateIcon('heroicon-o-building-storefront')
            ->emptyStateActions([
                Action::make('crear')
                    ->label('Crear nuevo restaurante')
                    ->icon('heroicon-m-plus')
                    ->button()
                    ->url(fn (): string => RestauranteResource::getUrl('create')),
            ]);
    }
}
