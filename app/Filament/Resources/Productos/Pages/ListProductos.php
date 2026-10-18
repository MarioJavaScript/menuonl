<?php

namespace App\Filament\Resources\Productos\Pages;

use App\Filament\Resources\Productos\ProductoResource;
use App\Models\Producto;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Livewire\Attributes\On;

class ListProductos extends ListRecords
{
    protected static string $resource = ProductoResource::class;

    public function getSubheading(): string
    {
        return 'Cambia precios y marca lo que se agotó, sin entrar a cada uno.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * "Deshacer" in the notification shown after deleting a dish: the same restore the "Recuperar" action does,
     * with the same policy check. Soft-deleted dishes of other restaurants are out of reach (tenant scope).
     */
    #[On('deshacer-eliminar-platillo')]
    public function deshacerEliminarPlatillo(int $id): void
    {
        $producto = Producto::onlyTrashed()->find($id);

        if (! $producto instanceof Producto) {
            return;
        }

        $this->authorize('restore', $producto);

        $producto->restore();

        Notification::make()
            ->success()
            ->title(($producto->traduccion()?->nombre ?? 'El platillo').' volvió a tu menú')
            ->send();
    }
}
