<?php

namespace App\Filament\Resources\Productos\Tables;

use App\Filament\Resources\Productos\ProductoResource;
use App\Models\Categoria;
use App\Models\Producto;
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
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\Filter;
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
                ImageColumn::make('imagen')
                    ->label('Foto')
                    ->disk('public')
                    ->square()
                    ->size(48)
                    ->visibleFrom('sm'),
                TextColumn::make('nombre')
                    ->label('Platillo')
                    ->weight('semibold')
                    ->state(fn (Producto $record): ?string => self::nombre($record))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('traducciones', fn (Builder $q): Builder => $q->where('nombre', 'like', "%{$search}%")
                    )
                    )
                    ->description(fn (Producto $record): ?string => $record->categoria?->traduccion()?->nombre),
                TextInputColumn::make('precio')
                    ->label('Precio')
                    ->type('number')
                    ->prefix('$')
                    ->inputMode('decimal')
                    ->step(0.01)
                    ->extraInputAttributes(fn (Producto $record): array => ['aria-label' => 'Precio de '.self::nombre($record)])
                    ->rules(['required', 'numeric', 'min:0', 'max:99999999.99'])
                    ->validationAttribute('Precio')
                    ->validationMessages([
                        'required' => 'Escribe un precio, por ejemplo 85.50.',
                        'numeric' => 'Escribe un precio válido, por ejemplo 85.50.',
                        'min' => 'El precio no puede ser negativo.',
                        'max' => 'El precio es demasiado alto. Revisa la cantidad.',
                    ])
                    ->afterStateUpdated(fn (Producto $record) => Notification::make()
                        ->success()
                        ->title('Precio de '.self::nombre($record).' actualizado')
                        ->send())
                    ->sortable(),
                ToggleColumn::make('disponible')
                    ->label('Disponible')
                    ->afterStateUpdated(fn (Producto $record, bool $state) => Notification::make()
                        ->success()
                        ->title($state ? self::nombre($record).' está disponible' : self::nombre($record).' se marcó como agotado')
                        ->send()),
                ToggleColumn::make('visible')
                    ->label('En el menú')
                    ->afterStateUpdated(fn (Producto $record, bool $state) => Notification::make()
                        ->success()
                        ->title($state ? self::nombre($record).' se muestra en tu menú' : self::nombre($record).' quedó oculto')
                        ->send()),
            ])
            ->stackedOnMobile()
            ->defaultSort('orden')
            ->reorderable('orden')
            ->reorderRecordsTriggerAction(fn (Action $action, bool $isReordering): Action => $action
                ->label($isReordering ? __('panel.menu.orden.terminar') : __('panel.menu.orden.ordenar')))
            ->description(__('panel.menu.orden.ayuda_platillos'))
            ->filters([
                SelectFilter::make('categoria_id')
                    ->label('Categoría')
                    ->placeholder('Todas')
                    ->options(fn (): array => Categoria::query()
                        ->with('traducciones')
                        ->orderBy('orden')
                        ->get()
                        ->mapWithKeys(fn (Categoria $c): array => [$c->id => $c->traduccion()?->nombre ?? 'Sin nombre'])
                        ->all()
                    ),
                Filter::make('agotados')
                    ->label('Solo agotados')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where('disponible', false)),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make()
                    ->extraAttributes(fn (Producto $record): array => ['aria-label' => 'Editar '.self::nombre($record)]),
                ActionGroup::make([
                    DeleteAction::make()
                        ->label('Eliminar platillo')
                        ->color('gray')
                        ->modalHeading(fn (Producto $record): string => '¿Eliminar "'.self::nombre($record).'"?')
                        ->modalDescription('Dejará de verse en tu menú. Puedes recuperarlo desde "Eliminados".')
                        ->modalSubmitActionLabel('Eliminar platillo')
                        ->modalSubmitAction(fn (Action $action): Action => $action->color('danger'))
                        ->successNotification(fn (Producto $record): Notification => Notification::make()
                            ->success()
                            ->title(self::nombre($record).' se eliminó')
                            ->actions([
                                Action::make('deshacer')
                                    ->label('Deshacer')
                                    ->link()
                                    ->dispatch('deshacer-eliminar-platillo', ['id' => $record->getKey()])
                                    ->close(),
                            ])),
                    RestoreAction::make()
                        ->label('Recuperar platillo')
                        ->modalHeading(fn (Producto $record): string => '¿Recuperar "'.self::nombre($record).'"?')
                        ->modalDescription('Volverá a tu menú con el mismo precio y foto.')
                        ->modalSubmitActionLabel('Recuperar platillo')
                        ->successNotification(fn (Producto $record): Notification => Notification::make()
                            ->success()
                            ->title(self::nombre($record).' se recuperó')),
                ])
                    ->label('Más acciones')
                    ->icon(Heroicon::EllipsisHorizontal)
                    ->extraAttributes(fn (Producto $record): array => ['aria-label' => 'Más acciones de '.self::nombre($record)]),
            ])
            ->emptyStateHeading('Todavía no tienes platillos')
            ->emptyStateDescription('Agrega el primero; puedes completar la foto y las opciones después.')
            ->emptyStateIcon('heroicon-o-cake')
            ->emptyStateActions([
                Action::make('crear')
                    ->label('Agregar platillo')
                    ->icon('heroicon-m-plus')
                    ->button()
                    ->url(fn (): string => ProductoResource::getUrl('create')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Eliminar seleccionados')
                        ->modalHeading('¿Eliminar los platillos seleccionados?')
                        ->modalDescription('Dejarán de verse en tu menú. Puedes recuperarlos desde "Eliminados".')
                        ->modalSubmitActionLabel('Eliminar platillos')
                        ->modalSubmitAction(fn (Action $action): Action => $action->color('danger')),
                    RestoreBulkAction::make()
                        ->label('Recuperar seleccionados'),
                ])->label('Acciones con los seleccionados'),
            ]);
    }

    private static function nombre(Producto $record): string
    {
        return $record->traduccion()?->nombre ?? 'Platillo sin nombre';
    }
}
