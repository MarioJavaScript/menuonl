<?php

namespace App\Filament\Resources\Invitaciones\Tables;

use App\Filament\Resources\Invitaciones\InvitacionResource;
use App\Models\Invitacion;
use App\Services\InvitacionService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

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
                TextColumn::make('envio')
                    ->label('Correo')
                    ->badge()
                    ->state(fn (Invitacion $record): string => match (true) {
                        $record->aceptada_en !== null => 'Aceptada',
                        $record->envioFallido() => 'No se envió',
                        $record->envioEnCola() => 'En cola',
                        default => 'Enviado',
                    })
                    ->color(fn (Invitacion $record): string => match (true) {
                        $record->aceptada_en !== null => 'gray',
                        $record->envioFallido() => 'danger',
                        $record->envioEnCola() => 'warning',
                        default => 'success',
                    })
                    ->tooltip(fn (Invitacion $record): ?string => $record->envioFallido() ? $record->envio_error : null),
                TextColumn::make('expira_en')->label('Caduca')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->defaultSort('expira_en', 'desc')
            ->recordActions([
                Action::make('reenviar')
                    ->label('Reenviar')
                    ->icon('heroicon-o-paper-airplane')
                    ->requiresConfirmation()
                    ->modalHeading('¿Reenviar invitación?')
                    ->modalDescription('Se genera un enlace nuevo y el anterior deja de funcionar.')
                    ->modalSubmitActionLabel('Sí, reenviar')
                    ->visible(fn (Invitacion $record): bool => $record->aceptada_en === null)
                    ->authorize('create')
                    ->action(function (Invitacion $record): void {
                        try {
                            app(InvitacionService::class)->reenviar($record, auth()->user());
                        } catch (ValidationException $e) {
                            Notification::make()->danger()->title(collect($e->errors())->flatten()->first())->send();

                            return;
                        }

                        Notification::make()->success()->title('Invitación reenviada')->body('El correo se enviará en unos momentos.')->send();
                    }),
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
