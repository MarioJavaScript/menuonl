<?php

namespace App\Filament\Resources\Categorias\Tables;

use App\Filament\Resources\Categorias\CategoriaResource;
use App\Models\Categoria;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CategoriasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['traducciones', 'restaurante']))
            ->columns([
                TextColumn::make('nombre')
                    ->label('Nombre')
                    ->state(fn (Categoria $record): ?string => $record->traduccion()?->nombre)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('traducciones', fn (Builder $q): Builder => $q->where('nombre', 'like', "%{$search}%"))),
                IconColumn::make('visible')->label('Visible')->boolean(),
                TextColumn::make('orden')->label('Orden')->sortable(),
            ])
            ->defaultSort('orden')
            ->reorderable('orden')
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
            ])
            ->emptyStateHeading('No hay categorías registradas')
            ->emptyStateDescription('Crea categorías (como Entradas, Platos Fuertes o Bebidas) para estructurar tu menú público.')
            ->emptyStateIcon('heroicon-o-rectangle-stack')
            ->emptyStateActions([
                Action::make('crear')
                    ->label('Crear primera categoría')
                    ->icon('heroicon-m-plus')
                    ->button()
                    ->url(fn (): string => CategoriaResource::getUrl('create')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
