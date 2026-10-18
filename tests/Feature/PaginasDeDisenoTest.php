<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ArmaEscenarioDePanel;
use Tests\TestCase;

/**
 * The design system samples (/diseno and the panel "Componentes" page) only exist in the local environment.
 */
class PaginasDeDisenoTest extends TestCase
{
    use ArmaEscenarioDePanel, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpEscenarioDePanel();
    }

    public function test_la_muestra_publica_no_existe_fuera_del_entorno_local(): void
    {
        $this->get('/diseno')->assertNotFound();
    }

    public function test_la_muestra_del_panel_no_se_abre_fuera_del_entorno_local(): void
    {
        $restaurante = $this->crearRestaurante('a');
        $propietario = $this->crearUsuario('propietario', $restaurante);

        $this->actingAs($propietario)->get('/admin/a/componentes')->assertForbidden();
    }
}
