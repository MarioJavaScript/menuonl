<?php

namespace App\Filament\Resources\Contactos\Tables;

use App\Services\Catalogos;
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
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
