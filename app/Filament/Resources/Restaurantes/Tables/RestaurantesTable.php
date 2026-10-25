<?php

namespace App\Filament\Resources\Restaurantes\Tables;

use App\Enums\PlanRestaurante;
use App\Filament\Resources\Restaurantes\Actions\CambiarPlanAction;
use App\Filament\Resources\Restaurantes\RestauranteResource;
use App\Models\Invitacion;
use App\Models\Restaurante;
use App\Models\Scopes\RestauranteScope;
use App\Services\InvitacionService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RestaurantesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')->label('Nombre')->searchable()->sortable(),
                TextColumn::make('slug')->label('Enlace')->searchable(),
                TextColumn::make('plan')->label('Plan')->badge()->sortable(),
                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'activo' ? 'success' : 'danger'),
                TextColumn::make('invitaciones_fallidas_count')
                    ->label('Invitación')
                    ->badge()
                    ->color('danger')
                    ->placeholder('—')
                    ->formatStateUsing(fn (mixed $state): ?string => (int) $state > 0 ? 'Correo no enviado' : null),
                TextColumn::make('created_at')->label('Alta')->dateTime('d/m/Y')->sortable(),
            ])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount([
                'invitaciones as invitaciones_fallidas_count' => fn (Builder $invitaciones): Builder => $invitaciones
                    ->withoutGlobalScope(RestauranteScope::class)
                    ->conEnvioFallido(),
            ]))
            ->defaultSort('nombre')
            ->filters([
                SelectFilter::make('plan')->label('Plan')->options(PlanRestaurante::class),
            ])
            ->recordActions([
                Action::make('verMenu')
                    ->label('Ver menú')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (Restaurante $record): string => route('menu.publico', ['slug' => $record->slug]))
                    ->openUrlInNewTab(),
                EditAction::make(),
                CambiarPlanAction::make(),
                Action::make('reenviarInvitacion')
                    ->label('Reenviar invitación')
                    ->icon('heroicon-o-paper-airplane')
                    ->requiresConfirmation()
                    ->modalHeading('¿Reenviar la invitación?')
                    ->modalDescription('Se genera un enlace nuevo para cada invitación cuyo correo no salió; los enlaces anteriores dejan de funcionar.')
                    ->modalSubmitActionLabel('Sí, reenviar')
                    ->visible(fn (Restaurante $record): bool => (int) $record->invitaciones_fallidas_count > 0)
                    ->authorize('update')
                    ->action(function (Restaurante $record): void {
                        $servicio = app(InvitacionService::class);

                        Invitacion::withoutGlobalScopes()
                            ->where('restaurante_id', $record->getKey())
                            ->conEnvioFallido()
                            ->get()
                            ->each(fn (Invitacion $invitacion) => $servicio->reenviar($invitacion, auth()->user()));

                        Notification::make()->success()->title('Invitación reenviada')->body('El correo se enviará en unos momentos.')->send();
                    }),
                Action::make('suspender')
                    ->label('Suspender')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('¿Suspender restaurante?')
                    ->modalDescription('El menú público y el acceso al panel para este restaurante quedarán inactivos.')
                    ->modalSubmitActionLabel('Sí, suspender')
                    ->visible(fn (Restaurante $record): bool => $record->isActivo())
                    ->authorize('update')
                    ->action(fn (Restaurante $record): bool => $record->update(['estado' => 'suspendido'])),
                Action::make('reactivar')
                    ->label('Reactivar')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('¿Reactivar restaurante?')
                    ->modalDescription('El menú público y las operaciones del restaurante volverán a estar activas.')
                    ->modalSubmitActionLabel('Sí, reactivar')
                    ->visible(fn (Restaurante $record): bool => ! $record->isActivo())
                    ->authorize('update')
                    ->action(fn (Restaurante $record): bool => $record->update(['estado' => 'activo'])),
            ])
            ->emptyStateHeading('No hay restaurantes registrados')
            ->emptyStateDescription('Registra un nuevo restaurante en la plataforma para iniciar sus operaciones.')
            ->emptyStateIcon('heroicon-o-building-storefront')
            ->emptyStateActions([
                Action::make('crear')
                    ->label('Crear nuevo restaurante')
                    ->icon('heroicon-m-plus')
                    ->button()
                    ->url(fn (): string => RestauranteResource::getUrl('create')),
            ]);
    }
}
