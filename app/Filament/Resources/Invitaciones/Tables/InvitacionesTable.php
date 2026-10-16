<?php

namespace App\Filament\Resources\Invitaciones\Tables;

use App\Models\Invitacion;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InvitacionesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('email')->label('Correo')->searchable(),
                TextColumn::make('rol')->label('Rol')->badge()->formatStateUsing(fn (string $state): string => ucfirst($state)),
                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->state(fn (Invitacion $record): string => match (true) {
                        $record->aceptada_en !== null => 'Aceptada',
                        $record->expira_en->isPast() => 'Caducada',
                        default => 'Pendiente',
                    }),
                TextColumn::make('expira_en')->label('Caduca')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->defaultSort('expira_en', 'desc')
            ->recordActions([
                DeleteAction::make()->label('Revocar'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('Revocar'),
                ]),
            ]);
    }
}
