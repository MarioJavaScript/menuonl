<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PlanRestaurante: string implements HasColor, HasLabel
{
    case Completo = 'completo';
    case SitioMenu = 'sitio_menu';
    case SoloMenu = 'solo_menu';

    /**
     * Un valor desconocido o vacío en la base se trata como el plan más restrictivo (D12).
     */
    public static function desde(?string $valor): self
    {
        return self::tryFrom((string) $valor) ?? self::SoloMenu;
    }

    /**
     * Capacidades del plan para quien no es superadmin. Sitio y menú: "servicio" se limita en /panel
     * al módulo de reservas (D2); pedidos, QR y equipo los gestiona solo el superadmin (D3, D4).
     */
    public function incluye(Capacidad $capacidad): bool
    {
        return match ($this) {
            self::Completo => true,
            self::SitioMenu => in_array($capacidad, [
                Capacidad::Sitio,
                Capacidad::Reservas,
                Capacidad::Panel,
                Capacidad::EditarTelefonos,
                Capacidad::EditarHorario,
                Capacidad::Servicio,
            ], true),
            self::SoloMenu => false,
        };
    }

    public function getLabel(): string
    {
        return __('panel.planes.'.$this->value.'.nombre');
    }

    public function descripcion(): string
    {
        return __('panel.planes.'.$this->value.'.descripcion');
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Completo => 'success',
            self::SitioMenu => 'info',
            self::SoloMenu => 'gray',
        };
    }
}
