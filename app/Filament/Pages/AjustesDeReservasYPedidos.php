<?php

namespace App\Filament\Pages;

use App\Enums\Capacidad;
use App\Filament\Concerns\EditaAjustesDelRestaurante;
use App\Models\Restaurante;
use BackedEnum;
use Closure;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Ajustes de reservas y pedidos: pedidos por WhatsApp (activar, número, modalidades de entrega y mensaje) y reservas
 * desde el sitio (activar, cupo y duración).
 *
 * Aura y Lite configuran sus pedidos por WhatsApp completos. La sección de reservas solo existe si el plan incluye
 * reservas (Aura); en Lite no aparece para nadie y sus campos se descartan al guardar aunque lleguen en la petición.
 */
class AjustesDeReservasYPedidos extends Page
{
    use EditaAjustesDelRestaurante;

    protected static ?string $slug = 'ajustes-de-reservas-y-pedidos';

    protected static ?int $navigationSort = 10;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static function capacidad(): Capacidad
    {
        return Capacidad::EditarTelefonos;
    }

    /**
     * Los campos de reservas solo se guardan si el plan incluye reservas.
     *
     * @return list<string>|null
     */
    protected function camposEditables(Restaurante $restaurante): ?array
    {
        $pedidos = ['pedidos_activos', 'whatsapp_pedidos', 'pedidos_domicilio', 'pedidos_recoger', 'pedidos_mensaje'];

        return $restaurante->incluye(Capacidad::Reservas)
            ? [...$pedidos, 'reservas_activas', 'reservas_cupo', 'reservas_franja_min']
            : $pedidos;
    }

    /**
     * @return array<string, mixed>
     */
    protected function datosIniciales(Restaurante $restaurante): array
    {
        $datos = $restaurante->attributesToArray();
        $datos['whatsapp_pedidos'] = $restaurante->whatsappPedidosDigitos() ?? $restaurante->whatsapp_pedidos;

        return $datos;
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('panel.grupos.pedidos');
    }

    public static function getNavigationLabel(): string
    {
        return __('panel.pedidos.nav');
    }

    public function getTitle(): string
    {
        return __('panel.pedidos.titulo');
    }

    public function getSubheading(): ?string
    {
        return (Filament::getTenant()?->incluye(Capacidad::Reservas) ?? false)
            ? __('panel.pedidos.subtitulo')
            : __('panel.pedidos.subtitulo_sin_reservas');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('panel.pedidos.whatsapp.titulo'))
                ->description(__('panel.pedidos.whatsapp.descripcion'))
                ->schema([
                    Toggle::make('pedidos_activos')
                        ->label(__('panel.pedidos.whatsapp.activar'))
                        ->helperText(__('panel.pedidos.whatsapp.activar_ayuda'))
                        ->live(),
                    TextInput::make('whatsapp_pedidos')
                        ->label(__('panel.pedidos.whatsapp.numero'))
                        ->placeholder('5215512345678')
                        ->helperText(__('panel.pedidos.whatsapp.numero_ayuda'))
                        ->regex('/^[0-9]{8,20}$/')
                        ->validationMessages([
                            'regex' => __('panel.pedidos.whatsapp.numero_invalido'),
                            'required' => __('panel.pedidos.whatsapp.numero_requerido'),
                        ])
                        ->required(fn (Get $get): bool => (bool) $get('pedidos_activos'))
                        ->maxLength(20),
                    Toggle::make('pedidos_domicilio')
                        ->label(__('panel.pedidos.whatsapp.domicilio'))
                        ->helperText(__('panel.pedidos.whatsapp.domicilio_ayuda'))
                        ->live(),
                    Toggle::make('pedidos_recoger')
                        ->label(__('panel.pedidos.whatsapp.recoger'))
                        ->helperText(__('panel.pedidos.whatsapp.recoger_ayuda'))
                        ->live()
                        ->rules([
                            fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                if ($get('pedidos_activos') && ! $value && ! $get('pedidos_domicilio')) {
                                    $fail(__('panel.pedidos.whatsapp.sin_modalidad'));
                                }
                            },
                        ]),
                    Textarea::make('pedidos_mensaje')
                        ->label(__('panel.pedidos.whatsapp.mensaje'))
                        ->helperText(__('panel.pedidos.whatsapp.mensaje_ayuda'))
                        ->placeholder(__('panel.pedidos.whatsapp.mensaje_ejemplo'))
                        ->rows(2)
                        ->maxLength(200)
                        ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? trim($state) : null),
                ]),
            Section::make(__('panel.pedidos.reservas.titulo'))
                ->description(__('panel.pedidos.reservas.descripcion'))
                ->visible(fn (): bool => Filament::getTenant()?->incluye(Capacidad::Reservas) ?? false)
                ->schema([
                    Toggle::make('reservas_activas')
                        ->label(__('panel.pedidos.reservas.activar'))
                        ->helperText(__('panel.pedidos.reservas.activar_ayuda'))
                        ->live(),
                    Text::make(__('panel.pedidos.reservas.sin_horario'))
                        ->visible(fn (Get $get): bool => (bool) $get('reservas_activas') && ! $this->tieneHorario()),
                    TextInput::make('reservas_cupo')
                        ->label(__('panel.pedidos.reservas.cupo'))
                        ->helperText(__('panel.pedidos.reservas.cupo_ayuda'))
                        ->numeric()
                        ->integer()
                        ->minValue(1)
                        ->maxValue(500)
                        ->required(),
                    Select::make('reservas_franja_min')
                        ->label(__('panel.pedidos.reservas.duracion'))
                        ->helperText(__('panel.pedidos.reservas.duracion_ayuda'))
                        ->options([
                            30 => __('panel.pedidos.reservas.minutos', ['n' => 30]),
                            60 => __('panel.pedidos.reservas.una_hora'),
                            90 => __('panel.pedidos.reservas.hora_y_media'),
                            120 => __('panel.pedidos.reservas.dos_horas'),
                        ])
                        ->native(false)
                        ->required(),
                ]),
        ]);
    }

    private function tieneHorario(): bool
    {
        return Filament::getTenant()->horarios()->where('cerrado', false)->exists();
    }
}
