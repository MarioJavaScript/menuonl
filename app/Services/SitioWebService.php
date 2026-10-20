<?php

namespace App\Services;

use App\Models\Restaurante;
use App\Models\Scopes\RestauranteScope;
use App\Models\SitioConfiguracion;
use App\Models\SitioGaleria;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lee y guarda el contenido editable del sitio de UN restaurante para los formularios del panel
 * (propietario y superadmin). El restaurante se pasa siempre de forma explícita: el servicio nunca
 * deduce el destino del tenant activo, así el superadmin puede editar uno distinto del seleccionado.
 */
class SitioWebService
{
    private const array CAMPOS_DE_TEXTO = [
        'hero_imagen',
        'hero_imagen_creditos',
        'hero_subtitulo',
        'historia_etiqueta',
        'historia_titulo',
        'historia_parrafo_1',
        'historia_parrafo_2',
        'historia_imagen',
        'historia_imagen_creditos',
    ];

    /**
     * Estado inicial del formulario.
     *
     * @return array<string, mixed>
     */
    public function datosParaFormulario(Restaurante $restaurante): array
    {
        $config = $this->configuracion($restaurante);

        $datos = [];
        foreach (self::CAMPOS_DE_TEXTO as $campo) {
            $datos[$campo] = $config?->{$campo};
        }

        $datos['estadisticas'] = array_values($config?->estadisticas ?? []);
        $datos['reservas_activas'] = $config?->reservas_activas ?? true;
        $datos['galeria'] = $this->fotos($restaurante)
            ->map(fn (SitioGaleria $foto): array => [
                'id' => $foto->id,
                'imagen' => $foto->imagen,
                'titulo' => $foto->titulo,
                'texto_alternativo' => $foto->texto_alternativo,
                'creditos' => $foto->creditos,
                'visible' => $foto->visible,
            ])
            ->all();

        return $datos;
    }

    /**
     * Rutas de imagen ya guardadas del restaurante: las únicas que el formulario puede reenviar como texto
     * (cualquier otra cadena es una ruta manipulada).
     *
     * @return list<string>
     */
    public function rutasGuardadas(Restaurante $restaurante): array
    {
        $config = $this->configuracion($restaurante);

        return array_values(array_filter([
            $config?->hero_imagen,
            $config?->historia_imagen,
            ...$this->fotos($restaurante)->pluck('imagen')->all(),
        ], fn (?string $ruta): bool => filled($ruta)));
    }

    /**
     * Guarda la configuración y sincroniza la galería (altas, cambios, bajas y orden) de una sola vez.
     *
     * @param  array<string, mixed>  $datos  Estado ya validado del formulario.
     */
    public function guardar(Restaurante $restaurante, array $datos): void
    {
        $contextoPrevio = RestauranteScope::getRestauranteId();
        RestauranteScope::setRestauranteId($restaurante->id);

        try {
            DB::transaction(function () use ($restaurante, $datos): void {
                $this->guardarConfiguracion($restaurante, $datos);
                $this->sincronizarGaleria($restaurante, array_values($datos['galeria'] ?? []));
            });
        } finally {
            RestauranteScope::setRestauranteId($contextoPrevio);
        }
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function guardarConfiguracion(Restaurante $restaurante, array $datos): void
    {
        $config = SitioConfiguracion::withoutGlobalScopes()->firstOrNew(['restaurante_id' => $restaurante->id]);

        foreach (self::CAMPOS_DE_TEXTO as $campo) {
            $config->{$campo} = $this->textoONulo($datos[$campo] ?? null);
        }

        $config->estadisticas = collect($datos['estadisticas'] ?? [])
            ->map(fn (array $dato): array => ['etiqueta' => trim((string) ($dato['etiqueta'] ?? '')), 'valor' => trim((string) ($dato['valor'] ?? ''))])
            ->filter(fn (array $dato): bool => $dato['etiqueta'] !== '' && $dato['valor'] !== '')
            ->take(SitioConfiguracion::MAX_ESTADISTICAS)
            ->values()
            ->all();
        $config->reservas_activas = (bool) ($datos['reservas_activas'] ?? true);

        $config->save();
    }

    /**
     * @param  list<array<string, mixed>>  $filas  En el orden elegido por el usuario.
     */
    private function sincronizarGaleria(Restaurante $restaurante, array $filas): void
    {
        $existentes = SitioGaleria::withoutGlobalScopes()
            ->where('restaurante_id', $restaurante->id)
            ->get()
            ->keyBy('id');

        // Primero las bajas: así quitar una foto y añadir otra no choca con el máximo de la galería.
        $idsEnviados = collect($filas)->pluck('id')->filter()->map(fn ($id): int => (int) $id)->all();

        foreach ($existentes->except($idsEnviados) as $foto) {
            $ruta = $foto->imagen;
            $foto->delete();
            app(WebpImageService::class)->deleteFromRestaurante($ruta, $restaurante->id);
        }

        foreach ($filas as $orden => $fila) {
            $foto = isset($fila['id']) ? $existentes->get((int) $fila['id']) : null;
            $foto ??= new SitioGaleria(['restaurante_id' => $restaurante->id]);

            $foto->fill([
                'imagen' => $fila['imagen'] ?? null,
                'titulo' => trim((string) ($fila['titulo'] ?? '')),
                'texto_alternativo' => trim((string) ($fila['texto_alternativo'] ?? '')),
                'creditos' => $this->textoONulo($fila['creditos'] ?? null),
                'visible' => (bool) ($fila['visible'] ?? true),
                'orden' => $orden,
            ])->save();
        }
    }

    private function textoONulo(mixed $valor): ?string
    {
        $texto = is_string($valor) ? trim($valor) : null;

        return $texto === '' ? null : $texto;
    }

    private function configuracion(Restaurante $restaurante): ?SitioConfiguracion
    {
        return SitioConfiguracion::withoutGlobalScopes()->where('restaurante_id', $restaurante->id)->first();
    }

    /**
     * @return Collection<int, SitioGaleria>
     */
    private function fotos(Restaurante $restaurante): Collection
    {
        return SitioGaleria::withoutGlobalScopes()
            ->where('restaurante_id', $restaurante->id)
            ->ordenado()
            ->get();
    }
}
