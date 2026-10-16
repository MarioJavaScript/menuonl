<?php

namespace App\Observers;

use App\Models\Restaurante;
use Illuminate\Database\Eloquent\Model;

class AuditableObserver
{
    /**
     * Handle the Model "created" event.
     */
    public function created(Model $model): void
    {
        // Restaurante creation is root tenant setup, not an in-restaurant audit event
        if ($model instanceof Restaurante) {
            return;
        }

        if (method_exists($model, 'recordAuditLog')) {
            $diff = $model->buildCreatedDiff();
            $model->recordAuditLog('crear', $diff);
            $model->triggerMenuCachePurge();
        }
    }

    /**
     * Handle the Model "updating" event.
     */
    public function updating(Model $model): void
    {
        if (method_exists($model, 'buildUpdatedDiff')) {
            $model->pendingAuditDiff = $model->buildUpdatedDiff();
        }
    }

    /**
     * Handle the Model "updated" event.
     */
    public function updated(Model $model): void
    {
        if (method_exists($model, 'recordAuditLog')) {
            $diff = $model->pendingAuditDiff ?? ($model->buildUpdatedDiff() ?: []);
            if (! empty($diff)) {
                $model->recordAuditLog('actualizar', $diff);
                $model->triggerMenuCachePurge();
            }
        }
    }

    /**
     * Handle the Model "deleting" event.
     */
    public function deleting(Model $model): void
    {
        if (method_exists($model, 'buildDeletedDiff')) {
            $model->pendingDeleteDiff = $model->buildDeletedDiff();
        }
    }

    /**
     * Handle the Model "deleted" event.
     */
    public function deleted(Model $model): void
    {
        if (method_exists($model, 'recordAuditLog')) {
            $diff = $model->pendingDeleteDiff ?? $model->buildDeletedDiff();
            $model->recordAuditLog('eliminar', $diff);
            $model->triggerMenuCachePurge();
        }
    }
}
