<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\GestionaSitioWeb;
use App\Models\Restaurante;
use App\Models\SitioConfiguracion;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

/**
 * Contenido del sitio del restaurante activo (portada, historia, datos destacados, galería). Solo el propietario
 * y el superadmin: SitioConfiguracionPolicy no da permisos al editor, así que la página ni aparece en su menú.
 */
class SitioWeb extends Page
{
    use GestionaSitioWeb;

    protected static ?string $slug = 'sitio-web';

    protected static ?string $title = 'Sitio web';

    protected static ?string $navigationLabel = 'Sitio web';

    protected static \UnitEnum|string|null $navigationGroup = 'Operación del Local';

    protected static ?int $navigationSort = 40;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    public static function canAccess(): bool
    {
        return Gate::allows('viewAny', SitioConfiguracion::class);
    }

    public function mount(): void
    {
        $this->autorizar();
        $this->cargarFormulario();
    }

    protected function restauranteDelSitio(): Restaurante
    {
        $tenant = Filament::getTenant();

        abort_unless($tenant instanceof Restaurante, 404);

        return $tenant;
    }

    protected function autorizar(): void
    {
        abort_unless(static::canAccess(), 404);
        abort_unless(Gate::allows('update', new SitioConfiguracion(['restaurante_id' => $this->restauranteDelSitio()->getKey()])), 404);
    }
}
