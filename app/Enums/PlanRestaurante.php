<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Facades\Lang;

enum PlanRestaurante: string implements HasColor, HasLabel
{
    /** Aura: sitio y menú, los dos editables por el propietario, con reservas y pedidos por WhatsApp. */
    case SitioMenu = 'sitio_menu';

    /** Lite: solo el menú, editable por el propietario, con pedidos por WhatsApp; sin sitio ni reservas. */
    case SoloMenu = 'solo_menu';

    /**
     * Valor del plan retirado "Completo". La migración 2026_11_04_201124_planes_aura_y_lite lo pasa a `sitio_menu`; mientras tanto (por
     * ejemplo, durante el despliegue o en un respaldo viejo) se sigue leyendo como Aura, para que ningún restaurante
     * pierda su sitio.
     */
    public const string VALOR_RETIRADO_COMPLETO = 'completo';

    /**
     * Valor guardado en la base a plan. El valor retirado `completo` se lee como Aura; un valor desconocido o vacío,
     * como el plan más restrictivo (D12).
     */
    public static function desde(?string $valor): self
    {
        if ($valor === self::VALOR_RETIRADO_COMPLETO) {
            return self::SitioMenu;
        }

        return self::tryFrom((string) $valor) ?? self::SoloMenu;
    }

    /**
     * El valor que entrega un campo de formulario: Filament devuelve el enum ya construido, otras vías un texto.
     * Cualquier otra cosa (incluido un texto fuera de los dos valores, y el retirado `completo`) da null, nunca un plan
     * por defecto.
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
     * Capacidades del plan para quien no es superadmin. Aura incluye todo. Lite incluye el panel completo del menú
     * (apariencia, menú, datos, contactos, horario, QR, equipo y métricas), pero no el sitio ni las reservas.
     */
    public function incluye(Capacidad $capacidad): bool
    {
        return match ($this) {
            self::SitioMenu => true,
            self::SoloMenu => ! in_array($capacidad, [Capacidad::Sitio, Capacidad::Reservas], true),
        };
    }

    /**
     * Nivel del plan de 1 (el que menos incluye) a la cantidad de planes (el que más), para el glifo de barras. Sale de la misma tabla
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
            self::SitioMenu => 'success',
            self::SoloMenu => 'info',
        };
    }
}
