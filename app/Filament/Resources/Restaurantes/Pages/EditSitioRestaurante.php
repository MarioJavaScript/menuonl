<?php

namespace App\Filament\Resources\Restaurantes\Pages;

use App\Filament\Concerns\GestionaSitioWeb;
use App\Filament\Resources\Restaurantes\RestauranteResource;
use App\Models\Restaurante;
use App\Models\SitioConfiguracion;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;

/**
 * Contenido del sitio de cualquier restaurante, para el superadmin (el recurso ya niega el acceso a los demás).
 * Edita el restaurante de la URL, no el tenant seleccionado en el panel.
 */
class EditSitioRestaurante extends Page
{
    use GestionaSitioWeb, InteractsWithRecord;

    protected static string $resource = RestauranteResource::class;

    protected static ?string $title = 'Sitio web';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        $this->autorizar();
        $this->cargarFormulario();
    }

    public function getTitle(): string|Htmlable
    {
        return 'Sitio web: '.$this->record->nombre;
    }

    protected function restauranteDelSitio(): Restaurante
    {
        return $this->record;
    }

    protected function autorizar(): void
    {
        abort_unless(Gate::allows('update', new SitioConfiguracion(['restaurante_id' => $this->record->getKey()])), 403);
    }
}
