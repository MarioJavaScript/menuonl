<?php

namespace App\Filament\Resources\Categorias\Pages;

use App\Filament\Resources\Categorias\CategoriaResource;
use App\Models\Categoria;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Livewire\Attributes\On;

class ListCategorias extends ListRecords
{
    protected static string $resource = CategoriaResource::class;

    public function getSubheading(): string
    {
        return 'Organiza las secciones de tu menú y el orden en que las ven tus comensales.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * "Deshacer" in the notification shown after deleting a category: the same restore the "Recuperar" action does,
     * with the same policy check. Soft-deleted categories of other restaurants are out of reach (tenant scope).
     */
    #[On('deshacer-eliminar-categoria')]
    public function deshacerEliminarCategoria(int $id): void
    {
        $categoria = Categoria::onlyTrashed()->find($id);

        if (! $categoria instanceof Categoria) {
            return;
        }

        $this->authorize('restore', $categoria);

        $categoria->restore();

        Notification::make()
            ->success()
            ->title(($categoria->traduccion()?->nombre ?? 'La categoría').' volvió a tu menú')
            ->send();
    }
}
