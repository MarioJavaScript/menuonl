<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Ejecuta las pruebas de JavaScript del pedido por WhatsApp de la plantilla E (tests/Js, con el ejecutor de pruebas de Node,
 * sin dependencias nuevas). Si Node no está instalado, la prueba se omite.
 */
class PedidoWhatsappJsTest extends TestCase
{
    public function test_las_reglas_del_mensaje_y_la_limpieza_de_notas_pasan_en_node(): void
    {
        $node = trim((string) (PHP_OS_FAMILY === 'Windows' ? shell_exec('where node 2>NUL') : shell_exec('command -v node 2>/dev/null')));

        if ($node === '') {
            $this->markTestSkipped('Node no está instalado.');
        }

        $raiz = dirname(__DIR__, 2);
        $salida = [];
        $codigo = 0;
        exec('cd '.escapeshellarg($raiz).' && node --test tests/Js/ 2>&1', $salida, $codigo);

        $this->assertSame(0, $codigo, implode("\n", array_slice($salida, -40)));
    }
}
