<?php

namespace App\Services;

use App\Models\EscaneoDiario;
use App\Models\Restaurante;
use App\Models\Scopes\RestauranteScope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Lectura de los escaneos por mesa y por día de UN restaurante (para las pantallas de métricas).
 *
 * Quién puede ver las métricas lo decide EscaneoDiarioPolicy (propietario y superadmin, plan Completo); aquí solo se
 * garantiza el aislamiento: siempre se filtra por el restaurante recibido, el scope global del tenant sigue activo y,
 * dentro del panel de otro restaurante, la consulta se rechaza.
 *
 * Las fechas son días locales del restaurante (Y-m-d), inclusivas. Las reservas no se copian aquí: se cuentan desde `reservas`.
 */
class MetricasDeEscaneos
{
    /**
     * Escaneos por código y día. "mesa" es null en los códigos generales; "mesa" y "nombre" salen del código actual.
     *
     * @return Collection<int, array{codigo_qr_id: int, mesa: string|null, nombre: string, tipo: string, fecha: string, escaneos: int}>
     */
    public function porMesaYDia(Restaurante $restaurante, string $desde, string $hasta): Collection
    {
        return $this->consulta($restaurante, $desde, $hasta)
            ->orderBy('escaneos_diarios.fecha')
            ->orderBy('escaneos_diarios.codigo_qr_id')
            ->orderBy('escaneos_diarios.tipo')
            ->get(['escaneos_diarios.codigo_qr_id', 'codigos_qr.mesa', 'codigos_qr.nombre', 'escaneos_diarios.tipo', 'escaneos_diarios.fecha', 'escaneos_diarios.conteo'])
            ->map(fn (object $fila): array => [
                'codigo_qr_id' => (int) $fila->codigo_qr_id,
                'mesa' => $fila->mesa,
                'nombre' => (string) $fila->nombre,
                'tipo' => (string) $fila->tipo,
                'fecha' => (string) $fila->fecha,
                'escaneos' => (int) $fila->conteo,
            ]);
    }

    /**
     * Total de escaneos del rango por código y tipo, del que más al que menos.
     *
     * @return Collection<int, array{codigo_qr_id: int, mesa: string|null, nombre: string, tipo: string, escaneos: int}>
     */
    public function totalesPorMesa(Restaurante $restaurante, string $desde, string $hasta): Collection
    {
        return $this->consulta($restaurante, $desde, $hasta)
            ->groupBy('escaneos_diarios.codigo_qr_id', 'codigos_qr.mesa', 'codigos_qr.nombre', 'escaneos_diarios.tipo')
            ->selectRaw('escaneos_diarios.codigo_qr_id, codigos_qr.mesa, codigos_qr.nombre, escaneos_diarios.tipo, SUM(escaneos_diarios.conteo) as escaneos')
            ->orderByDesc('escaneos')
            ->orderBy('escaneos_diarios.codigo_qr_id')
            ->orderBy('escaneos_diarios.tipo')
            ->get()
            ->map(fn (object $fila): array => [
                'codigo_qr_id' => (int) $fila->codigo_qr_id,
                'mesa' => $fila->mesa,
                'nombre' => (string) $fila->nombre,
                'tipo' => (string) $fila->tipo,
                'escaneos' => (int) $fila->escaneos,
            ]);
    }

    /**
     * Consulta base del restaurante y rango. Pasa por el scope global (toBase lo aplica) y filtra por restaurante además.
     */
    private function consulta(Restaurante $restaurante, string $desde, string $hasta): Builder
    {
        $this->validarRango($desde, $hasta);
        $this->exigirElMismoRestaurante($restaurante);

        return EscaneoDiario::query()
            ->join('codigos_qr', 'codigos_qr.id', '=', 'escaneos_diarios.codigo_qr_id')
            ->where('escaneos_diarios.restaurante_id', $restaurante->getKey())
            ->whereBetween('escaneos_diarios.fecha', [$desde, $hasta])
            ->toBase();
    }

    private function exigirElMismoRestaurante(Restaurante $restaurante): void
    {
        $activo = RestauranteScope::resolveRestauranteId();

        if ($activo !== null && $activo !== (int) $restaurante->getKey()) {
            throw new AuthorizationException('Las métricas de otro restaurante no están disponibles en este contexto.');
        }
    }

    private function validarRango(string $desde, string $hasta): void
    {
        foreach ([$desde, $hasta] as $fecha) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) !== 1) {
                throw new InvalidArgumentException("Fecha inválida: {$fecha}. Usa el formato Y-m-d.");
            }
        }

        if ($desde > $hasta) {
            throw new InvalidArgumentException('La fecha inicial no puede ser posterior a la final.');
        }
    }
}
