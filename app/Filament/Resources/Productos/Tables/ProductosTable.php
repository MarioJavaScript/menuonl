<?php

namespace App\Filament\Resources\Productos\Tables;

use App\Filament\Resources\Productos\ProductoResource;
use App\Models\Categoria;
use App\Models\Producto;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['traducciones', 'restaurante', 'categoria.traducciones']))
            ->columns([
                TextColumn::make('nombre')
                    ->label('Nombre')
                    ->state(fn (Producto $record): ?string => $record->traduccion()?->nombre)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('traducciones', fn (Builder $q): Builder => $q->where('nombre', 'like', "%{$search}%")
                    )
                    )
                    ->description(fn (Producto $record): ?string => $record->categoria?->traduccion()?->nombre),
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
                SelectFilter::make('categoria_id')
                    ->label('Categoría')
                    ->options(fn (): array => Categoria::query()
                        ->with('traducciones')
                        ->orderBy('orden')
                        ->get()
                        ->mapWithKeys(fn (Categoria $c): array => [$c->id => $c->traduccion()?->nombre ?? "#{$c->id}"])
                        ->all()
                    ),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
            ])
            ->emptyStateHeading('No hay platillos en el menú')
            ->emptyStateDescription('Comienza agregando platillos con sus precios, fotos, variantes y etiquetas dietéticas para que tus clientes puedan ordenar.')
            ->emptyStateIcon('heroicon-o-cake')
            ->emptyStateActions([
                Action::make('crear')
                    ->label('Agregar primer platillo')
                    ->icon('heroicon-m-plus')
                    ->button()
                    ->url(fn (): string => ProductoResource::getUrl('create')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
