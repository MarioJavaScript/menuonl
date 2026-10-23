<?php

namespace App\Filament\Resources\Productos\Pages;

use App\Filament\Resources\Productos\ProductoResource;
use App\Models\Producto;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
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
            $this->accionPlatillosAgotados(),
            CreateAction::make(),
        ];
    }

    /**
     * "Mostrar platillos agotados" vive junto a la lista de platillos porque cambia lo que se ve en el menú. Solo quien
     * puede cambiar los datos del restaurante (la política de Restaurante no cambia) la ve.
     */
    private function accionPlatillosAgotados(): Action
    {
        return Action::make('platillos_agotados')
            ->label(__('panel.menu.agotados.accion'))
            ->icon(Heroicon::OutlinedEyeSlash)
            ->color('gray')
            ->visible(fn (): bool => Filament::getTenant() !== null && (auth()->user()?->can('update', Filament::getTenant()) ?? false))
            ->modalHeading(__('panel.menu.agotados.titulo'))
            ->modalSubmitActionLabel(__('panel.menu.agotados.boton'))
            ->fillForm(fn (): array => ['mostrar_agotados' => (bool) Filament::getTenant()->mostrar_agotados])
            ->schema([
                Toggle::make('mostrar_agotados')
                    ->label(__('panel.menu.agotados.campo'))
                    ->helperText(__('panel.menu.agotados.ayuda')),
            ])
            ->action(function (array $data): void {
                $restaurante = Filament::getTenant();

                Gate::authorize('update', $restaurante);

                $restaurante->update(['mostrar_agotados' => (bool) $data['mostrar_agotados']]);

                Notification::make()->success()->title(__('panel.menu.agotados.guardado'))->send();
            });
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
