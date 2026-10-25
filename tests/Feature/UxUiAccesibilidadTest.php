<?php

namespace Tests\Feature;

use App\Filament\Pages\Inicio;
use App\Filament\Resources\Categorias\CategoriaResource;
use App\Filament\Resources\Categorias\Pages\CreateCategoria;
use App\Filament\Resources\Categorias\Pages\EditCategoria;
use App\Filament\Resources\Categorias\Pages\ListCategorias;
use App\Filament\Resources\CodigosQr\CodigoQrResource;
use App\Filament\Resources\CodigosQr\Pages\ListCodigosQr;
use App\Filament\Resources\Contactos\ContactoResource;
use App\Filament\Resources\Contactos\Pages\ListContactos;
use App\Filament\Resources\HistorialCambioResource;
use App\Filament\Resources\HistorialCambioResource\Pages\ListHistorialCambios;
use App\Filament\Resources\Horarios\HorarioResource;
use App\Filament\Resources\Horarios\Pages\ListHorarios;
use App\Filament\Resources\Invitaciones\InvitacionResource;
use App\Filament\Resources\Invitaciones\Pages\ListInvitaciones;
use App\Filament\Resources\Productos\Pages\CreateProducto;
use App\Filament\Resources\Productos\Pages\EditProducto;
use App\Filament\Resources\Productos\Pages\ListProductos;
use App\Filament\Resources\Productos\ProductoResource;
use App\Filament\Resources\Restaurantes\Pages\ListRestaurantes;
use App\Filament\Resources\Restaurantes\RestauranteResource;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Widgets\ResumenRestauranteWidget;
use App\Models\Categoria;
use App\Models\CategoriaTraduccion;
use App\Models\CodigoQr;
use App\Models\Contacto;
use App\Models\Etiqueta;
use App\Models\Horario;
use App\Models\Invitacion;
use App\Models\Producto;
use App\Models\ProductoTraduccion;
use App\Models\ProductoVariante;
use App\Models\Restaurante;
use App\Models\RestauranteIdioma;
use App\Models\Scopes\RestauranteScope;
use App\Models\VarianteTraduccion;
use App\Services\Catalogos;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Concerns\ArmaEscenarioDePanel;
use Tests\TestCase;

class UxUiAccesibilidadTest extends TestCase
{
    use ArmaEscenarioDePanel, RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        RestauranteScope::setRestauranteId(null);

        parent::tearDown();
    }

    // =========================================================================
    // 1. Portada Pública (GET /) — Identidad MENUONL, Español y Cero Scaffold
    // =========================================================================

    public function test_portada_publica_retorna_200_con_branding_menuonl_y_sin_scaffold_ingles(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertViewIs('welcome');
        $response->assertSee(config('app.name'));
        $response->assertSee('La carta digital que tus comensales recordarán.');
        $response->assertSee('Entrar al panel');
        $response->assertSee('Acceder a la administración');
        $response->assertSee(url('/admin'));

        // Cero anglicismos o residuos del scaffold predeterminado de Laravel
        $response->assertDontSee('Documentation');
        $response->assertDontSee('Laracasts');
        $response->assertDontSee('Deploy now');
        $response->assertDontSee("Let's get started");
        $response->assertDontSee('Log in');
        $response->assertDontSee('Register');

        // Atributo lang="es" en HTML para lectores de pantalla
        $response->assertSee('lang="es"', false);
    }

    public function test_portada_publica_muestra_enlace_a_menu_de_muestra_cuando_existe_restaurante_activo(): void
    {
        $demo = Restaurante::factory()->create([
            'nombre' => 'La Esquina Gourmet',
            'slug' => 'la-esquina',
            'estado' => 'activo',
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('Ver menú de muestra');
        $response->assertSee('Explorar menú de muestra');
        $response->assertSee(url('/'.$demo->slug.'/menu'));
    }

    // =========================================================================
    // 2. Menú Público (GET /{slug}/menu) — WCAG 2.2 AA y Experiencia del Comensal
    // =========================================================================

    public function test_menu_publico_cumple_criterios_wcag_aa_objetivos_tactiles_y_aria(): void
    {
        $restaurante = Restaurante::factory()->create([
            'nombre' => 'Trattoria Bella',
            'slug' => 'trattoria-bella',
            'estado' => 'activo',
            'pedidos_activos' => true,
            'whatsapp_pedidos' => '5215555555555',
            'zona_horaria' => 'America/Mexico_City',
            'moneda' => 'MXN',
            'idioma_default' => 'es',
            'mostrar_agotados' => true,
        ]);
        RestauranteIdioma::factory()->create([
            'restaurante_id' => $restaurante->id,
            'idioma' => 'es',
        ]);

        // Fijar tiempo a Lunes 13:00 en la zona horaria del restaurante
        $ahora = Carbon::parse('2026-10-19 13:00:00', 'America/Mexico_City');
        Carbon::setTestNow($ahora);

        Horario::factory()->create([
            'restaurante_id' => $restaurante->id,
            'dia_semana' => $ahora->dayOfWeek,
            'abre' => '10:00:00',
            'cierra' => '22:00:00',
            'cerrado' => false,
        ]);

        $categoria = Categoria::factory()->create([
            'restaurante_id' => $restaurante->id,
            'orden' => 1,
            'visible' => true,
        ]);
        CategoriaTraduccion::factory()->create([
            'categoria_id' => $categoria->id,
            'idioma' => 'es',
            'nombre' => 'Pastas Artesanales',
        ]);

        // Platillo con precio de oferta
        $prodOferta = Producto::factory()->create([
            'restaurante_id' => $restaurante->id,
            'categoria_id' => $categoria->id,
            'precio' => 180.00,
            'precio_oferta' => 150.00,
            'destacado' => true,
            'disponible' => true,
            'orden' => 1,
        ]);
        ProductoTraduccion::factory()->create([
            'producto_id' => $prodOferta->id,
            'idioma' => 'es',
            'nombre' => 'Fettuccine Alfredo',
            'descripcion' => 'Pasta fresca con salsa cremosa de parmesano',
        ]);

        $etiqueta = Etiqueta::factory()->create([
            'codigo' => 'vegetariano',
        ]);
        $prodOferta->etiquetas()->attach($etiqueta);

        // Platillo con variantes
        $prodVariantes = Producto::factory()->create([
            'restaurante_id' => $restaurante->id,
            'categoria_id' => $categoria->id,
            'precio' => 200.00,
            'destacado' => false,
            'disponible' => true,
            'orden' => 2,
        ]);
        ProductoTraduccion::factory()->create([
            'producto_id' => $prodVariantes->id,
            'idioma' => 'es',
            'nombre' => 'Pizza Margherita',
        ]);
        $var1 = ProductoVariante::factory()->create([
            'producto_id' => $prodVariantes->id,
            'precio' => 190.00,
            'disponible' => true,
            'orden' => 1,
        ]);
        VarianteTraduccion::factory()->create([
            'variante_id' => $var1->id,
            'idioma' => 'es',
            'nombre' => 'Individual (25cm)',
        ]);

        // Platillo agotado
        $prodAgotado = Producto::factory()->create([
            'restaurante_id' => $restaurante->id,
            'categoria_id' => $categoria->id,
            'precio' => 90.00,
            'destacado' => false,
            'disponible' => false,
            'orden' => 3,
        ]);
        ProductoTraduccion::factory()->create([
            'producto_id' => $prodAgotado->id,
            'idioma' => 'es',
            'nombre' => 'Tiramisú Casero',
        ]);

        $response = $this->get('/trattoria-bella/menu?mesa=7');

        $response->assertStatus(200);
        $response->assertViewIs('menu.publico');

        // WCAG 2.2 AA — Objetivos táctiles mínimos de 44x44px
        $response->assertSee('min-w-[44px]');
        $response->assertSee('min-h-[44px]');

        // Barra flotante de carrito accesible con rol de diálogo
        $response->assertSee('id="floatingCartBar"', false);
        $response->assertSee('aria-haspopup="dialog"', false);
        $response->assertSee('aria-controls="cartModalBackdrop"', false);

        // Cajón modal de carrito con role="dialog" y aria-modal="true"
        $response->assertSee('id="cartModalBackdrop"', false);
        $response->assertSee('role="dialog"', false);
        $response->assertSee('aria-modal="true"', false);

        // Lectores de pantalla: textos descriptivos en precios de oferta vs regular
        $response->assertSee('<span class="sr-only">Precio original:</span>', false);
        $response->assertSee('<span class="sr-only">Precio de oferta:</span>', false);

        // Manejador global de tecla Escape para cerrar cajón modal (vive en la presentación del cajón, no en un script en línea)
        $this->assertStringContainsString("e.key === 'Escape' || e.key === 'Esc'", (string) file_get_contents(resource_path('js/pedido/presentacion-hoja.js')));

        // Notificación Toast accesible con role="status" y aria-live="polite"
        $response->assertSee('id="cartToast"', false);
        $response->assertSee('role="status"', false);
        $response->assertSee('aria-live="polite"', false);

        // Banner de confirmación en línea para vaciar pedido
        $response->assertSee('id="vaciarConfirmBanner"', false);
        $response->assertSee('¿Quitar todo de tu pedido?');
        $response->assertSee('Vaciar pedido');
        $response->assertSee('Conservar');

        // Textos del modal de pedido y botón de WhatsApp
        $response->assertSee('Mandar pedido por WhatsApp');
        $response->assertDontSee('Enviar Pedido por WhatsApp');
        $response->assertSee('Tu pedido está vacío. Toca "Agregar" en un platillo.', false);
        $response->assertDontSee('Tu carrito está vacío.');

        // Transparencia de estado: abierto ahora y mesa activa
        $response->assertSee('Abierto ahora');
        $response->assertSee('Mesa 7');

        // Etiquetas con semántica de lista accesible
        $response->assertSee('<ul class="flex flex-wrap gap-1 mt-2.5 list-none" aria-label="Etiquetas de', false);
        $response->assertSee('<li class="tag', false);

        // Insignia accesible para platillo recomendado
        $response->assertSee('Recomendado');
        $response->assertSee('class="chip-recomendado"', false);

        // Platillo agotado con estado accesible deshabilitado
        $response->assertSee('Agotado');
        $response->assertSee('aria-disabled="true"', false);
    }

    public function test_menu_publico_cuando_restaurante_esta_cerrado_muestra_banner_explicativo_y_oculta_carrito(): void
    {
        $restaurante = Restaurante::factory()->create([
            'nombre' => 'Cantina El Rincón',
            'slug' => 'cantina-el-rincon',
            'estado' => 'activo',
            'pedidos_activos' => true,
            'whatsapp_pedidos' => '5215555555555',
            'zona_horaria' => 'America/Mexico_City',
        ]);
        RestauranteIdioma::factory()->create([
            'restaurante_id' => $restaurante->id,
            'idioma' => 'es',
        ]);

        // Domingo a medianoche: sin horario configurado (cerrado)
        $domingo = Carbon::parse('2026-10-18 23:59:00', 'America/Mexico_City');
        Carbon::setTestNow($domingo);

        $response = $this->get('/'.$restaurante->slug.'/menu');

        $response->assertStatus(200);
        $response->assertSee('Cerrado');
        $response->assertSee('Estamos cerrados.');
        $response->assertDontSee('id="floatingCartBar"', false);
    }

    public function test_menu_publico_selector_de_idiomas_cumple_criterios_de_accesibilidad_y_tactiles(): void
    {
        $restaurante = Restaurante::factory()->create([
            'slug' => 'bistro-poliglota',
            'estado' => 'activo',
            'idioma_default' => 'es',
        ]);
        RestauranteIdioma::factory()->create([
            'restaurante_id' => $restaurante->id,
            'idioma' => 'es',
        ]);
        RestauranteIdioma::factory()->create([
            'restaurante_id' => $restaurante->id,
            'idioma' => 'en',
        ]);

        $response = $this->get('/'.$restaurante->slug.'/menu');

        $response->assertStatus(200);
        $response->assertSee('aria-label="Selector de idioma"', false);
        $response->assertSee('aria-label="Ver el menú en English"', false);
        $response->assertSee('min-h-[44px]');
        $response->assertSee('min-w-[44px]');
    }

    public function test_menu_publico_escapa_caracteres_especiales_para_prevenir_xss(): void
    {
        $restaurante = Restaurante::factory()->create([
            'slug' => 'xss-safe-grill',
            'estado' => 'activo',
        ]);
        RestauranteIdioma::factory()->create([
            'restaurante_id' => $restaurante->id,
            'idioma' => 'es',
        ]);

        $categoria = Categoria::factory()->create([
            'restaurante_id' => $restaurante->id,
            'visible' => true,
        ]);
        CategoriaTraduccion::factory()->create([
            'categoria_id' => $categoria->id,
            'idioma' => 'es',
            'nombre' => 'Entradas Seguras',
        ]);

        $xssPayload = "<script>alert('xss-test')</script>";
        $producto = Producto::factory()->create([
            'restaurante_id' => $restaurante->id,
            'categoria_id' => $categoria->id,
            'precio' => 50.00,
            'disponible' => true,
            'visible' => true,
        ]);
        ProductoTraduccion::factory()->create([
            'producto_id' => $producto->id,
            'idioma' => 'es',
            'nombre' => 'Taco '.$xssPayload,
            'descripcion' => 'Descripción con '.$xssPayload,
        ]);

        $response = $this->get('/'.$restaurante->slug.'/menu');

        $response->assertStatus(200);
        $response->assertDontSee($xssPayload, false);
        $response->assertSee(htmlspecialchars($xssPayload, ENT_QUOTES), false);
    }

    // =========================================================================
    // 3. Menú No Disponible (GET /q/{codigo} y /{slug}/menu inválidos/inactivos)
    // =========================================================================

    public function test_codigo_qr_inexistente_o_inactivo_muestra_pantalla_empatica_sin_error_404_tecnico(): void
    {
        $response = $this->get('/q/codigo-que-no-existe');

        $response->assertStatus(404);
        $response->assertViewIs('menu.no-disponible');

        // Sin jerga técnica ni strings genéricos de error 404
        $response->assertDontSee('Error 404');
        $response->assertDontSee('Not Found');

        // Orientación empática para el comensal
        $response->assertSee('Menú digital en pausa');
        $response->assertSee('Carta no disponible');
        $response->assertSee('carta impresa');
        $response->assertSee('Reintentar lectura');
        $response->assertSee('Ir a la portada de '.config('app.name'));
        $response->assertSee('min-h-[44px]');
        $response->assertDontSee('onclick=', false);
    }

    public function test_restaurante_suspendido_muestra_pantalla_empatica_de_menu_no_disponible(): void
    {
        $restaurante = Restaurante::factory()->create([
            'nombre' => 'Café de la Plaza',
            'slug' => 'cafe-plaza-suspendido',
            'estado' => 'suspendido',
        ]);

        $qr = CodigoQr::factory()->create([
            'restaurante_id' => $restaurante->id,
            'codigo' => 'cafe01',
            'activo' => true,
        ]);

        // Acceso vía QR
        $responseQr = $this->get('/q/'.$qr->codigo);
        $responseQr->assertStatus(404);
        $responseQr->assertViewIs('menu.no-disponible');
        $responseQr->assertDontSee('Error 404');
        $responseQr->assertSee('carta impresa');

        // Acceso directo a menú público
        $responseMenu = $this->get('/'.$restaurante->slug.'/menu');
        $responseMenu->assertStatus(404);
        $responseMenu->assertViewIs('menu.no-disponible');
        $responseMenu->assertDontSee('Error 404');
        $responseMenu->assertSee('Reintentar lectura');
    }

    // =========================================================================
    // 4. Ficha QR Imprimible (qr/imprimible.blade.php) — Dimensiones e Instrucciones
    // =========================================================================

    public function test_ficha_qr_imprimible_muestra_dimensiones_y_pasos_de_escaneo_en_espanol(): void
    {
        $restaurante = $this->crearRestaurante('asador-real');
        $restaurante->update(['nombre' => 'Asador Real']);

        $qr = CodigoQr::factory()->deMesa('4')->create([
            'restaurante_id' => $restaurante->id,
            'codigo' => 'asador04',
            'activo' => true,
        ]);

        $propietario = $this->crearUsuario('propietario', $restaurante);
        $this->entrarAlPanel($propietario, $restaurante);

        $response = $this->get(route('codigos-qr.descargar', ['codigoQr' => $qr, 'formato' => 'pdf']));

        $response->assertStatus(200);
        $response->assertViewIs('qr.imprimible');
        $response->assertSee('Asador Real');
        $response->assertSee('Mesa 4');

        // Dimensiones optimizadas para soporte de mesa de 100mm
        $response->assertSee('100mm');

        // Instrucciones paso a paso en español natural
        $response->assertSee('Abre la cámara de tu teléfono móvil o lector de QR.');
        $response->assertSee('Apunta hacia el código o acerca tu teléfono si cuenta con NFC.');
        $response->assertSee('Explora la carta y haz tu pedido al instante.');
        $response->assertSee('<span class="step-dot">1</span>', false);
        $response->assertSee('<span class="step-dot">2</span>', false);
        $response->assertSee('<span class="step-dot">3</span>', false);

        // Botones de acción claros con soporte de impresión
        $response->assertSee('Imprimir ficha de mesa');
        $response->assertSee('Volver al panel');
        $response->assertSee('Sin necesidad de descargar aplicaciones · '.config('app.name'));
        $response->assertSee('min-height: 44px', false);
        $response->assertDontSee('onclick=', false);
    }

    // =========================================================================
    // 5. Invitaciones UX y Accesibilidad (resources/views/invitaciones/*)
    // =========================================================================

    public function test_invitacion_invalida_muestra_orientacion_clara_y_boton_accesible(): void
    {
        $response = $this->get('/invitaciones/token-invalido-o-expirado');

        $response->assertStatus(404);
        $response->assertViewIs('invitaciones.invalida');
        $response->assertSee('Invitación no disponible');
        $response->assertSee('Este enlace ya no es válido, ha caducado o ya fue utilizado anteriormente');
        $response->assertSee('vigencia máxima de 7 días');
        $response->assertSee('Ir al acceso del panel');
        $response->assertSee('min-h-[44px]');
    }

    public function test_invitacion_valida_muestra_formulario_en_espanol_con_perfil_y_ayuda(): void
    {
        $restaurante = $this->crearRestaurante('fonda-dona-luz');
        $restaurante->update(['nombre' => 'Fonda Doña Luz']);

        $tokenPlano = Str::random(48);
        Invitacion::factory()->create([
            'restaurante_id' => $restaurante->id,
            'email' => 'nuevo.chef@fondaluz.com',
            'rol' => 'editor',
            'token' => hash('sha256', $tokenPlano),
            'expira_en' => now()->addDays(5),
        ]);

        $response = $this->get('/invitaciones/'.$tokenPlano);

        $response->assertStatus(200);
        $response->assertViewIs('invitaciones.aceptar');
        $response->assertSee('Te damos la bienvenida al equipo');
        $response->assertSee('Fonda Doña Luz');
        $response->assertSee('Editor');
        $response->assertSee('Nombre completo');
        $response->assertSee('Crear contraseña');
        $response->assertSee('Confirmar contraseña');
        $response->assertSee('Debe incluir al menos 10 caracteres combinando letras y números.');
        $response->assertSee('border-borde-control', false);
    }

    // =========================================================================
    // 6. Panel Administrativo Filament 5 — Localización, Estados Vacíos y Acceso a Menú
    // =========================================================================

    public function test_panel_configuracion_locale_espanol_por_defecto_y_fallback(): void
    {
        $this->assertSame('es', config('app.locale'), 'El locale predeterminado debe ser "es".');
        $this->assertSame('es', config('app.fallback_locale'), 'El locale de fallback debe ser "es".');
    }

    public function test_filament_recursos_tienen_grupos_de_navegacion_estandarizados_en_espanol(): void
    {
        // Mi menú
        $this->assertSame('Mi menú', CategoriaResource::getNavigationGroup());
        $this->assertSame('Mi menú', ProductoResource::getNavigationGroup());

        // Información
        $this->assertSame('Información', HorarioResource::getNavigationGroup());
        $this->assertSame('Información', ContactoResource::getNavigationGroup());

        // QR y mesas
        $this->assertSame('QR y mesas', CodigoQrResource::getNavigationGroup());

        // Administración
        $this->assertSame('Administración', UserResource::getNavigationGroup());
        $this->assertSame('Administración', InvitacionResource::getNavigationGroup());
        $this->assertSame('Administración', HistorialCambioResource::getNavigationGroup());

        // Plataforma
        $this->assertSame('Plataforma', RestauranteResource::getNavigationGroup());
    }

    public function test_filament_tablas_tienen_encabezados_de_estado_vacio_en_espanol(): void
    {
        $restaurante = $this->crearRestaurante('restaurante-vacio');
        $propietario = $this->crearUsuario('propietario', $restaurante);
        $this->entrarAlPanel($propietario, $restaurante);

        // Tablas con contexto de restaurante
        $this->assertSame('Todavía no tienes categorías', Livewire::test(ListCategorias::class)->instance()->getTable()->getEmptyStateHeading());
        $this->assertSame('Sin códigos QR generados', Livewire::test(ListCodigosQr::class)->instance()->getTable()->getEmptyStateHeading());
        $this->assertSame('Sin horarios de atención configurados', Livewire::test(ListHorarios::class)->instance()->getTable()->getEmptyStateHeading());
        $this->assertSame('No hay canales de contacto registrados', Livewire::test(ListContactos::class)->instance()->getTable()->getEmptyStateHeading());
        $this->assertSame('Todavía no tienes platillos', Livewire::test(ListProductos::class)->instance()->getTable()->getEmptyStateHeading());
        $this->assertSame('No hay invitaciones enviadas', Livewire::test(ListInvitaciones::class)->instance()->getTable()->getEmptyStateHeading());
        $this->assertSame('No hay miembros adicionales en el equipo', Livewire::test(ListUsers::class)->instance()->getTable()->getEmptyStateHeading());
        $this->assertSame('Sin eventos de auditoría', Livewire::test(ListHistorialCambios::class)->instance()->getTable()->getEmptyStateHeading());

        // Tablas no asociadas a tenant (Administración Global / Superadmin)
        $superadmin = $this->crearUsuario('superadmin');
        $this->entrarAlPanel($superadmin, $restaurante);

        $this->assertSame('No hay restaurantes registrados', Livewire::test(ListRestaurantes::class)->instance()->getTable()->getEmptyStateHeading());
    }

    public function test_filament_panel_registra_acceso_directo_a_menu_publico_en_render_hook(): void
    {
        $restaurante = $this->crearRestaurante('los-arcos');
        $propietario = $this->crearUsuario('propietario', $restaurante);
        $this->entrarAlPanel($propietario, $restaurante);

        // Render Hook en cabecera: PanelsRenderHook::USER_MENU_BEFORE
        $renderedHook = FilamentView::renderHook(PanelsRenderHook::USER_MENU_BEFORE);
        $this->assertStringContainsString('Ver mi menú', (string) $renderedHook);
        $this->assertStringContainsString(route('menu.publico', ['slug' => 'los-arcos']), (string) $renderedHook);
        $this->assertStringContainsString('target="_blank"', (string) $renderedHook);
    }

    public function test_panel_navegacion_e_inicio_usan_termino_inicio_y_no_escritorio(): void
    {
        $this->assertSame('Inicio', Inicio::getNavigationLabel());
        $this->assertNotSame('Escritorio', Inicio::getNavigationLabel());
        $this->assertNotSame('Dashboard', Inicio::getNavigationLabel());
        $this->assertSame('Inicio', __('filament-panels::pages/dashboard.title'));

        $restaurante = $this->crearRestaurante('tacos-el-guero');
        // Nombre fijo con apóstrofo, comillas y ampersand: no depende de la fábrica aleatoria ni de su idioma.
        $restaurante->update(['nombre' => "Tacos \"El Güero\" & O'Connell"]);
        $propietario = $this->crearUsuario('propietario', $restaurante);
        $this->entrarAlPanel($propietario, $restaurante);

        $respuesta = $this->get(Inicio::getUrl());
        $respuesta->assertOk();
        $respuesta->assertSee('Inicio');
        $respuesta->assertSee("Lo que necesita tu atención hoy en {$restaurante->nombre}.");
        $respuesta->assertDontSee('Escritorio');
    }

    public function test_tabla_platillos_accion_deshacer_eliminar_restaura_el_registro(): void
    {
        $restaurante = $this->crearRestaurante('rest-deshacer');
        $propietario = $this->crearUsuario('propietario', $restaurante);
        $this->entrarAlPanel($propietario, $restaurante);

        $categoria = Categoria::factory()->create(['restaurante_id' => $restaurante->id]);
        $producto = Producto::factory()->create([
            'restaurante_id' => $restaurante->id,
            'categoria_id' => $categoria->id,
        ]);
        ProductoTraduccion::factory()->create([
            'producto_id' => $producto->id,
            'idioma' => 'es',
            'nombre' => 'Taco al Pastor',
        ]);

        Livewire::test(ListProductos::class)
            ->callAction(TestAction::make('delete')->table($producto))
            ->assertNotified('Taco al Pastor se eliminó')
            ->dispatch('deshacer-eliminar-platillo', id: $producto->id)
            ->assertNotified('Taco al Pastor volvió a tu menú');

        $this->assertNotSoftDeleted('productos', ['id' => $producto->id]);
    }

    public function test_tabla_platillos_deshacer_eliminar_respeta_aislamiento_entre_restaurantes(): void
    {
        $tenantA = $this->crearRestaurante('tenant-undo-a');
        $tenantB = $this->crearRestaurante('tenant-undo-b');

        $prodB = Producto::factory()->create(['restaurante_id' => $tenantB->id]);
        $prodB->delete();

        $propietarioA = $this->crearUsuario('propietario', $tenantA);
        $this->entrarAlPanel($propietarioA, $tenantA);

        Livewire::test(ListProductos::class)
            ->dispatch('deshacer-eliminar-platillo', id: $prodB->id);

        $this->assertSoftDeleted('productos', ['id' => $prodB->id]);
    }

    public function test_tabla_platillos_configura_columnas_responsivas_acciones_y_estados_vacios(): void
    {
        $restaurante = $this->crearRestaurante('rest-tabla-platillos');
        $propietario = $this->crearUsuario('propietario', $restaurante);
        $this->entrarAlPanel($propietario, $restaurante);

        $livewire = Livewire::test(ListProductos::class);
        /** @var ListProductos $page */
        $page = $livewire->instance();
        $this->assertSame('Cambia precios y marca lo que se agotó, sin entrar a cada uno.', $page->getSubheading());

        $table = $page->getTable();
        $this->assertTrue($table->isStackedOnMobile());

        // Columnas y responsividad (sm)
        $colImagen = $table->getColumn('imagen');
        $this->assertNotNull($colImagen);
        $this->assertSame('Foto', $colImagen->getLabel());
        $this->assertSame('sm', $colImagen->getVisibleFrom());

        $colNombre = $table->getColumn('nombre');
        $this->assertNotNull($colNombre);
        $this->assertSame('Platillo', $colNombre->getLabel());
        $this->assertTrue($colNombre->isSearchable());

        $colPrecio = $table->getColumn('precio');
        $this->assertNotNull($colPrecio);
        $this->assertSame('Precio', $colPrecio->getLabel());
        $this->assertSame('$', $colPrecio->getPrefixLabel());

        $colDisponible = $table->getColumn('disponible');
        $this->assertNotNull($colDisponible);
        $this->assertSame('Disponible', $colDisponible->getLabel());

        $colVisible = $table->getColumn('visible');
        $this->assertNotNull($colVisible);
        $this->assertSame('En el menú', $colVisible->getLabel());

        // Estado vacío
        $this->assertSame('Todavía no tienes platillos', $table->getEmptyStateHeading());
        $this->assertSame('Agrega el primero; puedes completar la foto y las opciones después.', $table->getEmptyStateDescription());
        $this->assertSame('heroicon-o-cake', $table->getEmptyStateIcon());

        $emptyActions = $table->getEmptyStateActions();
        $this->assertNotEmpty($emptyActions);
        $crearAction = collect($emptyActions)->first(fn ($a) => $a->getName() === 'crear');
        $this->assertNotNull($crearAction);
        $this->assertSame('Agregar platillo', $crearAction->getLabel());
        $this->assertSame(ProductoResource::getUrl('create'), $crearAction->getUrl());
    }

    public function test_tabla_categorias_accion_deshacer_eliminar_restaura_el_registro(): void
    {
        $restaurante = $this->crearRestaurante('rest-deshacer-cat');
        $propietario = $this->crearUsuario('propietario', $restaurante);
        $this->entrarAlPanel($propietario, $restaurante);

        $categoria = Categoria::factory()->create(['restaurante_id' => $restaurante->id]);
        CategoriaTraduccion::factory()->create([
            'categoria_id' => $categoria->id,
            'idioma' => 'es',
            'nombre' => 'Entradas Calientes',
        ]);
        $platillo = Producto::factory()->create([
            'restaurante_id' => $restaurante->id,
            'categoria_id' => $categoria->id,
        ]);
        ProductoTraduccion::factory()->create([
            'producto_id' => $platillo->id,
            'idioma' => 'es',
            'nombre' => 'Sopa de Tortilla',
        ]);

        Livewire::test(ListCategorias::class)
            ->callAction(TestAction::make('delete')->table($categoria))
            ->assertNotified('Entradas Calientes se eliminó')
            ->dispatch('deshacer-eliminar-categoria', id: $categoria->id)
            ->assertNotified('Entradas Calientes volvió a tu menú');

        $this->assertNotSoftDeleted('categorias', ['id' => $categoria->id]);
        $this->assertNotSoftDeleted('productos', ['id' => $platillo->id]);

        // Id inexistente o ya activo no produce errores ni notificaciones adicionales
        Livewire::test(ListCategorias::class)
            ->dispatch('deshacer-eliminar-categoria', id: 999999)
            ->assertNotNotified();
    }

    public function test_tabla_categorias_deshacer_eliminar_respeta_aislamiento_entre_restaurantes(): void
    {
        $tenantA = $this->crearRestaurante('cat-undo-a');
        $tenantB = $this->crearRestaurante('cat-undo-b');

        $catB = Categoria::factory()->create(['restaurante_id' => $tenantB->id]);
        $catB->delete();

        $propietarioA = $this->crearUsuario('propietario', $tenantA);
        $this->entrarAlPanel($propietarioA, $tenantA);

        Livewire::test(ListCategorias::class)
            ->dispatch('deshacer-eliminar-categoria', id: $catB->id);

        $this->assertSoftDeleted('categorias', ['id' => $catB->id]);
    }

    public function test_tabla_categorias_accion_recuperar_restaura_el_registro(): void
    {
        $restaurante = $this->crearRestaurante('rest-recuperar-cat');
        $propietario = $this->crearUsuario('propietario', $restaurante);
        $this->entrarAlPanel($propietario, $restaurante);

        $categoria = Categoria::factory()->create(['restaurante_id' => $restaurante->id]);
        CategoriaTraduccion::factory()->create([
            'categoria_id' => $categoria->id,
            'idioma' => 'es',
            'nombre' => 'Bebidas Frías',
        ]);
        $categoria->delete();
        $this->assertSoftDeleted('categorias', ['id' => $categoria->id]);

        Livewire::test(ListCategorias::class)
            ->filterTable('trashed', true)
            ->callAction(TestAction::make('restore')->table($categoria))
            ->assertNotified('Bebidas Frías se recuperó');

        $this->assertNotSoftDeleted('categorias', ['id' => $categoria->id]);
    }

    public function test_tabla_categorias_configura_columnas_responsivas_acciones_y_estados_vacios(): void
    {
        $restaurante = $this->crearRestaurante('rest-tabla-categorias');
        $propietario = $this->crearUsuario('propietario', $restaurante);
        $this->entrarAlPanel($propietario, $restaurante);

        $livewire = Livewire::test(ListCategorias::class);
        /** @var ListCategorias $page */
        $page = $livewire->instance();
        $this->assertSame('Organiza las secciones de tu menú y el orden en que las ven tus comensales.', $page->getSubheading());

        $table = $page->getTable();
        $this->assertTrue($table->isStackedOnMobile());

        // Columnas
        $colNombre = $table->getColumn('nombre');
        $this->assertNotNull($colNombre);
        $this->assertSame('Nombre', $colNombre->getLabel());
        $this->assertTrue($colNombre->isSearchable());

        $colPlatillos = $table->getColumn('productos_count');
        $this->assertNotNull($colPlatillos);
        $this->assertSame('Platillos', $colPlatillos->getLabel());

        $colVisible = $table->getColumn('visible');
        $this->assertNotNull($colVisible);
        $this->assertSame('Visible', $colVisible->getLabel());

        // El orden se cambia arrastrando: no hay columna numérica.
        $this->assertNull($table->getColumn('orden'));
        $this->assertSame('orden', $table->getReorderColumn());

        // Estado vacío
        $this->assertSame('Todavía no tienes categorías', $table->getEmptyStateHeading());
        $this->assertSame('Crea la primera para organizar tu menú, por ejemplo: Entradas, Platos fuertes o Bebidas.', $table->getEmptyStateDescription());
        $this->assertSame('heroicon-o-rectangle-stack', $table->getEmptyStateIcon());

        $emptyActions = $table->getEmptyStateActions();
        $this->assertNotEmpty($emptyActions);
        $crearAction = collect($emptyActions)->first(fn ($a) => $a->getName() === 'crear');
        $this->assertNotNull($crearAction);
        $this->assertSame('Agregar categoría', $crearAction->getLabel());
        $this->assertSame(CategoriaResource::getUrl('create'), $crearAction->getUrl());

        // Acciones de registro: ActionGroup "Más acciones" con DeleteAction y RestoreAction
        $recordActions = $table->getRecordActions();
        $this->assertNotEmpty($recordActions);
        $editAction = collect($recordActions)->first(fn ($a) => $a instanceof Action && $a->getName() === 'edit');
        $this->assertNotNull($editAction);

        $grupo = collect($recordActions)->first(fn ($a) => $a instanceof ActionGroup);
        $this->assertNotNull($grupo);
        $this->assertSame('Más acciones', $grupo->getLabel());
        $accionesGrupo = $grupo->getActions();
        $deleteAction = collect($accionesGrupo)->first(fn ($a) => $a->getName() === 'delete');
        $restoreAction = collect($accionesGrupo)->first(fn ($a) => $a->getName() === 'restore');
        $this->assertNotNull($deleteAction);
        $this->assertNotNull($restoreAction);
        $this->assertSame('Eliminar categoría', $deleteAction->getLabel());
        $this->assertSame('Recuperar categoría', $restoreAction->getLabel());
        $this->assertStringContainsString('Los platillos de esta categoría dejarán de mostrarse', (string) $deleteAction->getModalDescription());

        // Acciones masivas en barra de herramientas
        $toolbarActions = $table->getToolbarActions();
        $this->assertNotEmpty($toolbarActions);
        $bulkGroup = collect($toolbarActions)->first(fn ($a) => $a instanceof BulkActionGroup);
        $this->assertNotNull($bulkGroup);
        $this->assertSame('Acciones con las seleccionadas', $bulkGroup->getLabel());
        $bulkActions = $bulkGroup->getActions();
        $deleteBulk = collect($bulkActions)->first(fn ($a) => $a->getName() === 'delete');
        $restoreBulk = collect($bulkActions)->first(fn ($a) => $a->getName() === 'restore');
        $this->assertNotNull($deleteBulk);
        $this->assertNotNull($restoreBulk);
        $this->assertSame('Eliminar seleccionadas', $deleteBulk->getLabel());
        $this->assertSame('Recuperar seleccionadas', $restoreBulk->getLabel());
        $this->assertStringContainsString('Los platillos de estas categorías dejarán de mostrarse', (string) $deleteBulk->getModalDescription());
    }

    public function test_formulario_platillo_configura_prefijos_y_textos_de_ayuda_en_tuteo(): void
    {
        $restaurante = $this->crearRestaurante('rest-form-platillo');
        $propietario = $this->crearUsuario('propietario', $restaurante);
        $this->entrarAlPanel($propietario, $restaurante);

        $livewire = Livewire::test(CreateProducto::class);
        $form = $livewire->instance()->form;

        // Prefijos en campos de precio
        $campoPrecio = $form->getComponent('precio');
        $this->assertNotNull($campoPrecio);
        $this->assertSame('$', $campoPrecio->getPrefixLabel());

        $campoOferta = $form->getComponent('precio_oferta');
        $this->assertNotNull($campoOferta);
        $this->assertSame('$', $campoOferta->getPrefixLabel());

        // Textos de ayuda empáticos y en tuteo renderizados en pantalla
        $livewire->assertSee('Precio regular que pagarán tus comensales.');
        $livewire->assertSee('Si lo indicas, se mostrará como precio vigente junto al precio regular tachado.');
        $livewire->assertSee('Desmárcalo si se te agotó hoy; podrás volver a activarlo en cualquier momento.');
        $livewire->assertSee('Actívalo para sugerirlo al inicio de tu menú como una de tus recomendaciones especiales.');
        $livewire->assertSee('Desmárcalo si quieres guardarlo u ocultarlo temporalmente de tu menú sin borrarlo.');
    }

    public function test_formulario_categoria_configura_textos_de_ayuda_y_orden(): void
    {
        $restaurante = $this->crearRestaurante('rest-form-cat');
        $propietario = $this->crearUsuario('propietario', $restaurante);
        $this->entrarAlPanel($propietario, $restaurante);

        $livewire = Livewire::test(CreateCategoria::class);

        $livewire->assertSee('Apágala para ocultar esta categoría y sus platillos de tu menú sin borrarlos.');
        $livewire->assertDontSee('Orden de aparición');
    }

    public function test_edicion_platillo_y_categoria_configuran_acciones_de_cabecera(): void
    {
        $restaurante = $this->crearRestaurante('rest-edit-actions');
        $propietario = $this->crearUsuario('propietario', $restaurante);
        $this->entrarAlPanel($propietario, $restaurante);

        $categoria = Categoria::factory()->create(['restaurante_id' => $restaurante->id]);
        CategoriaTraduccion::factory()->create(['categoria_id' => $categoria->id, 'idioma' => 'es', 'nombre' => 'Bebidas']);
        $platillo = Producto::factory()->create(['restaurante_id' => $restaurante->id, 'categoria_id' => $categoria->id]);
        ProductoTraduccion::factory()->create(['producto_id' => $platillo->id, 'idioma' => 'es', 'nombre' => 'Limonada']);

        // EditProducto
        $livewireProd = Livewire::test(EditProducto::class, ['record' => $platillo->getRouteKey()]);
        /** @var EditProducto $editProd */
        $editProd = $livewireProd->instance();
        $prodActions = $editProd->getCachedHeaderActions();
        $prodDelete = collect($prodActions)->first(fn ($a) => $a->getName() === 'delete');
        $prodRestore = collect($prodActions)->first(fn ($a) => $a->getName() === 'restore');
        $this->assertNotNull($prodDelete);
        $this->assertNotNull($prodRestore);
        $this->assertSame('Eliminar platillo', $prodDelete->getLabel());
        $this->assertSame('Recuperar platillo', $prodRestore->getLabel());
        $this->assertSame('¿Eliminar "Limonada"?', (string) $prodDelete->getModalHeading());
        $this->assertSame('Dejará de verse en tu menú. Puedes recuperarlo desde "Eliminados".', (string) $prodDelete->getModalDescription());
        $this->assertSame('Eliminar platillo', $prodDelete->getModalSubmitActionLabel());
        $this->assertSame('¿Recuperar "Limonada"?', (string) $prodRestore->getModalHeading());
        $this->assertSame('Volverá a tu menú con el mismo precio y foto.', (string) $prodRestore->getModalDescription());
        $this->assertSame('Recuperar platillo', $prodRestore->getModalSubmitActionLabel());

        // EditCategoria
        $livewireCat = Livewire::test(EditCategoria::class, ['record' => $categoria->getRouteKey()]);
        /** @var EditCategoria $editCat */
        $editCat = $livewireCat->instance();
        $catActions = $editCat->getCachedHeaderActions();
        $catDelete = collect($catActions)->first(fn ($a) => $a->getName() === 'delete');
        $catRestore = collect($catActions)->first(fn ($a) => $a->getName() === 'restore');
        $this->assertNotNull($catDelete);
        $this->assertNotNull($catRestore);
        $this->assertSame('Eliminar categoría', $catDelete->getLabel());
        $this->assertSame('Recuperar categoría', $catRestore->getLabel());
        $this->assertSame('¿Eliminar "Bebidas"?', (string) $catDelete->getModalHeading());
        $this->assertSame('Los platillos dentro de esta categoría dejarán de mostrarse en tu menú. Puedes recuperarla desde "Eliminados".', (string) $catDelete->getModalDescription());
        $this->assertSame('Eliminar categoría', $catDelete->getModalSubmitActionLabel());
        $this->assertSame('¿Recuperar "Bebidas"?', (string) $catRestore->getModalHeading());
        $this->assertSame('Volverá a mostrarse en tu menú junto con sus platillos.', (string) $catRestore->getModalDescription());
        $this->assertSame('Recuperar categoría', $catRestore->getModalSubmitActionLabel());

        // Ejecución de eliminación y restauración desde la cabecera de EditProducto
        Livewire::test(EditProducto::class, ['record' => $platillo->getRouteKey()])
            ->callAction('delete');
        $this->assertSoftDeleted('productos', ['id' => $platillo->id]);

        Livewire::test(EditProducto::class, ['record' => $platillo->getRouteKey()])
            ->callAction('restore');
        $this->assertNotSoftDeleted('productos', ['id' => $platillo->id]);

        // Ejecución de eliminación y restauración desde la cabecera de EditCategoria
        Livewire::test(EditCategoria::class, ['record' => $categoria->getRouteKey()])
            ->callAction('delete');
        $this->assertSoftDeleted('categorias', ['id' => $categoria->id]);

        Livewire::test(EditCategoria::class, ['record' => $categoria->getRouteKey()])
            ->callAction('restore');
        $this->assertNotSoftDeleted('categorias', ['id' => $categoria->id]);
    }

    public function test_formulario_platillo_y_categoria_validaciones_de_precios_y_orden_en_espanol(): void
    {
        $restaurante = $this->crearRestaurante('rest-validaciones-lote2');
        $propietario = $this->crearUsuario('propietario', $restaurante);
        $this->entrarAlPanel($propietario, $restaurante);

        $categoria = Categoria::factory()->create(['restaurante_id' => $restaurante->id]);

        // Validación de precio negativo en platillo
        Livewire::test(CreateProducto::class)
            ->fillForm([
                'categoria_id' => $categoria->id,
                'precio' => -10,
                'orden' => 0,
                'traducciones' => [['idioma' => 'es', 'nombre' => 'Taco']],
            ])
            ->call('create')
            ->assertHasFormErrors(['precio'])
            ->assertSee('El precio no puede ser negativo.');

        // Validación de precio de oferta mayor al regular
        Livewire::test(CreateProducto::class)
            ->fillForm([
                'categoria_id' => $categoria->id,
                'precio' => 50,
                'precio_oferta' => 70,
                'orden' => 0,
                'traducciones' => [['idioma' => 'es', 'nombre' => 'Taco Caro']],
            ])
            ->call('create')
            ->assertHasFormErrors(['precio_oferta'])
            ->assertSee('El precio promocional debe ser menor o igual al precio regular.');

        // Caso límite: precio de oferta igual al precio regular es válido
        Livewire::test(CreateProducto::class)
            ->fillForm([
                'categoria_id' => $categoria->id,
                'precio' => 50,
                'precio_oferta' => 50,
                'orden' => 0,
                'traducciones' => [['idioma' => 'es', 'nombre' => 'Taco Mismo Precio']],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('productos', [
            'precio' => 50,
            'precio_oferta' => 50,
        ]);

        // Validación de precio requerido en platillo
        Livewire::test(CreateProducto::class)
            ->fillForm([
                'categoria_id' => $categoria->id,
                'precio' => null,
                'orden' => 0,
                'traducciones' => [['idioma' => 'es', 'nombre' => 'Taco Sin Precio']],
            ])
            ->call('create')
            ->assertHasFormErrors(['precio'])
            ->assertSee('Escribe un precio para el platillo.');

        // Validación de precio promocional negativo
        Livewire::test(CreateProducto::class)
            ->fillForm([
                'categoria_id' => $categoria->id,
                'precio' => 50,
                'precio_oferta' => -5,
                'orden' => 0,
                'traducciones' => [['idioma' => 'es', 'nombre' => 'Taco Oferta Negativa']],
            ])
            ->call('create')
            ->assertHasFormErrors(['precio_oferta'])
            ->assertSee('El precio promocional no puede ser negativo.');

    }

    public function test_panel_render_hook_barra_inferior_movil_aislamiento_y_estructura(): void
    {
        $restaurante = $this->crearRestaurante('rest-barra-movil');
        $propietario = $this->crearUsuario('propietario', $restaurante);
        $this->entrarAlPanel($propietario, $restaurante);

        $hook = (string) FilamentView::renderHook(PanelsRenderHook::BODY_END);
        $this->assertStringContainsString('class="mo-barra-inferior"', $hook);
        $this->assertStringContainsString('aria-label="Accesos rápidos"', $hook);
        $this->assertStringContainsString('Inicio', $hook);
        $this->assertStringContainsString('Platillos', $hook);
        $this->assertStringContainsString('Categorías', $hook);
        $this->assertStringContainsString('Horario', $hook);
        $this->assertStringContainsString('Más', $hook);
        $this->assertStringContainsString('aria-label="Más opciones del menú"', $hook);

        // Aislamiento: con tenant nulo retorna cadena vacía sin errores
        Filament::setTenant(null);
        $hookNulo = (string) FilamentView::renderHook(PanelsRenderHook::BODY_END);
        $this->assertSame('', trim($hookNulo), 'Render hook BODY_END debe retornar vacío cuando tenant es nulo.');
    }

    // =========================================================================
    // Sistema de diseño compartido: tokens, modo oscuro, layout y marca
    // =========================================================================

    public function test_hoja_de_tokens_compartida_define_modo_oscuro_y_movimiento_reducido(): void
    {
        $css = (string) file_get_contents(public_path('css/menuonl.css'));

        $this->assertStringContainsString('@media (prefers-color-scheme: dark)', $css);
        $this->assertStringContainsString('--mo-superficie:', $css);
        $this->assertStringContainsString('@media (prefers-reduced-motion: reduce)', $css);
        $this->assertFileExists(public_path('favicon.svg'));
        $this->assertGreaterThan(0, filesize(public_path('favicon.svg')));
    }

    public function test_paginas_publicas_comparten_layout_con_hoja_externa_favicon_y_esquema_de_color(): void
    {
        $restaurante = Restaurante::factory()->create(['estado' => 'activo']);
        RestauranteIdioma::factory()->create(['restaurante_id' => $restaurante->id, 'idioma' => 'es']);

        $urls = [
            '/',
            route('menu.publico', ['slug' => $restaurante->slug]),
            route('menu.publico', ['slug' => 'no-existe']),
            route('invitaciones.mostrar', ['token' => 'token-invalido']),
        ];

        foreach ($urls as $url) {
            $respuesta = $this->get($url);

            $respuesta->assertSee('css/menuonl.css', false);
            $respuesta->assertSee('favicon.svg', false);
            $respuesta->assertSee('<meta name="color-scheme" content="light">', false);
            $this->assertStringNotContainsString('<style>', $respuesta->getContent(), "$url no debe llevar CSS en línea duplicado.");
        }
    }

    public function test_menu_publico_muestra_hero_con_portada_prioritaria_y_logo_con_dimensiones(): void
    {
        $restaurante = Restaurante::factory()->create(['estado' => 'activo', 'eslogan' => 'Cocina de brasa']);
        $restaurante->update([
            'portada' => "restaurantes/{$restaurante->id}/portada.webp",
            'logo' => "restaurantes/{$restaurante->id}/logo.webp",
        ]);
        RestauranteIdioma::factory()->create(['restaurante_id' => $restaurante->id, 'idioma' => 'es']);

        $respuesta = $this->get(route('menu.publico', ['slug' => $restaurante->slug]));

        $respuesta->assertOk();
        $respuesta->assertSee('hero--con-portada', false);
        $respuesta->assertSee('fetchpriority="high"', false);
        $respuesta->assertSee('width="72" height="72"', false);
        $respuesta->assertSee('Cocina de brasa');
        $respuesta->assertSee('href="#carta"', false);
    }

    public function test_cada_tema_se_aplica_en_el_menu_publico_y_un_tema_desconocido_cae_al_calido(): void
    {
        foreach (array_keys(Catalogos::temas()) as $tema) {
            $restaurante = Restaurante::factory()->create(['estado' => 'activo', 'tema' => $tema]);

            $this->get(route('menu.publico', ['slug' => $restaurante->slug]))
                ->assertOk()
                ->assertSee('data-tema="'.$tema.'"', false);
        }

        $raro = Restaurante::factory()->create(['estado' => 'activo']);
        DB::table('restaurantes')->where('id', $raro->id)->update(['tema' => 'inventado']);

        $this->get(route('menu.publico', ['slug' => $raro->slug]))->assertSee('data-tema="calido"', false);

        $css = (string) file_get_contents(public_path('css/menuonl.css'));
        foreach (['calido', 'editorial', 'rustico'] as $tema) {
            $this->assertStringContainsString('[data-tema="'.$tema.'"]', $css);
        }
    }

    public function test_color_de_acento_valido_se_imprime_y_uno_malicioso_se_descarta(): void
    {
        $valido = Restaurante::factory()->create(['estado' => 'activo', 'color_acento' => '#112233']);
        $this->get(route('menu.publico', ['slug' => $valido->slug]))
            ->assertSee('--mo-acento: #112233', false)
            ->assertSee('--mo-acento-texto: #ffffff', false);

        $claro = Restaurante::factory()->create(['estado' => 'activo', 'color_acento' => '#f5e6a8']);
        $this->get(route('menu.publico', ['slug' => $claro->slug]))->assertSee('--mo-acento-texto: #17171e', false);

        $malicioso = Restaurante::factory()->create(['estado' => 'activo']);
        DB::table('restaurantes')->where('id', $malicioso->id)
            ->update(['color_acento' => 'red;x:y']); // 7 caracteres: color_acento es string(7) y MariaDB rechaza valores más largos.

        $respuesta = $this->get(route('menu.publico', ['slug' => $malicioso->slug]));
        $respuesta->assertOk();
        $respuesta->assertDontSee('red;x:y', false);
        $respuesta->assertDontSee('--mo-acento:', false);
    }

    public function test_menu_publico_incluye_control_de_apariencia_y_script_de_persistencia(): void
    {
        $restaurante = Restaurante::factory()->create(['estado' => 'activo']);
        RestauranteIdioma::factory()->create(['restaurante_id' => $restaurante->id, 'idioma' => 'es']);

        $respuesta = $this->get(route('menu.publico', ['slug' => $restaurante->slug]));

        $respuesta->assertOk();
        $respuesta->assertSee('class="pie-publico"', false);
        $respuesta->assertSee('segmentado');
        $respuesta->assertSee('<legend>Apariencia</legend>', false);
        $respuesta->assertSee('data-apariencia-opcion', false);
        $respuesta->assertSee('value="system"', false);
        $respuesta->assertSee('value="light"', false);
        $respuesta->assertSee('value="dark"', false);
        $respuesta->assertSee('Automático');
        $respuesta->assertSee('Claro');
        $respuesta->assertSee('Oscuro');

        // La lógica vive en un archivo propio (la CSP no admite scripts en línea): la página lo carga y el archivo la contiene.
        $respuesta->assertSee('js/apariencia.js', false);
        $guion = (string) file_get_contents(public_path('js/apariencia.js'));
        $this->assertStringContainsString("var clave = 'filament:theme:mode';", $guion);
        $this->assertStringContainsString("document.documentElement.setAttribute('data-apariencia', modo);", $guion);
    }

    public function test_portada_usa_tokens_y_no_colores_fijos_ni_consultas_en_la_vista(): void
    {
        $respuesta = $this->get('/');

        $respuesta->assertOk();
        $respuesta->assertSee('bg-fondo', false);
        $respuesta->assertDontSee('#FBFBFD', false);
        $respuesta->assertDontSee('#1D1D1F', false);

        $vista = (string) file_get_contents(resource_path('views/welcome.blade.php'));
        $this->assertStringNotContainsString('Schema::', $vista);
        $this->assertStringNotContainsString('Restaurante::', $vista);
    }

    public function test_panel_tiene_marca_propia_favicon_y_boton_de_menu_publico_con_componentes_filament(): void
    {
        $restaurante = $this->crearRestaurante('marca-propia');
        $propietario = $this->crearUsuario('propietario', $restaurante);
        $this->entrarAlPanel($propietario, $restaurante);

        $panel = Filament::getPanel('admin');

        $this->assertSame(config('app.name'), $panel->getBrandName());
        $this->assertStringContainsString('favicon.svg', (string) $panel->getFavicon());

        $boton = (string) FilamentView::renderHook(PanelsRenderHook::USER_MENU_BEFORE);
        $this->assertStringContainsString('fi-btn', $boton);
        $this->assertStringContainsString('target="_blank"', $boton);
        $this->assertStringContainsString('rel="noopener noreferrer"', $boton);
    }

    public function test_detalle_de_historial_se_renderiza_con_componentes_filament_y_escapa_valores(): void
    {
        $html = view('filament.historial.cambios', [
            'cambios' => ['nombre' => ['antes' => '<b>viejo</b>', 'despues' => 'nuevo']],
        ])->render();

        $this->assertStringContainsString('Antes', $html);
        $this->assertStringContainsString('Después', $html);
        $this->assertStringNotContainsString('<b>viejo</b>', $html);

        $vacio = view('filament.historial.cambios', ['cambios' => []])->render();
        $this->assertStringContainsString('Sin detalles de cambios registrados.', $vacio);
    }

    // =========================================================================
    // Hoja inferior, cabecera dinámica, tarjetas y View Transitions
    // =========================================================================

    public function test_hoja_del_carrito_es_un_dialogo_accesible_arrastrable_con_alpine(): void
    {
        $restaurante = Restaurante::factory()->create(['estado' => 'activo', 'pedidos_activos' => true, 'whatsapp_pedidos' => '5215555555555']);
        RestauranteIdioma::factory()->create(['restaurante_id' => $restaurante->id, 'idioma' => 'es']);
        Horario::factory()->create(['restaurante_id' => $restaurante->id, 'dia_semana' => now($restaurante->zona_horaria)->dayOfWeek, 'abre' => '00:00:00', 'cierra' => '23:59:00']);

        $respuesta = $this->get(route('menu.publico', ['slug' => $restaurante->slug]));

        $respuesta->assertOk();
        $respuesta->assertSee('id="cartModalBackdrop"', false);
        $respuesta->assertSee('role="dialog"', false);
        $respuesta->assertSee('aria-modal="true"', false);
        $respuesta->assertSee('aria-labelledby="cartDrawerTitle"', false);
        $respuesta->assertSee('x-data="hoja"', false);
        $respuesta->assertSee('class="hoja-asa" aria-hidden="true"', false);
        $respuesta->assertSee('min-w-[44px] min-h-[44px]', false);
        $this->assertStringContainsString("e.key === 'Escape' || e.key === 'Esc'", (string) file_get_contents(resource_path('js/pedido/presentacion-hoja.js')));
        $this->assertStringContainsString("dialogo.addEventListener('hoja-cerrar'", (string) file_get_contents(resource_path('js/pedido/presentacion-hoja.js')));
    }

    public function test_solo_el_menu_publico_carga_alpine_y_sin_dependencias_de_animacion(): void
    {
        $paquete = json_decode((string) file_get_contents(base_path('package.json')), true);
        $dependencias = array_keys(($paquete['dependencies'] ?? []) + ($paquete['devDependencies'] ?? []));

        $this->assertContains('@alpinejs/csp', $dependencias, 'Alpine debe usar la compilación CSP: la política no permite unsafe-eval.');
        $this->assertNotContains('alpinejs', $dependencias);
        foreach (['gsap', 'framer-motion', 'animejs', 'aos', 'motion', 'lottie-web'] as $libreria) {
            $this->assertNotContains($libreria, $dependencias, "No se permiten librerías de animación ($libreria).");
        }

        $vite = (string) file_get_contents(base_path('vite.config.js'));
        $this->assertStringContainsString('resources/js/menu.js', $vite);
        $this->assertSame('', trim((string) file_get_contents(resource_path('js/app.js')), '/ 
'));
        $this->assertStringContainsString('@alpinejs/csp', (string) file_get_contents(resource_path('js/menu.js')));
    }

    public function test_cabecera_dinamica_tiene_centinela_barra_decorativa_y_navegacion_etiquetada(): void
    {
        $restaurante = Restaurante::factory()->create(['estado' => 'activo']);
        RestauranteIdioma::factory()->create(['restaurante_id' => $restaurante->id, 'idioma' => 'es']);

        $respuesta = $this->get(route('menu.publico', ['slug' => $restaurante->slug]));

        $respuesta->assertOk();
        $respuesta->assertSee('x-data="cabecera"', false);
        $respuesta->assertSee('x-init="iniciar"', false);
        $respuesta->assertSee('data-centinela', false);
        $respuesta->assertSee('class="barra-compacta" aria-hidden="true"', false);
        $respuesta->assertSee('aria-label="Categorías del menú"', false);

        $css = (string) file_get_contents(public_path('css/menuonl.css'));
        $this->assertStringContainsString('.cabecera-compacta .sticky-nav', $css);
        $this->assertStringContainsString('@media (prefers-reduced-motion: reduce)', $css);
    }

    public function test_tarjeta_de_plato_tiene_jerarquia_imagen_con_marco_y_entrada_escalonada(): void
    {
        $restaurante = Restaurante::factory()->create(['estado' => 'activo']);
        RestauranteIdioma::factory()->create(['restaurante_id' => $restaurante->id, 'idioma' => 'es']);
        $categoria = Categoria::factory()->create(['restaurante_id' => $restaurante->id]);
        CategoriaTraduccion::factory()->create(['categoria_id' => $categoria->id, 'idioma' => 'es', 'nombre' => 'Fuertes']);
        $plato = Producto::factory()->create(['restaurante_id' => $restaurante->id, 'categoria_id' => $categoria->id, 'precio' => 120, 'disponible' => true, 'visible' => true]);
        $plato->update(['imagen' => "restaurantes/{$restaurante->id}/productos/a.webp"]);
        ProductoTraduccion::factory()->create(['producto_id' => $plato->id, 'idioma' => 'es', 'nombre' => 'Pulpo a la brasa']);

        $respuesta = $this->get(route('menu.publico', ['slug' => $restaurante->slug]));

        $respuesta->assertOk();
        $respuesta->assertSee('class="plato" data-plato="'.$plato->id.'" style="--i: 0"', false);
        $respuesta->assertSee('class="plato-imagen"', false);
        $respuesta->assertSee('loading="lazy" decoding="async" width="96" height="96"', false);
        $respuesta->assertSee('class="plato-precio"', false);

        $css = (string) file_get_contents(public_path('css/menuonl.css'));
        $this->assertStringContainsString('animation-timeline: view()', $css);
        $this->assertStringContainsString('@supports (animation-timeline: view())', $css);
        $this->assertStringContainsString('.plato:active', $css);
        $this->assertStringContainsString('prefers-reduced-motion: no-preference', $css);
    }

    public function test_detalle_del_plato_es_dialogo_accesible_con_transiciones_y_fallback(): void
    {
        $restaurante = Restaurante::factory()->create(['estado' => 'activo']);
        RestauranteIdioma::factory()->create(['restaurante_id' => $restaurante->id, 'idioma' => 'es']);
        $categoria = Categoria::factory()->create(['restaurante_id' => $restaurante->id]);
        CategoriaTraduccion::factory()->create(['categoria_id' => $categoria->id, 'idioma' => 'es', 'nombre' => 'Postres']);
        $plato = Producto::factory()->create(['restaurante_id' => $restaurante->id, 'categoria_id' => $categoria->id, 'disponible' => true, 'visible' => true]);
        ProductoTraduccion::factory()->create(['producto_id' => $plato->id, 'idioma' => 'es', 'nombre' => 'Flan casero']);

        $respuesta = $this->get(route('menu.publico', ['slug' => $restaurante->slug]));

        $respuesta->assertOk();
        $respuesta->assertSee('id="platoHojaFondo"', false);
        $respuesta->assertSee('aria-labelledby="platoHojaTitulo"', false);
        $respuesta->assertSee('x-data="detallePlato"', false);
        $respuesta->assertSee('aria-label="Cerrar detalle del platillo"', false);
        $respuesta->assertSee('data-abrir-plato aria-haspopup="dialog">Flan casero</button>', false);

        $js = (string) file_get_contents(resource_path('js/menu.js'));
        $this->assertStringContainsString('document.startViewTransition', $js);
        $this->assertStringContainsString("typeof document.startViewTransition === 'function'", $js, 'Debe haber fallback sin View Transitions.');
        $this->assertStringContainsString("evento.key === 'Escape'", $js);
        $this->assertStringContainsString("evento.key !== 'Tab'", $js);
        $this->assertStringContainsString('viewTransitionName = nombreVt', $js);

        $css = (string) file_get_contents(public_path('css/menuonl.css'));
        $this->assertStringContainsString('::view-transition-group(*)', $css);
        $this->assertStringContainsString('aspect-ratio: 4 / 3', $css);
    }

    public function test_widget_resumen_restaurante_muestra_estado_acciones_directas_y_avisos_reales(): void
    {
        $restaurante = $this->crearRestaurante('casa-oaxaca');
        $restaurante->update([
            'nombre' => 'Casa Oaxaca',
            'eslogan' => 'Cocina tradicional oaxaqueña',
            'tema' => 'rustico',
            'pedidos_activos' => true,
            'whatsapp_pedidos' => null,
        ]);

        $propietario = $this->crearUsuario('propietario', $restaurante);
        $this->entrarAlPanel($propietario, $restaurante);

        Livewire::test(ResumenRestauranteWidget::class)
            ->assertSee('Tu menú está en línea')
            ->assertSee('Cerrado ahora')
            ->assertSee('Hoy no abres')
            ->assertSee('Agregar platillo')
            ->assertSee('Qué falta configurar')
            ->assertSee('Crea tu primera categoría, por ejemplo "Tacos" o "Bebidas".')
            ->assertSee('Crear categoría')
            ->assertSee('Activaste los pedidos, pero falta tu WhatsApp. Sin él no te llegan.')
            ->assertSee('Agregar WhatsApp')
            ->assertSee('Agrega tu horario para que tus clientes sepan cuándo abres.')
            ->assertSee('Agregar horario');
    }

    public function test_menu_publico_muestra_etiquetas_en_espanol_monograma_sin_logo_y_enlace_mapa(): void
    {
        $restaurante = Restaurante::factory()->create([
            'nombre' => 'Bosque de Agua',
            'slug' => 'bosque-de-agua',
            'logo' => null,
            'estado' => 'activo',
        ]);
        RestauranteIdioma::factory()->create(['restaurante_id' => $restaurante->id, 'idioma' => 'es']);

        Contacto::factory()->create([
            'restaurante_id' => $restaurante->id,
            'tipo' => 'direccion',
            'etiqueta' => 'Ubicación',
            'valor' => 'Av. Álvaro Obregón 120, Roma Norte',
            'visible' => true,
        ]);

        $categoria = Categoria::factory()->create(['restaurante_id' => $restaurante->id, 'visible' => true]);
        CategoriaTraduccion::factory()->create(['categoria_id' => $categoria->id, 'idioma' => 'es', 'nombre' => 'Especialidades']);

        $plato = Producto::factory()->create([
            'restaurante_id' => $restaurante->id,
            'categoria_id' => $categoria->id,
            'precio' => 240,
            'destacado' => true,
            'disponible' => true,
            'visible' => true,
        ]);
        ProductoTraduccion::factory()->create(['producto_id' => $plato->id, 'idioma' => 'es', 'nombre' => 'Mole negro']);

        $etiqueta = Etiqueta::firstOrCreate(['codigo' => 'sin_gluten'], ['icono' => 'wheat-off']);
        $plato->etiquetas()->attach($etiqueta);

        $respuesta = $this->get(route('menu.publico', ['slug' => $restaurante->slug]));

        $respuesta->assertOk();
        $respuesta->assertSee('hero-monograma', false);
        $respuesta->assertSee('Sin gluten');
        $respuesta->assertSee('data-etiqueta="sin_gluten"', false);
        $respuesta->assertSee('https://www.google.com/maps/search/?api=1&amp;query=', false);
        $respuesta->assertSee('id="destacado-dish-title-'.$plato->id.'"', false);
        $respuesta->assertSee('id="dish-title-'.$plato->id.'"', false);
    }
}
