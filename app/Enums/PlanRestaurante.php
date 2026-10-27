<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Facades\Lang;

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
     * El valor que entrega un campo de formulario: Filament devuelve el enum ya construido, otras vías un texto.
     * Cualquier otra cosa (incluido un texto fuera de los tres valores) da null, nunca un plan por defecto.
     */
    public static function deFormulario(mixed $valor): ?self
    {
        return match (true) {
            $valor instanceof self => $valor,
            is_string($valor) => self::tryFrom($valor),
            default => null,
        };
    }

    /**
     * Capacidades del plan para quien no es superadmin. Sitio y menú: "servicio" se limita en /panel
     * al módulo de reservas (D2); QR y equipo los gestiona solo el superadmin (D3, D4).
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

    /**
     * Nivel del plan de 1 (el que menos incluye) a 3 (el que más), para el glifo de tres barras. Sale de la misma tabla
     * de incluye(): un plan sube un nivel por cada plan que incluye estrictamente menos capacidades que él.
     */
    public function nivel(): int
    {
        $propias = $this->cantidadDeCapacidades();

        return 1 + count(array_filter(self::cases(), fn (self $plan): bool => $plan->cantidadDeCapacidades() < $propias));
    }

    private function cantidadDeCapacidades(): int
    {
        return count(array_filter(Capacidad::cases(), fn (Capacidad $capacidad): bool => $this->incluye($capacidad)));
    }

    /**
     * Lo que gana y pierde un restaurante al pasar de este plan a otro, en lenguaje claro para el superadmin.
     * Sale de la misma tabla de incluye(), así que no puede contradecirla. Las capacidades sin texto (hoy, métricas,
     * que aún no tiene pantalla) no se mencionan.
     *
     * @return array{gana: list<string>, pierde: list<string>}
     */
    public function cambiosHacia(self $nuevo): array
    {
        $cambios = ['gana' => [], 'pierde' => []];

        foreach (Capacidad::cases() as $capacidad) {
            $antes = $this->incluye($capacidad);
            $despues = $nuevo->incluye($capacidad);

            if ($antes === $despues || ! Lang::has('panel.planes.capacidades.'.$capacidad->value.'.gana')) {
                continue;
            }

            $cambios[$despues ? 'gana' : 'pierde'][] = __('panel.planes.capacidades.'.$capacidad->value.($despues ? '.gana' : '.pierde'));
        }

        return $cambios;
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
