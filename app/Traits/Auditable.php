<?php

namespace App\Traits;

use App\Models\HistorialCambio;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Restaurante;
use App\Models\Scopes\RestauranteScope;
use App\Services\MenuCacheService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

trait Auditable
{
    /**
     * Pending audit diff captured during updating.
     *
     * @var array<string, array{antes: mixed, despues: mixed}>|null
     */
    public ?array $pendingAuditDiff = null;

    /**
     * Pending delete diff captured during deleting.
     *
     * @var array<string, array{antes: mixed, despues: mixed}>|null
     */
    public ?array $pendingDeleteDiff = null;

    /**
     * Determine the restaurant ID associated with this model.
     */
    public function getAuditRestauranteId(): ?int
    {
        if ($this instanceof Restaurante) {
            return $this->id;
        }

        if (isset($this->restaurante_id) && $this->restaurante_id !== null) {
            return (int) $this->restaurante_id;
        }

        if ($this instanceof ProductoVariante) {
            if ($this->relationLoaded('producto') && $this->producto) {
                return (int) $this->producto->restaurante_id;
            }

            if ($this->producto_id) {
                $restId = Producto::withoutGlobalScopes()->where('id', $this->producto_id)->value('restaurante_id');
                if ($restId) {
                    return (int) $restId;
                }
            }
        }

        $contextId = RestauranteScope::getRestauranteId();
        if ($contextId !== null) {
            return (int) $contextId;
        }

        return null;
    }

    /**
     * Get entity name for auditing (e.g. "categoria", "producto", "restaurante").
     */
    public function getAuditEntityName(): string
    {
        return Str::snake(class_basename($this));
    }

    /**
     * Build the audit diff for entity creation.
     *
     * @return array<string, array{antes: null, despues: mixed}>
     */
    public function buildCreatedDiff(): array
    {
        $diff = [];
        $ignored = ['id', 'created_at', 'updated_at', 'deleted_at', 'remember_token', 'password', 'pendingAuditDiff', 'pendingDeleteDiff'];

        foreach ($this->getAttributes() as $key => $value) {
            if (! in_array($key, $ignored, true)) {
                $diff[$key] = [
                    'antes' => null,
                    'despues' => $value,
                ];
            }
        }

        return $diff;
    }

    /**
     * Build the audit diff for entity update.
     *
     * @return array<string, array{antes: mixed, despues: mixed}>
     */
    public function buildUpdatedDiff(): array
    {
        $diff = [];
        $ignored = ['id', 'created_at', 'updated_at', 'deleted_at', 'remember_token', 'password', 'pendingAuditDiff', 'pendingDeleteDiff'];
        $dirty = $this->getDirty();

        foreach ($dirty as $key => $newValue) {
            if (in_array($key, $ignored, true)) {
                continue;
            }

            $oldValue = $this->getOriginal($key);
            if ($oldValue !== $newValue) {
                $diff[$key] = [
                    'antes' => $oldValue,
                    'despues' => $newValue,
                ];
            }
        }

        return $diff;
    }

    /**
     * Build the audit diff for entity deletion.
     *
     * @return array<string, array{antes: mixed, despues: null}>
     */
    public function buildDeletedDiff(): array
    {
        $diff = [];
        $ignored = ['id', 'created_at', 'updated_at', 'deleted_at', 'remember_token', 'password', 'pendingAuditDiff', 'pendingDeleteDiff'];
        $attributes = $this->getOriginal();
        if (empty($attributes)) {
            $attributes = $this->getAttributes();
        }

        foreach ($attributes as $key => $value) {
            if (! in_array($key, $ignored, true)) {
                $diff[$key] = [
                    'antes' => $value,
                    'despues' => null,
                ];
            }
        }

        return $diff;
    }

    /**
     * Record an audit log entry in historial_cambios.
     */
    public function recordAuditLog(string $accion, array $diff): ?HistorialCambio
    {
        $restauranteId = $this->getAuditRestauranteId();
        if (! $restauranteId) {
            return null;
        }

        return HistorialCambio::withoutGlobalScopes()->create([
            'restaurante_id' => $restauranteId,
            'usuario_id' => Auth::id(),
            'entidad' => $this->getAuditEntityName(),
            'entidad_id' => (int) $this->getKey(),
            'accion' => $accion,
            'cambios' => $diff,
        ]);
    }

    /**
     * Invalidate L1 and L2 cache for the associated restaurant.
     */
    public function triggerMenuCachePurge(): void
    {
        $restauranteId = $this->getAuditRestauranteId();
        if ($restauranteId) {
            app(MenuCacheService::class)->invalidateRestaurante($restauranteId);
        }
    }
}
