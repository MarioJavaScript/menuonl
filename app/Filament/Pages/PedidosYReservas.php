<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\EditaAjustesDelRestaurante;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
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
 * Pedidos y reservas: pedidos por WhatsApp (activar y número) y reservas desde el sitio (activar, cupo y duración).
 */
class PedidosYReservas extends Page
{
    use EditaAjustesDelRestaurante;

    protected static ?string $slug = 'pedidos-y-reservas';

    protected static ?int $navigationSort = 10;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

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
        return __('panel.pedidos.subtitulo');
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
                ]),
            Section::make(__('panel.pedidos.reservas.titulo'))
                ->description(__('panel.pedidos.reservas.descripcion'))
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
