<?php

namespace Tests\Feature;

use App\Filament\Pages\Apariencia;
use App\Filament\Pages\Inicio;
use App\Filament\Pages\PedidosYReservas;
use App\Filament\Pages\Recomendados;
use App\Filament\Pages\Tenancy\EditRestauranteProfile;
use App\Filament\Resources\Categorias\CategoriaResource;
use App\Filament\Resources\CodigosQr\CodigoQrResource;
use App\Filament\Resources\Contactos\ContactoResource;
use App\Filament\Resources\Horarios\HorarioResource;
use App\Filament\Resources\Productos\ProductoResource;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ArmaEscenarioDePanel;
use Tests\TestCase;

/**
 * El propietario (no el superadmin) administra todo su restaurante desde su propio panel, y cada rol sigue viendo
 * solo lo que veía antes.
 */
class PanelNavegacionDuenoTest extends TestCase
{
    use ArmaEscenarioDePanel, RefreshDatabase;

    public function test_el_propietario_abre_todas_las_pantallas_de_su_restaurante(): void
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);

        $pantallas = [
            Inicio::getUrl(),
            ProductoResource::getUrl('index'),
            CategoriaResource::getUrl('index'),
            Recomendados::getUrl(),
            Apariencia::getUrl(),
            EditRestauranteProfile::getUrl(),
            HorarioResource::getUrl('index'),
            ContactoResource::getUrl('index'),
            PedidosYReservas::getUrl(),
            CodigoQrResource::getUrl('index'),
        ];

        foreach ($pantallas as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_el_propietario_ve_los_grupos_en_orden_y_sin_plataforma(): void
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);

        $grupos = collect(Filament::getNavigation())->map(fn ($grupo) => $grupo->getLabel())->filter()->values()->all();

        $this->assertSame(['Mi menú', 'Apariencia', 'Información', 'Pedidos y reservas', 'QR y mesas', 'Administración'], $grupos);
        $this->assertNotContains('Plataforma', $grupos);
        $this->assertNotContains('Restaurante', $grupos);
    }

    public function test_cada_pantalla_esta_en_el_grupo_que_le_corresponde(): void
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);

        $porGrupo = collect(Filament::getNavigation())
            ->mapWithKeys(fn ($grupo) => [(string) $grupo->getLabel() => collect($grupo->getItems())->map(fn ($item): string => (string) $item->getLabel())->all()])
            ->all();

        $this->assertSame(['Platillos', 'Categorías', 'Recomendados'], $porGrupo['Mi menú']);
        $this->assertSame(['Apariencia'], $porGrupo['Apariencia']);
        $this->assertSame(['Datos del restaurante', 'Horario', 'Contactos'], $porGrupo['Información']);
        $this->assertSame(['Pedidos y reservas', 'Pedidos y reservas de hoy'], $porGrupo['Pedidos y reservas']);
        $this->assertSame(['Códigos QR'], $porGrupo['QR y mesas']);
    }

    public function test_los_enlaces_ver_mi_sitio_y_ver_mi_menu_estan_en_todas_las_pantallas(): void
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);

        foreach ([Inicio::getUrl(), Apariencia::getUrl(), Recomendados::getUrl(), PedidosYReservas::getUrl(), ProductoResource::getUrl('index')] as $url) {
            $this->get($url)
                ->assertSee('Ver mi sitio')
                ->assertSee('Ver mi menú')
                ->assertSee('href="'.route('sitio.publico', ['slug' => 'la-esquina']).'"', escape: false)
                ->assertSee('href="'.route('menu.publico', ['slug' => 'la-esquina']).'"', escape: false);
        }
    }

    public function test_un_pendiente_de_whatsapp_lleva_a_pedidos_y_reservas(): void
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        $restaurante->update(['pedidos_activos' => true, 'whatsapp_pedidos' => null]);
        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);

        $this->get(Inicio::getUrl())
            ->assertSee('falta tu WhatsApp')
            ->assertSee(PedidosYReservas::getUrl(), escape: false);
    }

    /**
     * @return array<string, array{0: string, 1: list<string>, 2: list<string>}>
     */
    public static function visibilidadPorRol(): array
    {
        $dueno = ['Platillos', 'Categorías', 'Recomendados', 'Apariencia', 'Datos del restaurante', 'Horario', 'Contactos', 'Pedidos y reservas', 'Códigos QR'];

        return [
            'editor' => ['editor', ['Platillos', 'Categorías', 'Recomendados', 'Horario', 'Contactos', 'Códigos QR'], ['Apariencia', 'Datos del restaurante', 'Pedidos y reservas']],
            'gerente' => ['gerente', ['Platillos', 'Recomendados'], array_values(array_diff($dueno, ['Platillos', 'Recomendados']))],
            'recepcion' => ['recepcion', [], $dueno],
            'cocina' => ['cocina', [], $dueno],
        ];
    }

    /**
     * @param  list<string>  $debenVerse
     * @param  list<string>  $noDebenVerse
     */
    #[DataProvider('visibilidadPorRol')]
    public function test_cada_rol_ve_solo_lo_que_veia_antes(string $rol, array $debenVerse, array $noDebenVerse): void
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        $this->entrarAlPanel($this->crearUsuario($rol, $restaurante), $restaurante);

        $etiquetas = collect(Filament::getNavigation())->flatMap(fn ($grupo) => $grupo->getItems())->map(fn ($item): string => (string) $item->getLabel())->all();

        foreach ($debenVerse as $etiqueta) {
            $this->assertContains($etiqueta, $etiquetas, "{$rol} debe ver {$etiqueta}");
        }

        foreach ($noDebenVerse as $etiqueta) {
            $this->assertNotContains($etiqueta, $etiquetas, "{$rol} no debe ver {$etiqueta}");
        }
    }
}
