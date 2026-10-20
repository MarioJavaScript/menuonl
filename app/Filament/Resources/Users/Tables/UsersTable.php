<?php

namespace App\Filament\Resources\Users\Tables;

use App\Filament\Resources\Invitaciones\InvitacionResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nombre')->searchable(),
                TextColumn::make('email')->label('Correo')->searchable(),
                TextColumn::make('rol')
                    ->label('Rol en este restaurante')
                    ->state(fn (User $record): string => $record->isSuperadmin()
                        ? 'Superadministrador'
                        : ucfirst((string) $record->rolEn(Filament::getTenant()))),
            ])
            ->defaultSort('name')
            ->recordActions([
                Action::make('revocarAcceso')
                    ->label('Quitar acceso')
                    ->icon('heroicon-o-user-minus')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('¿Quitar acceso al usuario?')
                    ->modalDescription('El usuario ya no podrá ingresar ni administrar este restaurante.')
                    ->modalSubmitActionLabel('Sí, quitar acceso')
                    ->authorize('revocarAcceso')
                    ->action(function (User $record): void {
                        $record->restaurantes()->detach(Filament::getTenant()->getKey());
                        $record->olvidarRoles();

                        Notification::make()->success()->title('Acceso revocado')->send();
                    }),
            ])
            ->emptyStateHeading('No hay miembros adicionales en el equipo')
            ->emptyStateDescription('Envía una invitación desde la sección de Invitaciones para añadir nuevos colaboradores con acceso a este restaurante.')
            ->emptyStateIcon('heroicon-o-users')
            ->emptyStateActions([
                Action::make('invitar')
                    ->label('Ir a Invitaciones')
                    ->icon('heroicon-m-envelope')
                    ->button()
                    ->url(fn (): string => InvitacionResource::getUrl('index')),
            ]);
    }
}
