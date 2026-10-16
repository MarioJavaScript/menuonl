<?php

namespace App\Filament\Resources\Productos\Tables;

use App\Models\Producto;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['traducciones', 'restaurante']))
            ->columns([
                TextColumn::make('nombre')
                    ->label('Nombre')
                    ->state(fn (Producto $record): ?string => $record->traduccion()?->nombre),
                TextInputColumn::make('precio')
                    ->label('Precio')
                    ->type('number')
                    ->rules(['required', 'numeric', 'min:0', 'max:99999999.99'])
                    ->sortable(),
                ToggleColumn::make('disponible')->label('Disponible'),
                ToggleColumn::make('visible')->label('Visible'),
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
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
