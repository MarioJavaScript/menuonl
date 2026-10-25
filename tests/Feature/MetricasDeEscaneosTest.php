<?php

namespace Tests\Feature;

use App\Models\CodigoQr;
use App\Models\EscaneoDiario;
use App\Models\Restaurante;
use App\Models\Scopes\RestauranteScope;
use App\Services\MetricasDeEscaneos;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ArmaEscenarioDePanel;
use Tests\TestCase;

/**
 * Lectura de los conteos (MetricasDeEscaneos) y quién puede verlos (EscaneoDiarioPolicy).
 */
class MetricasDeEscaneosTest extends TestCase
{
    use ArmaEscenarioDePanel, RefreshDatabase;

    private MetricasDeEscaneos $metricas;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpEscenarioDePanel();
        $this->metricas = new MetricasDeEscaneos;
    }

    private function conteo(CodigoQr $codigo, string $fecha, int $conteo, string $tipo = 'qr'): EscaneoDiario
    {
        return EscaneoDiario::factory()->deCodigo($codigo)->enElDia($fecha, $conteo)->create(['tipo' => $tipo]);
    }

    public function test_por_mesa_y_dia_devuelve_los_campos_ordenados_y_el_rango_inclusivo(): void
    {
        $restaurante = Restaurante::factory()->create();
        $mesa = CodigoQr::factory()->deMesa('4')->create(['restaurante_id' => $restaurante->id, 'nombre' => 'Mesa 4']);
        $general = CodigoQr::factory()->create(['restaurante_id' => $restaurante->id, 'mesa' => null, 'nombre' => 'General']);
        $this->conteo($mesa, '2026-10-19', 9);
        $this->conteo($mesa, '2026-10-21', 3);
        $this->conteo($general, '2026-10-20', 2);
        $this->conteo($mesa, '2026-10-20', 5);
        $this->conteo($mesa, '2026-10-22', 7);

        $filas = $this->metricas->porMesaYDia($restaurante, '2026-10-20', '2026-10-21');

        $this->assertSame(
            [['2026-10-20', $mesa->id, 5], ['2026-10-20', $general->id, 2], ['2026-10-21', $mesa->id, 3]],
            $filas->map(fn (array $fila): array => [$fila['fecha'], $fila['codigo_qr_id'], $fila['escaneos']])->all(),
        );
        $primera = $filas->first();
        $this->assertSame(['codigo_qr_id', 'mesa', 'nombre', 'tipo', 'fecha', 'escaneos'], array_keys($primera));
        $this->assertSame('4', $primera['mesa']);
        $this->assertSame('Mesa 4', $primera['nombre']);
        $this->assertSame('qr', $primera['tipo']);
        $this->assertNull($filas->firstWhere('codigo_qr_id', $general->id)['mesa']);
    }

    public function test_totales_por_mesa_suma_el_rango_y_ordena_del_mayor_al_menor(): void
    {
        $restaurante = Restaurante::factory()->create();
        $poco = CodigoQr::factory()->deMesa('1')->create(['restaurante_id' => $restaurante->id]);
        $mucho = CodigoQr::factory()->deMesa('2')->create(['restaurante_id' => $restaurante->id]);
        $this->conteo($poco, '2026-10-20', 2);
        $this->conteo($mucho, '2026-10-20', 4);
        $this->conteo($mucho, '2026-10-21', 6);
        $this->conteo($mucho, '2026-10-21', 1, 'nfc');
        $this->conteo($mucho, '2026-10-30', 100);

        $totales = $this->metricas->totalesPorMesa($restaurante, '2026-10-20', '2026-10-21');

        $this->assertSame(
            [[$mucho->id, 'qr', 10], [$poco->id, 'qr', 2], [$mucho->id, 'nfc', 1]],
            $totales->map(fn (array $total): array => [$total['codigo_qr_id'], $total['tipo'], $total['escaneos']])->all(),
        );
    }

    public function test_cada_restaurante_ve_solo_lo_suyo(): void
    {
        $uno = Restaurante::factory()->create();
        $dos = Restaurante::factory()->create();
        $codigoUno = CodigoQr::factory()->deMesa('1')->create(['restaurante_id' => $uno->id]);
        $codigoDos = CodigoQr::factory()->deMesa('1')->create(['restaurante_id' => $dos->id]);
        $this->conteo($codigoUno, '2026-10-20', 3);
        $this->conteo($codigoDos, '2026-10-20', 8);

        $this->assertSame([$codigoUno->id], $this->metricas->porMesaYDia($uno, '2026-10-20', '2026-10-20')->pluck('codigo_qr_id')->all());
        $this->assertSame([8], $this->metricas->totalesPorMesa($dos, '2026-10-20', '2026-10-20')->pluck('escaneos')->all());
    }

    public function test_dentro_del_panel_de_otro_restaurante_la_consulta_se_rechaza(): void
    {
        $uno = Restaurante::factory()->create();
        $dos = Restaurante::factory()->create();
        RestauranteScope::setRestauranteId($uno->id);

        $this->assertSame([], $this->metricas->porMesaYDia($uno, '2026-10-20', '2026-10-20')->all());

        $this->expectException(AuthorizationException::class);
        $this->metricas->porMesaYDia($dos, '2026-10-20', '2026-10-20');
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function rangosInvalidos(): array
    {
        return [
            'formato con barras' => ['2026/10/20', '2026-10-21'],
            'fecha con hora' => ['2026-10-20', '2026-10-21 10:00'],
            'texto' => ['ayer', 'hoy'],
            'rango invertido' => ['2026-10-21', '2026-10-20'],
        ];
    }

    #[DataProvider('rangosInvalidos')]
    public function test_un_rango_invalido_se_rechaza(string $desde, string $hasta): void
    {
        $restaurante = Restaurante::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->metricas->totalesPorMesa($restaurante, $desde, $hasta);
    }

    public function test_la_politica_permite_solo_a_superadmin_y_propietario_con_plan_completo(): void
    {
        $completo = $this->crearRestaurante('con-metricas');
        $sitio = Restaurante::factory()->sitioMenu()->create();
        $codigo = CodigoQr::factory()->deMesa('1')->create(['restaurante_id' => $completo->id]);
        $fila = $this->conteo($codigo, '2026-10-20', 1);

        $propietario = $this->crearUsuario('propietario', $completo);
        $gerente = $this->crearUsuario('gerente', $completo);
        $editor = $this->crearUsuario('editor', $completo);
        $propietarioSitio = $this->crearUsuario('propietario', $sitio);
        $superadmin = $this->crearUsuario('superadmin');

        RestauranteScope::setRestauranteId($completo->id);
        $this->assertTrue($propietario->can('viewAny', EscaneoDiario::class));
        $this->assertTrue($propietario->can('view', $fila));
        $this->assertTrue($superadmin->can('viewAny', EscaneoDiario::class));
        $this->assertFalse($gerente->can('viewAny', EscaneoDiario::class));
        $this->assertFalse($editor->can('viewAny', EscaneoDiario::class));
        $this->assertFalse($propietario->can('create', EscaneoDiario::class));
        $this->assertFalse($propietario->can('update', $fila));
        $this->assertFalse($propietario->can('delete', $fila));

        RestauranteScope::setRestauranteId($sitio->id);
        $this->assertFalse($propietarioSitio->can('viewAny', EscaneoDiario::class), 'El plan Sitio y menú no incluye métricas.');
    }

    public function test_un_propietario_no_ve_los_conteos_de_otro_restaurante(): void
    {
        $uno = $this->crearRestaurante('uno');
        $dos = $this->crearRestaurante('dos');
        $ajeno = $this->conteo(CodigoQr::factory()->deMesa('1')->create(['restaurante_id' => $dos->id]), '2026-10-20', 1);

        $this->assertFalse($this->crearUsuario('propietario', $uno)->can('view', $ajeno));
    }

    public function test_el_modelo_en_contexto_de_panel_solo_devuelve_las_filas_del_restaurante(): void
    {
        $uno = Restaurante::factory()->create();
        $dos = Restaurante::factory()->create();
        $propia = $this->conteo(CodigoQr::factory()->deMesa('1')->create(['restaurante_id' => $uno->id]), '2026-10-20', 1);
        $this->conteo(CodigoQr::factory()->deMesa('1')->create(['restaurante_id' => $dos->id]), '2026-10-20', 1);

        RestauranteScope::setRestauranteId($uno->id);

        $this->assertSame([$propia->id], EscaneoDiario::query()->pluck('id')->all());
    }
}
