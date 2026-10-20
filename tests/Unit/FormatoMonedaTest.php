<?php

namespace Tests\Unit;

use App\Services\FormatoMoneda;
use PHPUnit\Framework\TestCase;

class FormatoMonedaTest extends TestCase
{
    public function test_formato_moneda_resuelve_locale_adecuado_por_pais(): void
    {
        $this->assertSame('es-MX', FormatoMoneda::locale('es', 'MXN'));
        $this->assertSame('es-CO', FormatoMoneda::locale('es', 'COP'));
        $this->assertSame('es-AR', FormatoMoneda::locale('es', 'ARS'));
        $this->assertSame('en-US', FormatoMoneda::locale('en', 'USD'));
        $this->assertSame('es-MX', FormatoMoneda::locale('es_MX', 'MXN'));
        $this->assertSame('es-419', FormatoMoneda::locale('es-419', 'MXN'));
    }

    public function test_formato_moneda_en_pesos_mexicanos_omite_sufijo_mxn(): void
    {
        $this->assertSame('$45.00', FormatoMoneda::formatear(45, 'MXN'));
        $this->assertSame('$1,234.50', FormatoMoneda::formatear(1234.50, 'MXN'));
        $this->assertStringNotContainsString('MXN', FormatoMoneda::formatear(45, 'MXN'));
    }

    public function test_formato_moneda_con_simbolo_dolar_ambiguo_anade_codigo(): void
    {
        $resultado = FormatoMoneda::formatear(45, 'USD');
        $this->assertStringContainsString('USD', $resultado);
        $this->assertStringContainsString('45', $resultado);
    }

    public function test_formato_moneda_maneja_nulos_cero_y_cadenas(): void
    {
        $this->assertSame('$0.00', FormatoMoneda::formatear(null, 'MXN'));
        $this->assertSame('$0.00', FormatoMoneda::formatear(0, 'MXN'));
        $this->assertSame('$99.90', FormatoMoneda::formatear('99.90', 'MXN'));
    }
}
