<?php

namespace App\Filament\Resources\Restaurantes\Actions;

use App\Enums\Capacidad;
use App\Enums\PlanRestaurante;
use App\Models\Reserva;
use App\Models\Restaurante;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * "Cambiar plan" de Plataforma (docs/planes.md, D9). Solo el superadmin la ve y la ejecuta: la política `cambiarPlan`
 * la oculta y la rechaza, y Restaurante::cambiarPlan() la vuelve a comprobar al guardar. La ventana explica,
 * en lenguaje claro, qué gana y qué pierde el restaurante antes de confirmar.
 */
class CambiarPlanAction
{
    public static function make(): Action
    {
        return Action::make('cambiarPlan')
            ->label(__('panel.planes.cambiar.accion'))
            ->icon('heroicon-o-arrows-right-left')
            ->color('warning')
            ->authorize('cambiarPlan')
            ->modalHeading(fn (Restaurante $record): string => __('panel.planes.cambiar.titulo', ['restaurante' => $record->nombre]))
            ->modalSubmitActionLabel(__('panel.planes.cambiar.boton'))
            ->fillForm(fn (Restaurante $record): array => ['plan' => $record->plan->value])
            ->schema(fn (Restaurante $record): array => [
                Select::make('plan')
                    ->label(__('panel.planes.cambiar.campo'))
                    ->options(PlanRestaurante::class)
                    ->selectablePlaceholder(false)
                    ->required()
                    ->rule(Rule::enum(PlanRestaurante::class))
                    ->live(),
                Html::make(fn (Get $get): HtmlString => self::explicacion($record, PlanRestaurante::deFormulario($get('plan')))),
            ])
            ->action(function (array $data, Restaurante $record): void {
                $nuevo = PlanRestaurante::deFormulario($data['plan'] ?? null);

                if ($nuevo === null) {
                    throw ValidationException::withMessages(['plan' => __('validation.in', ['attribute' => __('panel.planes.cambiar.campo')])]);
                }

                if ($record->cambiarPlan($nuevo, auth()->user())) {
                    Notification::make()
                        ->title(__('panel.planes.cambiar.notificacion', ['plan' => $nuevo->getLabel()]))
                        ->success()
                        ->send();
                }
            });
    }

    /**
     * Texto de la ventana: plan actual, lo que se gana, lo que se pierde y lo que no cambia.
     */
    public static function explicacion(Restaurante $restaurante, ?PlanRestaurante $nuevo): HtmlString
    {
        $actual = $restaurante->plan;

        if ($nuevo === null || $nuevo === $actual) {
            return new HtmlString(view('filament.partials.cambio-de-plan', [
                'actual' => $actual->getLabel(),
                'cambios' => null,
                'reservasPendientes' => 0,
            ])->render());
        }

        $reservasPendientes = $actual->incluye(Capacidad::Reservas) && ! $nuevo->incluye(Capacidad::Reservas)
            ? Reserva::withoutGlobalScopes()->where('restaurante_id', $restaurante->getKey())->where('estado', 'pendiente')->count()
            : 0;

        return new HtmlString(view('filament.partials.cambio-de-plan', [
            'actual' => $actual->getLabel(),
            'cambios' => $actual->cambiosHacia($nuevo),
            'reservasPendientes' => $reservasPendientes,
        ])->render());
    }
}
