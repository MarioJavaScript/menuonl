<?php

namespace Tests\Unit;

use App\Services\FormatoFecha;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class FormatoFechaTest extends TestCase
{
    public function test_formatea_horas_en_doce_horas_con_a_m_y_p_m(): void
    {
        $this->assertSame('1:00 p. m.', FormatoFecha::hora('13:00:00'));
        $this->assertSame('10:45 a. m.', FormatoFecha::hora('10:45'));
        $this->assertSame('12:30 a. m.', FormatoFecha::hora('00:30'));
        $this->assertSame('12:00 p. m.', FormatoFecha::hora('12:00'));
        $this->assertSame('', FormatoFecha::hora(null));
    }

    public function test_formatea_un_rango_de_horario(): void
    {
        $this->assertSame('1:00 p. m. – 2:00 a. m.', FormatoFecha::rango('13:00:00', '02:00:00'));
    }

    public function test_formatea_fechas_con_mes_abreviado(): void
    {
        $this->assertSame('18 oct 2026', FormatoFecha::fecha(Carbon::create(2026, 10, 18)));
        $this->assertSame('1 ene 2027', FormatoFecha::fecha(Carbon::create(2027, 1, 1)));
    }

    public function test_formatea_fecha_y_hora_en_la_zona_del_restaurante(): void
    {
        $utc = Carbon::create(2026, 10, 18, 16, 45, 0, 'UTC');

        $this->assertSame('18 oct 2026, 10:45 a. m.', FormatoFecha::fechaHora($utc, 'America/Mexico_City'));
    }
}
