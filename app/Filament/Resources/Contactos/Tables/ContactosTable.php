<?php

namespace App\Filament\Resources\Contactos\Tables;

use App\Filament\Resources\Contactos\ContactoResource;
use App\Services\Catalogos;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ContactosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tipo')->label('Tipo')->formatStateUsing(fn (string $state): string => Catalogos::tiposContacto()[$state] ?? $state),
                TextColumn::make('etiqueta')->label('Etiqueta'),
                TextColumn::make('valor')->label('Valor')->limit(50),
                IconColumn::make('visible')->label('Visible')->boolean(),
                TextColumn::make('orden')->label('Orden')->sortable(),
            ])
            ->defaultSort('orden')
            ->reorderable('orden')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('No hay canales de contacto registrados')
            ->emptyStateDescription('Añade WhatsApp, teléfono, redes sociales o tu dirección para que los comensales puedan comunicarse directamente desde el menú.')
            ->emptyStateIcon('heroicon-o-phone')
            ->emptyStateActions([
                Action::make('crear')
                    ->label('Agregar canal de contacto')
                    ->icon('heroicon-m-plus')
                    ->button()
                    ->url(fn (): string => ContactoResource::getUrl('create')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
