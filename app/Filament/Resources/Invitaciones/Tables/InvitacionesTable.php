<?php

namespace App\Filament\Resources\Invitaciones\Tables;

use App\Filament\Resources\Invitaciones\InvitacionResource;
use App\Models\Invitacion;
use Filament\Actions\Action;
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
                    })
                    ->color(fn (Invitacion $record): string => match (true) {
                        $record->aceptada_en !== null => 'success',
                        $record->expira_en->isPast() => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('expira_en')->label('Caduca')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->defaultSort('expira_en', 'desc')
            ->recordActions([
                DeleteAction::make()
                    ->label('Revocar')
                    ->modalHeading('¿Revocar invitación?')
                    ->modalDescription('El enlace enviado por correo dejará de ser válido inmediatamente.')
                    ->modalSubmitActionLabel('Sí, revocar'),
            ])
            ->emptyStateHeading('No hay invitaciones enviadas')
            ->emptyStateDescription('Invita a administradores o editores para colaborar en la gestión de tu restaurante.')
            ->emptyStateIcon('heroicon-o-envelope')
            ->emptyStateActions([
                Action::make('crear')
                    ->label('Invitar colaborador')
                    ->icon('heroicon-m-plus')
                    ->button()
                    ->url(fn (): string => InvitacionResource::getUrl('create')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('Revocar'),
                ]),
            ]);
    }
}
