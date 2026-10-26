@php
    $numeroPedidos = $restaurante->whatsappPedidosDigitos();
    $puedePedir = $restaurante->pedidos_activos && $estaAbierto && $numeroPedidos !== null;
@endphp
<x-layout.publico
    :pedido-js="$puedePedir ? 'resources/js/menu-pedido.js' : null"
    :titulo="$restaurante->traduccion($idioma)->nombre.' · Menú'"
    :descripcion="$restaurante->traduccion($idioma)->descripcion"
    :lang="str_replace('_', '-', app()->getLocale())"
    html-class="scroll-smooth"
    class="menu-publico"
    x-data="cabecera"
    x-init="iniciar"
    :tema="$restaurante->temaVigente()"
    :acento="$restaurante->colorAcentoSeguro()"
    :acento-texto="$restaurante->colorAcentoTexto()"
    js="resources/js/menu.js"
>
        <!-- Cart Toast Notification -->
        <div id="cartToast" role="status" aria-live="polite" class="cart-toast" aria-atomic="true">
            <svg class="w-5 h-5 shrink-0 text-exito-texto" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="m8 12.5 2.5 2.5L16 9.5"/></svg>
            <span id="cartToastMessage">Platillo agregado</span>
        </div>

        @php
            $ahora = now($restaurante->zona_horaria);
            $diaHoy = (int) $ahora->dayOfWeek;
            $turnosHoy = $horarios->where('dia_semana', $diaHoy)->where('cerrado', false)->sortBy('abre');
            $tienePortada = filled($restaurante->portada);
            $nombresDias = \App\Services\Catalogos::diasSemana();

            // Próxima apertura, solo para el aviso de "cerrado" (texto; no afecta si se puede pedir).
            $proximaApertura = null;
            if (! $estaAbierto) {
                for ($desplazamiento = 0; $desplazamiento < 7 && $proximaApertura === null; $desplazamiento++) {
                    $fecha = $ahora->copy()->addDays($desplazamiento);
                    foreach ($horarios->where('dia_semana', (int) $fecha->dayOfWeek)->where('cerrado', false)->sortBy('abre') as $turno) {
                        if ($desplazamiento === 0 && substr((string) $turno->abre, 0, 5) <= $ahora->format('H:i')) {
                            continue;
                        }
                        $cuando = match ($desplazamiento) {
                            0 => 'hoy',
                            1 => 'mañana',
                            default => 'el '.mb_strtolower($nombresDias[(int) $fecha->dayOfWeek]),
                        };
                        $hora = \App\Services\FormatoFecha::hora($turno->abre);
                        $proximaApertura = $cuando.(str_starts_with($hora, '1:') ? ' a la ' : ' a las ').$hora;
                        break;
                    }
                }
            }
        @endphp

        <!-- Hero -->
        <header class="hero {{ $tienePortada ? 'hero--con-portada' : '' }}">
            @if ($tienePortada)
                <div class="hero-media">
                    <img src="{{ Storage::url($restaurante->portada) }}" alt="" width="1280" height="720" fetchpriority="high" decoding="async">
                </div>
            @endif

            <div class="contenedor hero-contenido">
                @if (filled($restaurante->logo))
                    <img src="{{ Storage::url($restaurante->logo) }}" alt="" class="hero-logo" width="72" height="72" decoding="async">
                @else
                    <div class="hero-logo hero-monograma" aria-hidden="true">{{ mb_strtoupper(mb_substr($restaurante->nombre, 0, 1)) }}</div>
                @endif

                <span class="regla-acento" aria-hidden="true"></span>
                @if (filled($restaurante->eslogan))
                    <p class="hero-kicker">{{ $restaurante->eslogan }}</p>
                @endif
                <h1 class="hero-titulo">{{ $restaurante->traduccion($idioma)->nombre }}</h1>
                @if ($restDesc = $restaurante->traduccion($idioma)->descripcion)
                    <p class="hero-descripcion">{{ $restDesc }}</p>
                @endif

                <div class="hero-datos">
                    @if ($estaAbierto)
                        <span class="badge-open">
                            <span class="w-2 h-2 rounded-full bg-current animate-pulse" aria-hidden="true"></span>
                            Abierto ahora
                        </span>
                    @else
                        <span class="badge-closed">
                            <span class="w-2 h-2 rounded-full bg-current" aria-hidden="true"></span>
                            Cerrado
                        </span>
                    @endif

                    @if ($turnosHoy->isNotEmpty())
                        <span class="badge-mesa">
                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 7v5l3 2"/></svg>
                            <span>Hoy: @foreach ($turnosHoy as $t)<span class="whitespace-nowrap">{{ \App\Services\FormatoFecha::rango($t->abre, $t->cierra) }}</span>@if (! $loop->last) y @endif @endforeach</span>
                        </span>
                    @endif

                    @if ($mesa)
                        <span class="badge-mesa">
                            Mesa {{ $mesa }}
                        </span>
                    @endif
                </div>

                <div class="hero-acciones">
                    <a href="#carta" class="btn-cta focus-visible:ring-2 focus-visible:ring-foco focus-visible:outline-none">Ver menú</a>
                    @if ($horarios->isNotEmpty())
                        <a href="#horarios" class="btn-cta-secundario focus-visible:ring-2 focus-visible:ring-foco focus-visible:outline-none">Horario</a>
                    @endif

                    <!-- Language selector -->
                    @if (count($idiomasDisponibles) > 1)
                        <nav class="idiomas sm:ml-auto" aria-label="Selector de idioma">
                            @foreach ($idiomasDisponibles as $langOption)
                                <a
                                    href="{{ request()->fullUrlWithQuery(['lang' => $langOption]) }}"
                                    class="idioma-link min-h-[44px] min-w-[44px] focus-visible:ring-2 focus-visible:ring-foco focus-visible:outline-none"
                                    aria-current="{{ $idioma === $langOption ? 'page' : 'false' }}"
                                    aria-label="Ver el menú en {{ \App\Services\Catalogos::idiomas()[$langOption] ?? strtoupper($langOption) }}"
                                >
                                    {{ $langOption }}
                                </a>
                            @endforeach
                        </nav>
                    @endif
                </div>

                <!-- Order eligibility warning banner -->
                @if (! $puedePedir)
                    <div role="status" aria-live="polite" class="aviso mt-5 max-w-2xl">
                        <svg class="w-5 h-5 shrink-0 text-texto-medio" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span>
                            @if (! $restaurante->pedidos_activos)
                                Para ordenar, pídele a tu mesero.
                            @elseif (! $estaAbierto)
                                Estamos cerrados.@if ($proximaApertura) Abrimos {{ $proximaApertura }}.@endif Puedes ver el menú; los pedidos se activan al abrir.
                            @elseif ($numeroPedidos === null)
                                Por ahora no recibimos pedidos aquí. Pídele a tu mesero.
                            @endif
                        </span>
                    </div>
                @endif
            </div>
        </header>

        <div data-centinela aria-hidden="true"></div>

        <!-- Barra compacta: aparece al salir el hero -->
        <div class="barra-compacta" aria-hidden="true">
            <div class="contenedor flex items-center gap-3">
                @if (filled($restaurante->logo))
                    <img src="{{ Storage::url($restaurante->logo) }}" alt="" class="barra-logo" width="28" height="28" decoding="async">
                @else
                    <span class="barra-logo barra-monograma" aria-hidden="true">{{ mb_strtoupper(mb_substr($restaurante->nombre, 0, 1)) }}</span>
                @endif
                <span class="barra-nombre">{{ $restaurante->traduccion($idioma)->nombre }}</span>
                <span class="{{ $estaAbierto ? 'badge-open' : 'badge-closed' }} ml-auto">{{ $estaAbierto ? 'Abierto ahora' : 'Cerrado' }}</span>
            </div>
        </div>

        <!-- Sticky Category Navigation Bar -->
        <nav class="sticky-nav" aria-label="Categorías del menú">
            <div class="nav-scroller contenedor flex items-center overflow-x-auto">
                @if ($destacados->isNotEmpty())
                    <a href="#destacados" class="nav-pill min-h-[44px] focus-visible:ring-2 focus-visible:ring-foco focus-visible:outline-none">Recomendados</a>
                @endif

                @foreach ($categorias as $cat)
                    @php
                        $catNom = $cat->traduccion($idioma)?->nombre ?? $cat->traduccion()?->nombre ?? 'Categoría';
                    @endphp
                    <a href="#cat-{{ $cat->id }}" class="nav-pill min-h-[44px] focus-visible:ring-2 focus-visible:ring-foco focus-visible:outline-none">{{ $catNom }}</a>
                @endforeach

                @if ($horarios->isNotEmpty())
                    <a href="#horarios" class="nav-pill min-h-[44px] focus-visible:ring-2 focus-visible:ring-foco focus-visible:outline-none">Horario</a>
                @endif

                @if ($contactos->isNotEmpty())
                    <a href="#contacto" class="nav-pill min-h-[44px] focus-visible:ring-2 focus-visible:ring-foco focus-visible:outline-none">Contacto</a>
                @endif
            </div>
        </nav>

        <!-- Main Content -->
        <main id="carta" class="contenedor py-8">

            @if ($categorias->isEmpty() && $destacados->isEmpty())
                <div class="vacio">
                    <span class="vacio-icono"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h10"/></svg></span>
                    <h2 class="vacio-titulo">Este menú se está preparando</h2>
                    <p>Pídele la carta a tu mesero.</p>
                </div>
            @endif

            <!-- Featured Section -->
            @if ($destacados->isNotEmpty())
                <section id="destacados" class="seccion">
                    <h2 class="seccion-titulo">Recomendados</h2>

                    <div class="platos">
                        @foreach ($destacados as $prod)
                            @include('menu.partials.dish-card', ['indice' => $loop->index, 'prod' => $prod, 'restaurante' => $restaurante, 'idioma' => $idioma, 'puedePedir' => $puedePedir, 'esDestacado' => true])
                        @endforeach
                    </div>
                </section>
            @endif

            <!-- Categories and Dishes -->
            @foreach ($categorias as $cat)
                @php
                    $catNom = $cat->traduccion($idioma)?->nombre ?? $cat->traduccion()?->nombre ?? 'Categoría';
                    $catDesc = $cat->traduccion($idioma)?->descripcion ?? $cat->traduccion()?->descripcion;
                @endphp
                <section id="cat-{{ $cat->id }}" class="seccion">
                    <h2 class="seccion-titulo">{{ $catNom }}</h2>
                    @if ($catDesc)
                        <p class="seccion-descripcion">{{ $catDesc }}</p>
                    @endif

                    @if ($cat->productos->isEmpty())
                        <p class="text-texto-suave text-sm italic mt-4">Pronto habrá platillos aquí.</p>
                    @else
                        <div class="platos">
                            @foreach ($cat->productos as $prod)
                                @include('menu.partials.dish-card', ['indice' => $loop->index, 'prod' => $prod, 'restaurante' => $restaurante, 'idioma' => $idioma, 'puedePedir' => $puedePedir])
                            @endforeach
                        </div>
                    @endif
                </section>
            @endforeach

            <!-- Weekly Schedule Table -->
            @if ($horarios->isNotEmpty())
                <section id="horarios" class="seccion">
                    <h2 class="seccion-titulo">Horario</h2>
                    @php
                        $dias = \App\Services\Catalogos::diasSemana();
                        $horariosPorDia = $horarios->groupBy('dia_semana');
                    @endphp
                    <div class="mt-3 max-w-xl text-sm">
                        @foreach ($dias as $numDia => $nombreDia)
                            @php
                                $turnos = $horariosPorDia->get($numDia);
                            @endphp
                            <div class="py-2.5 flex items-center justify-between border-b divisor {{ $numDia === $diaHoy ? 'font-bold text-texto-fuerte' : 'text-texto-medio' }}">
                                <span>{{ $nombreDia }}</span>
                                <span>
                                    @if ($turnos && $turnos->where('cerrado', false)->isNotEmpty())
                                        {{ $turnos->where('cerrado', false)->sortBy('abre')->map(fn ($t) => \App\Services\FormatoFecha::rango($t->abre, $t->cierra))->implode(' y ') }}
                                    @else
                                        <span class="text-texto-suave font-normal">Cerrado</span>
                                    @endif
                                </span>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            <!-- Safe Contacts Section -->
            @if ($contactos->isNotEmpty())
                <section id="contacto" class="seccion">
                    <h2 class="seccion-titulo">Contacto</h2>
                    <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                        @foreach ($contactos as $contacto)
                            @php
                                $valorContacto = trim((string) $contacto->valor);
                                $esHttp = (bool) preg_match('/\Ahttps?:\/\//i', $valorContacto);
                                $href = match ($contacto->tipo) {
                                    'telefono' => 'tel:' . preg_replace('/[^0-9+]/', '', $valorContacto),
                                    'whatsapp' => 'https://wa.me/' . preg_replace('/[^0-9]/', '', $valorContacto),
                                    'email' => str_starts_with(strtolower($valorContacto), 'mailto:') ? $valorContacto : 'mailto:' . $valorContacto,
                                    'direccion' => $esHttp ? $valorContacto : 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($valorContacto),
                                    'instagram' => $esHttp ? $valorContacto : 'https://instagram.com/' . ltrim($valorContacto, '@/'),
                                    'facebook' => $esHttp ? $valorContacto : 'https://facebook.com/' . ltrim($valorContacto, '@/'),
                                    'tiktok' => $esHttp ? $valorContacto : 'https://tiktok.com/@' . ltrim($valorContacto, '@/'),
                                    default => $esHttp ? $valorContacto : 'https://' . ltrim($valorContacto, '/'),
                                };
                            @endphp
                            <a
                                href="{{ $href }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="min-h-[44px] px-4 py-3 rounded-lg border border-borde hover:border-acento bg-superficie transition-colors flex flex-col sm:flex-row sm:items-center justify-between gap-1.5 sm:gap-3 text-texto-medio focus-visible:ring-2 focus-visible:ring-foco focus-visible:outline-none"
                            >
                                <span class="font-medium text-xs uppercase tracking-wider text-texto-suave shrink-0">{{ $contacto->etiqueta ?: (\App\Services\Catalogos::tiposContacto()[$contacto->tipo] ?? $contacto->tipo) }}</span>
                                <span class="font-semibold text-texto-fuerte text-sm line-clamp-2 break-words text-left">{{ $contacto->valor }}</span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

        </main>

        <footer class="pie-publico">
            <div class="contenedor flex flex-col items-center gap-4">
                <x-apariencia />
                <p class="inline-flex items-center gap-1.5">Hecho con <x-mb.logo variante="wordmark" :alto="16" /></p>
            </div>
        </footer>

        <!-- Detalle del plato (hoja inferior) -->
        <div
            id="platoHojaFondo"
            class="cart-modal-backdrop"
            role="dialog"
            aria-modal="true"
            aria-labelledby="platoHojaTitulo"
            x-data="detallePlato"
            @click.window="clic"
            @click="cerrarFondo"
            @keydown.window="teclas"
            @hoja-cerrar="cerrar"
        >
            <div class="cart-drawer plato-hoja" x-data="hoja" :style="estilo">
                <div class="hoja-cabecera flex items-center justify-between gap-2 pb-3 mb-2" @pointerdown="empezar" @pointermove="mover" @pointerup="soltar" @pointercancel="soltar">
                    <span class="hoja-asa" aria-hidden="true"></span>
                    <h3 id="platoHojaTitulo" class="plato-hoja-titulo" x-text="nombre"></h3>
                    <button type="button" x-ref="cerrar" @click="cerrar" class="btn-icono min-w-[44px] min-h-[44px] focus-visible:ring-2 focus-visible:ring-foco focus-visible:outline-none" aria-label="Cerrar detalle del platillo">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <figure class="plato-hoja-figura" x-show="imagen">
                    <img x-ref="imagen" :src="imagenSrc" alt="" width="640" height="480" class="plato-hoja-imagen">
                </figure>
                <p class="dish-desc" x-show="descripcion" x-text="descripcion"></p>
                <div x-ref="etiquetas" class="mt-2"></div>
                <div x-ref="compra" class="mt-3"></div>
            </div>
        </div>

        <!-- Pedido por WhatsApp (solo si $puedePedir). Lo anima resources/js/menu-pedido.js con los data-* de este contenedor. -->
        @if ($puedePedir)
            <div
                data-mo-pedido
                data-numero="{{ $numeroPedidos }}"
                data-restaurante="{{ $restaurante->nombre }}"
                data-moneda="{{ strtoupper($restaurante->moneda) }}"
                data-locale="{{ \App\Services\FormatoMoneda::locale($idioma, $restaurante->moneda) }}"
                data-mesa="{{ $mesa }}"
                data-clave="{{ 'menuonl_cart_m_'.$restaurante->slug }}"
                data-clave-antigua="{{ 'menuonl_cart_'.$restaurante->slug }}"
            >
            <!-- Floating Cart Bar -->
            <button
                type="button"
                id="floatingCartBar"
                class="floating-cart-bar focus-visible:ring-2 focus-visible:ring-foco focus-visible:ring-offset-2 focus-visible:outline-none"
                hidden
                data-mo-barra
                aria-haspopup="dialog"
                aria-expanded="false"
                aria-controls="cartModalBackdrop"
                aria-label="Ver pedido"
            >
                <div class="flex items-center gap-3">
                    <span id="cartCountBadge" data-mo-cuenta class="bg-acento-texto text-acento font-extrabold text-xs px-2.5 py-1 rounded-full min-w-[24px] text-center">
                        0
                    </span>
                    <span class="font-bold text-sm">Ver pedido</span>
                </div>
                <div class="font-extrabold text-base">
                    <span id="cartTotalSummary" data-mo-barra-total>{{ \App\Services\FormatoMoneda::formatear(0, $restaurante->moneda, $idioma) }}</span>
                </div>
            </button>

            <!-- Cart Drawer Modal -->
            <div
                id="cartModalBackdrop"
                data-mo-dialogo
                class="cart-modal-backdrop"
                role="dialog"
                aria-modal="true"
                aria-labelledby="cartDrawerTitle"
            >
                <div class="cart-drawer" x-data="hoja" :style="estilo">
                    <div class="hoja-cabecera flex items-center justify-between pb-3 border-b divisor mb-4" @pointerdown="empezar" @pointermove="mover" @pointerup="soltar" @pointercancel="soltar">
                        <span class="hoja-asa" aria-hidden="true"></span>
                        <h3 id="cartDrawerTitle" class="text-lg font-bold text-texto-fuerte" style="font-family: var(--mo-fuente-titulo)">Tu pedido</h3>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                id="btnVaciarCarrito"
                                data-mo-vaciar
                                hidden
                                class="btn-texto focus-visible:ring-2 focus-visible:ring-foco focus-visible:outline-none"
                            >
                                Vaciar pedido
                            </button>
                            <button
                                type="button"
                                id="btnCloseCartModal"
                                data-mo-cerrar
                                class="btn-icono min-w-[44px] min-h-[44px] focus-visible:ring-2 focus-visible:ring-foco focus-visible:outline-none"
                                aria-label="Cerrar pedido"
                            >
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Inline Confirmation Banner for Vaciar Pedido (initially hidden) -->
                    <div id="vaciarConfirmBanner" data-mo-confirmar-vaciar hidden class="mb-4 aviso aviso-peligro flex-col" role="alertdialog" aria-labelledby="vaciarConfirmTitulo">
                        <p id="vaciarConfirmTitulo" class="aviso-titulo mb-2">¿Quitar todo de tu pedido?</p>
                        <div class="flex flex-wrap items-center gap-2">
                            <button
                                type="button"
                                data-mo-conservar
                                class="btn-secundario focus-visible:ring-2 focus-visible:ring-foco focus-visible:outline-none"
                            >
                                Conservar
                            </button>
                            <button
                                type="button"
                                data-mo-confirmar
                                class="btn-peligro focus-visible:ring-2 focus-visible:ring-foco focus-visible:outline-none"
                            >
                                Vaciar pedido
                            </button>
                        </div>
                    </div>

                    <!-- Confirmation shown after handing the order to WhatsApp -->
                    <div id="pedidoEnviadoBanner" data-mo-enviado hidden class="mb-4 aviso aviso-exito flex-col" role="status" tabindex="-1">
                        <p class="mb-2"><span class="aviso-titulo">Abrimos WhatsApp con tu pedido.</span> Tócale enviar ahí para que nos llegue.</p>
                        <div class="flex flex-wrap items-center gap-2">
                            <button type="button" data-mo-listo class="btn-primario focus-visible:ring-2 focus-visible:ring-foco focus-visible:outline-none">Ya lo mandé</button>
                            <button type="button" data-mo-aun-no class="btn-secundario focus-visible:ring-2 focus-visible:ring-foco focus-visible:outline-none">Todavía no</button>
                        </div>
                    </div>

                    <!-- Aviso cuando el mensaje es largo y WhatsApp no lo aceptaría completo -->
                    <p data-mo-aviso-envio hidden role="status" class="mb-4 aviso"></p>

                    <!-- Cart items list -->
                    <div id="cartItemsContainer" class="mb-4">
                        <p data-mo-vacio class="py-6 text-center text-texto-suave text-sm">Tu pedido está vacío. Toca "Agregar" en un platillo.</p>
                        <div data-mo-lineas></div>
                        <template data-mo-plantilla-linea>
                            <div class="py-3 border-b divisor">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h4 class="text-sm font-bold text-texto-fuerte"><span data-mo-nombre></span> <span data-mo-variante class="text-xs text-texto-suave font-normal"></span></h4>
                                        <span class="text-xs font-semibold text-texto-medio" data-mo-subtotal></span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <button type="button" data-mo-menos class="btn-cant min-w-[44px] min-h-[44px]">
                                            <span aria-hidden="true">-</span>
                                        </button>
                                        <span class="text-sm font-extrabold text-texto-fuerte px-2 min-w-[24px] text-center"><span class="sr-only">Cantidad: </span><span data-mo-cantidad></span></span>
                                        <button type="button" data-mo-mas-uno class="btn-cant min-w-[44px] min-h-[44px]">
                                            <span aria-hidden="true">+</span>
                                        </button>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <input type="text" placeholder="Nota. Ej. sin picante" data-mo-nota class="campo" />
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Table & Notes Form -->
                    <div class="space-y-3 pt-3">
                        <div>
                            <label class="etiqueta-campo" for="inputMesa">
                                Mesa
                            </label>
                            <input
                                type="text"
                                id="inputMesa"
                                data-mo-mesa
                                value="{{ $mesa ?? '' }}"
                                placeholder='Ej. 4, o escribe "Para llevar"'
                                class="campo"
                            />
                        </div>

                        <div>
                            <label class="etiqueta-campo" for="inputNotasGenerales">
                                Nota para la cocina (opcional)
                            </label>
                            <textarea
                                id="inputNotasGenerales"
                                data-mo-cocina
                                rows="2"
                                placeholder="Ej. Sin cebolla, con cubiertos"
                                class="campo campo-area"
                            ></textarea>
                        </div>

                        <!-- Summary -->
                        <div class="flex items-center justify-between text-base font-extrabold text-texto-fuerte pt-2">
                            <span>Total</span>
                            <span id="modalCartTotal" data-mo-total>{{ \App\Services\FormatoMoneda::formatear(0, $restaurante->moneda, $idioma) }}</span>
                        </div>

                        <!-- WhatsApp Submit Button -->
                        <button
                            type="button"
                            id="btnEnviarWhatsApp"
                            data-mo-enviar
                            class="btn-whatsapp w-full min-h-[48px] py-3 px-4 text-sm shadow-lg focus-visible:ring-2 focus-visible:ring-foco focus-visible:ring-offset-2 focus-visible:outline-none"
                        >
                            <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766 0-3.187-2.59-5.771-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.312.045-.694.06-2.155-.544-1.631-.674-2.732-2.317-2.825-2.433-.092-.116-.694-.925-.694-1.765 0-.84.44-1.254.597-1.428.156-.173.344-.216.459-.216.115 0 .23.001.33.006.106.005.25-.04.39.376.144.348.49 1.198.533 1.285.043.087.072.188.014.304-.058.116-.087.188-.173.289l-.26.304c-.087.086-.177.18-.076.353.101.174.45 1.743 1.968 2.091.246.056.442.062.607-.021.165-.084.536-.622.68-.838.144-.217.288-.174.483-.102.195.072 1.241.586 1.455.693.214.108.358.161.41.25.053.089.053.518-.091.923z"/>
                            </svg>
                            <span>Mandar pedido por WhatsApp</span>
                        </button>
                    </div>
                </div>
            </div>
            </div>
        @endif
</x-layout.publico>
