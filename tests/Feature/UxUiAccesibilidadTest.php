<?php

namespace Tests\Feature;

use App\Filament\Resources\Categorias\CategoriaResource;
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
use App\Filament\Resources\Productos\Pages\ListProductos;
use App\Filament\Resources\Productos\ProductoResource;
use App\Filament\Resources\Restaurantes\Pages\ListRestaurantes;
use App\Filament\Resources\Restaurantes\RestauranteResource;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\UserResource;
use App\Models\Categoria;
use App\Models\CategoriaTraduccion;
use App\Models\CodigoQr;
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
        $response->assertSee('MENUONL');
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
        $response->assertSee(url('/m/'.$demo->slug));
    }

    // =========================================================================
    // 2. Menú Público (GET /m/{slug}) — WCAG 2.2 AA y Experiencia del Comensal
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

        $response = $this->get('/m/trattoria-bella?mesa=7');

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

        // Manejador global de tecla Escape para cerrar cajón modal
        $response->assertSee("e.key === 'Escape' || e.key === 'Esc'", false);

        // Notificación Toast accesible con role="status" y aria-live="polite"
        $response->assertSee('id="cartToast"', false);
        $response->assertSee('role="status"', false);
        $response->assertSee('aria-live="polite"', false);

        // Banner de confirmación en línea para vaciar pedido
        $response->assertSee('id="vaciarConfirmBanner"', false);
        $response->assertSee('¿Seguro que deseas vaciar todos los platillos de tu pedido?');
        $response->assertSee('Sí, vaciar');
        $response->assertSee('Cancelar');

        // Transparencia de estado: abierto ahora y mesa activa
        $response->assertSee('Abierto ahora');
        $response->assertSee('Mesa 7');

        // Etiquetas dietéticas con semántica de lista accesible
        $response->assertSee('<ul class="flex flex-wrap gap-1 mt-2.5 list-none" aria-label="Etiquetas dietéticas', false);
        $response->assertSee('<li class="tag', false);

        // Insignia accesible para platillo recomendado
        $response->assertSee('Recomendado');
        $response->assertSee('aria-label="Platillo recomendado"', false);

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

        $response = $this->get('/m/'.$restaurante->slug);

        $response->assertStatus(200);
        $response->assertSee('Cerrado');
        $response->assertSee('El restaurante se encuentra cerrado en este momento. Los pedidos están pausados hasta su próxima apertura.');
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

        $response = $this->get('/m/'.$restaurante->slug);

        $response->assertStatus(200);
        $response->assertSee('aria-label="Selector de idioma"', false);
        $response->assertSee('aria-label="Cambiar idioma a EN"', false);
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

        $response = $this->get('/m/'.$restaurante->slug);

        $response->assertStatus(200);
        $response->assertDontSee($xssPayload, false);
        $response->assertSee(htmlspecialchars($xssPayload, ENT_QUOTES), false);
    }

    // =========================================================================
    // 3. Menú No Disponible (GET /q/{codigo} y /m/{slug} inválidos/inactivos)
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
        $response->assertSee('Ir a la portada de MENUONL');
        $response->assertSee('min-h-[44px]');
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
        $responseMenu = $this->get('/m/'.$restaurante->slug);
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
        $response->assertSee('Sin necesidad de descargar aplicaciones · MENUONL');
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
        // Menú y Carta
        $this->assertSame('Menú y Carta', CategoriaResource::getNavigationGroup());
        $this->assertSame('Menú y Carta', ProductoResource::getNavigationGroup());

        // Operación del Local
        $this->assertSame('Operación del Local', CodigoQrResource::getNavigationGroup());
        $this->assertSame('Operación del Local', HorarioResource::getNavigationGroup());
        $this->assertSame('Operación del Local', ContactoResource::getNavigationGroup());

        // Equipo y Acceso
        $this->assertSame('Equipo y Acceso', UserResource::getNavigationGroup());
        $this->assertSame('Equipo y Acceso', InvitacionResource::getNavigationGroup());

        // Administración Global
        $this->assertSame('Administración Global', RestauranteResource::getNavigationGroup());
        $this->assertSame('Administración Global', HistorialCambioResource::getNavigationGroup());
    }

    public function test_filament_tablas_tienen_encabezados_de_estado_vacio_en_espanol(): void
    {
        $restaurante = $this->crearRestaurante('restaurante-vacio');
        $propietario = $this->crearUsuario('propietario', $restaurante);
        $this->entrarAlPanel($propietario, $restaurante);

        // Tablas con contexto de restaurante
        $this->assertSame('No hay categorías registradas', Livewire::test(ListCategorias::class)->instance()->getTable()->getEmptyStateHeading());
        $this->assertSame('Sin códigos QR generados', Livewire::test(ListCodigosQr::class)->instance()->getTable()->getEmptyStateHeading());
        $this->assertSame('Sin horarios de atención configurados', Livewire::test(ListHorarios::class)->instance()->getTable()->getEmptyStateHeading());
        $this->assertSame('No hay canales de contacto registrados', Livewire::test(ListContactos::class)->instance()->getTable()->getEmptyStateHeading());
        $this->assertSame('No hay platillos en el menú', Livewire::test(ListProductos::class)->instance()->getTable()->getEmptyStateHeading());
        $this->assertSame('No hay invitaciones enviadas', Livewire::test(ListInvitaciones::class)->instance()->getTable()->getEmptyStateHeading());
        $this->assertSame('No hay miembros adicionales en el equipo', Livewire::test(ListUsers::class)->instance()->getTable()->getEmptyStateHeading());
        $this->assertSame('Sin eventos de auditoría', Livewire::test(ListHistorialCambios::class)->instance()->getTable()->getEmptyStateHeading());

        // Tablas no asociadas a tenant (Administración Global / Superadmin)
        $superadmin = $this->crearUsuario('superadmin');
        $this->entrarAlPanel($superadmin, $restaurante);

        $this->assertSame('No hay restaurantes registrados', Livewire::test(ListRestaurantes::class)->instance()->getTable()->getEmptyStateHeading());
    }

    public function test_filament_panel_registra_acceso_directo_a_menu_publico_en_tenant_menu_y_render_hook(): void
    {
        $restaurante = $this->crearRestaurante('los-arcos');
        $propietario = $this->crearUsuario('propietario', $restaurante);
        $this->entrarAlPanel($propietario, $restaurante);

        $panel = Filament::getPanel('admin');
        $tenantItems = $panel->getTenantMenuItems();

        $this->assertNotEmpty($tenantItems, 'El panel no tiene configurado tenantMenuItems.');

        $publicMenuItem = collect($tenantItems)->first(fn ($item) => $item->getLabel() === 'Ver menú público');
        $this->assertNotNull($publicMenuItem, 'El panel no tiene registrado el ítem "Ver menú público" en tenantMenuItems.');
        $this->assertTrue($publicMenuItem->shouldOpenUrlInNewTab(), 'El ítem "Ver menú público" debe abrirse en pestaña nueva.');
        $this->assertSame(route('menu.publico', ['slug' => 'los-arcos']), $publicMenuItem->getUrl());

        // Render Hook: PanelsRenderHook::USER_MENU_BEFORE
        $renderedHook = FilamentView::renderHook(PanelsRenderHook::USER_MENU_BEFORE);
        $this->assertStringContainsString('Ver menú público', (string) $renderedHook);
        $this->assertStringContainsString(route('menu.publico', ['slug' => 'los-arcos']), (string) $renderedHook);
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
            $respuesta->assertSee('<meta name="color-scheme" content="light dark">', false);
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
            ->update(['color_acento' => 'red;background:url(//x.test)']);

        $respuesta = $this->get(route('menu.publico', ['slug' => $malicioso->slug]));
        $respuesta->assertOk();
        $respuesta->assertDontSee('url(//x.test)', false);
        $respuesta->assertDontSee('--mo-acento:', false);
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

        $this->assertSame('MENUONL', $panel->getBrandName());
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
        $respuesta->assertSee("e.key === 'Escape' || e.key === 'Esc'", false);
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
}
