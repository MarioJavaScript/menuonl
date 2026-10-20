<?php

namespace App\Filament\Resources\Categorias\Tables;

use App\Filament\Resources\Categorias\CategoriaResource;
use App\Models\Categoria;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
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
                    ->weight('semibold')
                    ->state(fn (Categoria $record): ?string => self::nombre($record))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('traducciones', fn (Builder $q): Builder => $q->where('nombre', 'like', "%{$search}%"))),
                TextColumn::make('productos_count')
                    ->counts('productos')
                    ->label('Platillos'),
                IconColumn::make('visible')->label('Visible')->boolean(),
                TextColumn::make('orden')->label('Orden')->sortable(),
            ])
            ->stackedOnMobile()
            ->defaultSort('orden')
            ->reorderable('orden')
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make()
                    ->extraAttributes(fn (Categoria $record): array => ['aria-label' => 'Editar '.self::nombre($record)]),
                ActionGroup::make([
                    DeleteAction::make()
                        ->label('Eliminar categoría')
                        ->color('gray')
                        ->modalHeading(fn (Categoria $record): string => '¿Eliminar "'.self::nombre($record).'"?')
                        ->modalDescription('Los platillos de esta categoría dejarán de mostrarse en tu menú. Puedes recuperarla desde "Eliminados".')
                        ->modalSubmitActionLabel('Eliminar categoría')
                        ->modalSubmitAction(fn (Action $action): Action => $action->color('danger'))
                        ->successNotification(fn (Categoria $record): Notification => Notification::make()
                            ->success()
                            ->title(self::nombre($record).' se eliminó')
                            ->actions([
                                Action::make('deshacer')
                                    ->label('Deshacer')
                                    ->link()
                                    ->dispatch('deshacer-eliminar-categoria', ['id' => $record->getKey()])
                                    ->close(),
                            ])),
                    RestoreAction::make()
                        ->label('Recuperar categoría')
                        ->modalHeading(fn (Categoria $record): string => '¿Recuperar "'.self::nombre($record).'"?')
                        ->modalDescription('Volverá a mostrarse en tu menú junto con sus platillos.')
                        ->modalSubmitActionLabel('Recuperar categoría')
                        ->successNotification(fn (Categoria $record): Notification => Notification::make()
                            ->success()
                            ->title(self::nombre($record).' se recuperó')),
                ])
                    ->label('Más acciones')
                    ->icon(Heroicon::EllipsisHorizontal)
                    ->extraAttributes(fn (Categoria $record): array => ['aria-label' => 'Más acciones de '.self::nombre($record)]),
            ])
            ->emptyStateHeading('Todavía no tienes categorías')
            ->emptyStateDescription('Crea la primera para organizar tu menú, por ejemplo: Entradas, Platos fuertes o Bebidas.')
            ->emptyStateIcon('heroicon-o-rectangle-stack')
            ->emptyStateActions([
                Action::make('crear')
                    ->label('Agregar categoría')
                    ->icon('heroicon-m-plus')
                    ->button()
                    ->url(fn (): string => CategoriaResource::getUrl('create')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Eliminar seleccionadas')
                        ->modalHeading('¿Eliminar las categorías seleccionadas?')
                        ->modalDescription('Los platillos de estas categorías dejarán de mostrarse en tu menú. Puedes recuperarlas desde "Eliminados".')
                        ->modalSubmitActionLabel('Eliminar categorías')
                        ->modalSubmitAction(fn (Action $action): Action => $action->color('danger')),
                    RestoreBulkAction::make()
                        ->label('Recuperar seleccionadas'),
                ])->label('Acciones con las seleccionadas'),
            ]);
    }

    private static function nombre(Categoria $record): string
    {
        return $record->traduccion()?->nombre ?? 'Categoría sin nombre';
    }
}
