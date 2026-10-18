<x-layout.publico
    :titulo="$restaurante->traduccion($idioma)->nombre.' — Menú Digital'"
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
            <span class="text-acento font-bold" aria-hidden="true">✓</span>
            <span id="cartToastMessage">Agregado al pedido</span>
        </div>

        @php
            $puedePedir = $restaurante->pedidos_activos && $estaAbierto && filled($restaurante->whatsapp_pedidos);
            $diaHoy = (int) now($restaurante->zona_horaria)->dayOfWeek;
            $turnosHoy = $horarios->where('dia_semana', $diaHoy)->where('cerrado', false);
            $tienePortada = filled($restaurante->portada);
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
                <p class="hero-kicker">{{ filled($restaurante->eslogan) ? $restaurante->eslogan : 'Menú digital' }}</p>
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
                        <span class="badge-mesa" aria-label="Horario de atención hoy">
                            <span aria-hidden="true">🕒</span>
                            Hoy:
                            @foreach ($turnosHoy as $t)
                                {{ substr($t->abre, 0, 5) }} - {{ substr($t->cierra, 0, 5) }}@if (! $loop->last), @endif
                            @endforeach
                        </span>
                    @endif

                    @if ($mesa)
                        <span class="badge-mesa" aria-label="Ubicación asignada: Mesa {{ $mesa }}">
                            Mesa {{ $mesa }}
                        </span>
                    @endif
                </div>

                <div class="hero-acciones">
                    <a href="#carta" class="btn-cta focus-visible:ring-2 focus-visible:ring-foco focus-visible:outline-none">Ver la carta</a>
                    @if ($horarios->isNotEmpty())
                        <a href="#horarios" class="btn-cta-secundario focus-visible:ring-2 focus-visible:ring-foco focus-visible:outline-none">Horarios</a>
                    @endif

                    <!-- Language selector -->
                    @if (count($idiomasDisponibles) > 1)
                        <nav class="idiomas sm:ml-auto" aria-label="Selector de idioma">
                            @foreach ($idiomasDisponibles as $langOption)
                                <a
                                    href="{{ request()->fullUrlWithQuery(['lang' => $langOption]) }}"
                                    class="idioma-link min-h-[44px] min-w-[44px] focus-visible:ring-2 focus-visible:ring-foco focus-visible:outline-none"
                                    aria-current="{{ $idioma === $langOption ? 'page' : 'false' }}"
                                    aria-label="Cambiar idioma a {{ strtoupper($langOption) }}"
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
                        <svg class="w-5 h-5 shrink-0 text-acento" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span>
                            @if (! $restaurante->pedidos_activos)
                                Este restaurante no tiene pedidos en línea activos actualmente. Puedes consultar el menú digital libremente.
                            @elseif (! $estaAbierto)
                                El restaurante se encuentra cerrado en este momento. Los pedidos están pausados hasta su próxima apertura.
                            @elseif (! filled($restaurante->whatsapp_pedidos))
                                Servicio de pedidos temporalmente no disponible por WhatsApp.
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
                <span class="{{ $estaAbierto ? 'badge-open' : 'badge-closed' }} ml-auto">{{ $estaAbierto ? 'Abierto' : 'Cerrado' }}</span>
            </div>
        </div>

        <!-- Sticky Category Navigation Bar -->
        <nav class="sticky-nav" aria-label="Categorías del menú">
            <div class="nav-scroller contenedor flex items-center overflow-x-auto">
                @if ($destacados->isNotEmpty())
                    <a href="#destacados" class="nav-pill min-h-[44px] focus-visible:ring-2 focus-visible:ring-foco focus-visible:outline-none"><span aria-hidden="true" class="mr-1">⭐</span> Destacados</a>
                @endif

                @foreach ($categorias as $cat)
                    @php
                        $catNom = $cat->traduccion($idioma)?->nombre ?? $cat->traduccion()?->nombre ?? 'Categoría';
                    @endphp
                    <a href="#cat-{{ $cat->id }}" class="nav-pill min-h-[44px] focus-visible:ring-2 focus-visible:ring-foco focus-visible:outline-none">{{ $catNom }}</a>
                @endforeach

                @if ($horarios->isNotEmpty())
                    <a href="#horarios" class="nav-pill min-h-[44px] focus-visible:ring-2 focus-visible:ring-foco focus-visible:outline-none"><span aria-hidden="true" class="mr-1">🕒</span> Horarios</a>
                @endif

                @if ($contactos->isNotEmpty())
                    <a href="#contacto" class="nav-pill min-h-[44px] focus-visible:ring-2 focus-visible:ring-foco focus-visible:outline-none"><span aria-hidden="true" class="mr-1">📞</span> Contacto</a>
                @endif
            </div>
        </nav>

        <!-- Main Content -->
        <main id="carta" class="contenedor py-8">

            <!-- Featured Section -->
            @if ($destacados->isNotEmpty())
                <section id="destacados" class="seccion">
                    <h2 class="seccion-titulo">Platillos Recomendados</h2>

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
                        <p class="text-texto-suave text-sm italic mt-4">No hay platillos disponibles en esta categoría.</p>
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
                    <h2 class="seccion-titulo">Horarios de Atención</h2>
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
                                        @foreach ($turnos->where('cerrado', false) as $t)
                                            <span class="inline-block ml-1">
                                                {{ substr($t->abre, 0, 5) }} - {{ substr($t->cierra, 0, 5) }}
                                            </span>
                                        @endforeach
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
                    <h2 class="seccion-titulo">Contacto y Ubicación</h2>
                    <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                        @foreach ($contactos as $contacto)
                            @php
                                $href = match ($contacto->tipo) {
                                    'telefono' => str_starts_with($contacto->valor, 'tel:') ? $contacto->valor : 'tel:' . preg_replace('/[^0-9+]/', '', $contacto->valor),
                                    'whatsapp' => 'https://wa.me/' . preg_replace('/[^0-9]/', '', $contacto->valor),
                                    'email' => str_starts_with($contacto->valor, 'mailto:') ? $contacto->valor : 'mailto:' . $contacto->valor,
                                    'direccion' => str_starts_with($contacto->valor, 'http') ? $contacto->valor : 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($contacto->valor),
                                    default => (str_starts_with($contacto->valor, 'http://') || str_starts_with($contacto->valor, 'https://')) ? $contacto->valor : 'https://' . $contacto->valor,
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
            <p>{{ $restaurante->traduccion($idioma)->nombre }} · Menú digital con MENUONL</p>
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

        <!-- Floating Cart Bar (Enabled only if $puedePedir) -->
        @if ($puedePedir)
            <button
                type="button"
                id="floatingCartBar"
                class="floating-cart-bar w-full focus-visible:ring-2 focus-visible:ring-foco focus-visible:ring-offset-2 focus-visible:outline-none"
                style="display: none;"
                onclick="openCartModal()"
                aria-haspopup="dialog"
                aria-expanded="false"
                aria-controls="cartModalBackdrop"
                aria-label="Ver mi pedido"
            >
                <div class="flex items-center gap-3">
                    <span id="cartCountBadge" class="bg-acento-texto text-acento font-extrabold text-xs px-2.5 py-1 rounded-full min-w-[24px] text-center">
                        0
                    </span>
                    <span class="font-bold text-sm">Ver mi pedido</span>
                </div>
                <div class="font-extrabold text-base">
                    <span id="cartTotalSummary">{{ \App\Services\FormatoMoneda::formatear(0, $restaurante->moneda, $idioma) }}</span>
                </div>
            </button>

            <!-- Cart Drawer Modal -->
            <div
                id="cartModalBackdrop"
                class="cart-modal-backdrop"
                onclick="closeCartModal(event)"
                @hoja-cerrar="closeCartModal(null, true)"
                role="dialog"
                aria-modal="true"
                aria-labelledby="cartDrawerTitle"
            >
                <div class="cart-drawer" onclick="event.stopPropagation()" x-data="hoja" :style="estilo">
                    <div class="hoja-cabecera flex items-center justify-between pb-3 border-b divisor mb-4" @pointerdown="empezar" @pointermove="mover" @pointerup="soltar" @pointercancel="soltar">
                        <span class="hoja-asa" aria-hidden="true"></span>
                        <div class="flex items-center gap-2">
                            <span class="text-xl" aria-hidden="true">🛒</span>
                            <h3 id="cartDrawerTitle" class="text-lg font-bold text-texto-fuerte" style="font-family: var(--mo-fuente-titulo)">Tu Pedido</h3>
                        </div>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                id="btnVaciarCarrito"
                                onclick="cartConfirmarVaciar()"
                                class="btn-texto focus-visible:ring-2 focus-visible:ring-foco focus-visible:outline-none"
                                aria-label="Vaciar todo el pedido"
                            >
                                Vaciar pedido
                            </button>
                            <button
                                type="button"
                                id="btnCloseCartModal"
                                onclick="closeCartModal()"
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
                    <div id="vaciarConfirmBanner" class="hidden mb-4 aviso aviso-peligro flex-col">
                        <p class="font-semibold mb-2">¿Seguro que deseas vaciar todos los platillos de tu pedido?</p>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                onclick="cartEjecutarVaciar()"
                                class="btn-peligro focus-visible:ring-2 focus-visible:ring-foco focus-visible:outline-none"
                            >
                                Sí, vaciar
                            </button>
                            <button
                                type="button"
                                onclick="cartCancelarVaciar()"
                                class="btn-neutro focus-visible:ring-2 focus-visible:ring-foco focus-visible:outline-none"
                            >
                                Cancelar
                            </button>
                        </div>
                    </div>

                    <!-- Confirmation shown after handing the order to WhatsApp -->
                    <div id="pedidoEnviadoBanner" class="hidden mb-4 aviso aviso-exito flex-col" role="status" tabindex="-1">
                        <p class="font-semibold mb-2">Abrimos WhatsApp con tu pedido. Envíalo desde ahí para que llegue al restaurante.</p>
                        <div class="flex flex-wrap items-center gap-2">
                            <button type="button" onclick="cartPedidoEnviado(true)" class="btn-primario focus-visible:ring-2 focus-visible:ring-foco focus-visible:outline-none">Ya lo envié, vaciar pedido</button>
                            <button type="button" onclick="cartPedidoEnviado(false)" class="btn-neutro focus-visible:ring-2 focus-visible:ring-foco focus-visible:outline-none">Conservar pedido</button>
                        </div>
                    </div>

                    <!-- Cart items list -->
                    <div id="cartItemsContainer" class="mb-4">
                        <!-- Rendered dynamically by JS -->
                    </div>

                    <!-- Table & Notes Form -->
                    <div class="space-y-3 pt-3">
                        <div>
                            <label class="etiqueta-campo" for="inputMesa">
                                Mesa / Ubicación:
                            </label>
                            <input
                                type="text"
                                id="inputMesa"
                                value="{{ $mesa ?? '' }}"
                                placeholder="Ej. Mesa 4 o Para llevar"
                                class="campo"
                            />
                        </div>

                        <div>
                            <label class="etiqueta-campo" for="inputNotasGenerales">
                                Nota general para la cocina:
                            </label>
                            <textarea
                                id="inputNotasGenerales"
                                rows="2"
                                placeholder="Indicaciones especiales, cubiertos, etc."
                                class="campo"
                            ></textarea>
                        </div>

                        <!-- Summary -->
                        <div class="flex items-center justify-between text-base font-extrabold text-texto-fuerte pt-2">
                            <span>Total:</span>
                            <span id="modalCartTotal">{{ \App\Services\FormatoMoneda::formatear(0, $restaurante->moneda, $idioma) }}</span>
                        </div>

                        <!-- WhatsApp Submit Button -->
                        <button
                            type="button"
                            id="btnEnviarWhatsApp"
                            onclick="enviarPedidoWhatsApp()"
                            class="btn-whatsapp w-full min-h-[48px] py-3 px-4 text-sm shadow-lg focus-visible:ring-2 focus-visible:ring-foco focus-visible:ring-offset-2 focus-visible:outline-none"
                        >
                            <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766 0-3.187-2.59-5.771-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.312.045-.694.06-2.155-.544-1.631-.674-2.732-2.317-2.825-2.433-.092-.116-.694-.925-.694-1.765 0-.84.44-1.254.597-1.428.156-.173.344-.216.459-.216.115 0 .23.001.33.006.106.005.25-.04.39.376.144.348.49 1.198.533 1.285.043.087.072.188.014.304-.058.116-.087.188-.173.289l-.26.304c-.087.086-.177.18-.076.353.101.174.45 1.743 1.968 2.091.246.056.442.062.607-.021.165-.084.536-.622.68-.838.144-.217.288-.174.483-.102.195.072 1.241.586 1.455.693.214.108.358.161.41.25.053.089.053.518-.091.923z"/>
                            </svg>
                            <span>Enviar Pedido por WhatsApp</span>
                        </button>
                    </div>
                </div>
            </div>
        @endif

        <!-- Client-Side Cart Script (Isolated under localStorage menuonl_cart_{slug}) -->
        <script>
            (function() {
                const NOMBRE = @js($restaurante->nombre);
                const CART_KEY = @js('menuonl_cart_'.$restaurante->slug);
                const MONEDA = @js($restaurante->moneda);
                const WHATSAPP_NUM = @js(preg_replace('/[^0-9]/', '', (string) $restaurante->whatsapp_pedidos));
                const PUEDE_PEDIR = @js((bool) $puedePedir);
                const IDIOMA = @js(\App\Services\FormatoMoneda::locale($idioma, $restaurante->moneda));
                let formateador;
                try {
                    formateador = new Intl.NumberFormat(IDIOMA, { style: 'currency', currency: MONEDA });
                } catch (e) {
                    formateador = null;
                }
                function fmt(valor) {
                    if (!formateador) return Number(valor).toFixed(2) + ' ' + escapeHtml(MONEDA);
                    const partes = formateador.formatToParts(valor);
                    const simboloAmbiguo = partes.some(p => p.type === 'currency' && p.value === '$');
                    return formateador.format(valor) + (simboloAmbiguo ? ' ' + escapeHtml(MONEDA) : '');
                }
                let toastTimeout = null;

                function escapeHtml(str) {
                    if (!str) return '';
                    return String(str)
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;')
                        .replace(/'/g, '&#39;');
                }

                function mostrarToast(mensaje) {
                    const toast = document.getElementById('cartToast');
                    const toastMsg = document.getElementById('cartToastMessage');
                    if (!toast || !toastMsg) return;
                    toastMsg.textContent = mensaje;
                    toast.classList.add('visible');
                    if (toastTimeout) clearTimeout(toastTimeout);
                    toastTimeout = setTimeout(function() {
                        toast.classList.remove('visible');
                    }, 2500);
                }
                window.mostrarNotificacionCarrito = mostrarToast;

                function loadCart() {
                    try {
                        const raw = localStorage.getItem(CART_KEY);
                        return raw ? JSON.parse(raw) : [];
                    } catch (e) {
                        return [];
                    }
                }

                function saveCart(cart) {
                    try {
                        localStorage.setItem(CART_KEY, JSON.stringify(cart));
                    } catch (e) {}
                    renderCart();
                }

                window.cartAddItem = function(item) {
                    if (!PUEDE_PEDIR) return;
                    const cart = loadCart();
                    const existingIndex = cart.findIndex(c => c.producto_id === item.producto_id && c.variante_id === item.variante_id);

                    if (existingIndex > -1) {
                        cart[existingIndex].cantidad += (item.cantidad || 1);
                    } else {
                        cart.push({
                            producto_id: item.producto_id,
                            variante_id: item.variante_id || null,
                            nombre: item.nombre,
                            variante_nombre: item.variante_nombre || null,
                            precio: parseFloat(item.precio),
                            cantidad: item.cantidad || 1,
                            notas: item.notas || ''
                        });
                    }
                    saveCart(cart);
                    mostrarToast('Agregaste ' + item.nombre + (item.variante_nombre ? ' (' + item.variante_nombre + ')' : '') + ' al pedido');
                };

                window.cartUpdateQty = function(index, delta) {
                    const cart = loadCart();
                    if (!cart[index]) return;
                    cart[index].cantidad += delta;
                    if (cart[index].cantidad <= 0) {
                        cart.splice(index, 1);
                    }
                    saveCart(cart);
                };

                window.cartUpdateItemNotes = function(index, notes) {
                    const cart = loadCart();
                    if (!cart[index]) return;
                    cart[index].notas = notes;
                    try {
                        localStorage.setItem(CART_KEY, JSON.stringify(cart));
                    } catch (e) {}
                };

                window.cartConfirmarVaciar = function() {
                    const banner = document.getElementById('vaciarConfirmBanner');
                    if (banner) banner.classList.remove('hidden');
                };

                window.cartCancelarVaciar = function() {
                    const banner = document.getElementById('vaciarConfirmBanner');
                    if (banner) banner.classList.add('hidden');
                };

                window.cartEjecutarVaciar = function() {
                    saveCart([]);
                    cartCancelarVaciar();
                    mostrarToast('Se vació tu pedido.');
                };

                window.openCartModal = function() {
                    const modal = document.getElementById('cartModalBackdrop');
                    if (modal) modal.classList.remove('cerrando');
                    if (modal) modal.classList.add('open');
                    const bar = document.getElementById('floatingCartBar');
                    if (bar) bar.setAttribute('aria-expanded', 'true');
                    document.body.style.overflow = 'hidden';
                    const closeBtn = document.getElementById('btnCloseCartModal');
                    if (closeBtn) closeBtn.focus();
                };

                window.closeCartModal = function(e, inmediato) {
                    if (e && e.target && e.target !== document.getElementById('cartModalBackdrop')) {
                        return;
                    }
                    const modal = document.getElementById('cartModalBackdrop');
                    const bar = document.getElementById('floatingCartBar');
                    const terminar = function() {
                        if (modal) modal.classList.remove('open', 'cerrando');
                    };
                    if (modal && modal.classList.contains('open') && !inmediato && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                        modal.classList.add('cerrando');
                        setTimeout(terminar, 200);
                    } else {
                        terminar();
                    }
                    if (bar) bar.setAttribute('aria-expanded', 'false');
                    document.body.style.overflow = '';
                    cartCancelarVaciar();
                    const enviado = document.getElementById('pedidoEnviadoBanner');
                    if (enviado) enviado.classList.add('hidden');
                    if (bar && bar.style.display !== 'none' && !e) {
                        bar.focus();
                    }
                };

                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Tab') {
                        const abierto = document.getElementById('cartModalBackdrop');
                        if (abierto && abierto.classList.contains('open')) {
                            const foco = Array.from(abierto.querySelectorAll('button, input, textarea'))
                                .filter(el => !el.disabled && el.offsetParent !== null);
                            if (foco.length) {
                                const primero = foco[0];
                                const ultimo = foco[foco.length - 1];
                                if (e.shiftKey && (document.activeElement === primero || !abierto.contains(document.activeElement))) {
                                    e.preventDefault();
                                    ultimo.focus();
                                } else if (!e.shiftKey && (document.activeElement === ultimo || !abierto.contains(document.activeElement))) {
                                    e.preventDefault();
                                    primero.focus();
                                }
                            }
                        }
                    }
                    if (e.key === 'Escape' || e.key === 'Esc') {
                        const modal = document.getElementById('cartModalBackdrop');
                        if (modal && modal.classList.contains('open')) {
                            closeCartModal();
                        }
                    }
                });

                window.enviarPedidoWhatsApp = function() {
                    const cart = loadCart();
                    if (cart.length === 0) {
                        mostrarToast('Tu carrito está vacío.');
                        return;
                    }

                    const mesaInput = document.getElementById('inputMesa');
                    const mesaVal = mesaInput ? mesaInput.value.trim() : '';
                    const mesaText = mesaVal ? mesaVal : 'Para llevar / Sin mesa';

                    const notasGeneralesInput = document.getElementById('inputNotasGenerales');
                    const notasGenerales = notasGeneralesInput ? notasGeneralesInput.value.trim() : '';

                    let total = 0;
                    let itemsText = '';

                    cart.forEach(item => {
                        const itemSub = item.precio * item.cantidad;
                        total += itemSub;
                        const vName = item.variante_nombre ? ` (${item.variante_nombre})` : '';
                        itemsText += `• ${item.cantidad}x ${item.nombre}${vName} - ${fmt(itemSub)}\n`;
                        if (item.notas && item.notas.trim()) {
                            itemsText += `  _Nota: ${item.notas.trim()}_\n`;
                        }
                    });

                    let msg = `¡Hola! Quiero hacer el siguiente pedido desde el menú digital de *${NOMBRE}*:\n\n`;
                    msg += `*Ubicación/Mesa:* ${mesaText}\n\n`;
                    msg += `*Detalle del pedido:*\n${itemsText}\n`;
                    if (notasGenerales) {
                        msg += `*Indicaciones:* ${notasGenerales}\n\n`;
                    }
                    msg += `*Total estimado:* ${fmt(total)}`;

                    const url = `https://wa.me/${WHATSAPP_NUM}?text=${encodeURIComponent(msg)}`;
                    window.open(url, '_blank', 'noopener,noreferrer');

                    const banner = document.getElementById('pedidoEnviadoBanner');
                    if (banner) {
                        banner.classList.remove('hidden');
                        banner.focus();
                    }
                };

                window.cartPedidoEnviado = function(vaciar) {
                    const banner = document.getElementById('pedidoEnviadoBanner');
                    if (banner) banner.classList.add('hidden');
                    if (vaciar) {
                        saveCart([]);
                        const nota = document.getElementById('inputNotasGenerales');
                        if (nota) nota.value = '';
                        closeCartModal();
                        mostrarToast('Pedido vaciado. ¡Buen provecho!');
                    }
                };

                function renderCart() {
                    if (!PUEDE_PEDIR) return;
                    const cart = loadCart();
                    const bar = document.getElementById('floatingCartBar');
                    const badge = document.getElementById('cartCountBadge');
                    const barTotal = document.getElementById('cartTotalSummary');
                    const modalTotal = document.getElementById('modalCartTotal');
                    const itemsContainer = document.getElementById('cartItemsContainer');
                    const btnVaciar = document.getElementById('btnVaciarCarrito');
                    const btnEnviar = document.getElementById('btnEnviarWhatsApp');

                    let totalQty = 0;
                    let totalPrice = 0;

                    cart.forEach(item => {
                        totalQty += item.cantidad;
                        totalPrice += (item.precio * item.cantidad);
                    });

                    if (badge) badge.textContent = totalQty;
                    if (barTotal) barTotal.textContent = fmt(totalPrice);
                    if (modalTotal) modalTotal.textContent = fmt(totalPrice);

                    if (bar) {
                        bar.style.display = totalQty > 0 ? 'flex' : 'none';
                        bar.setAttribute('aria-label', `Ver mi pedido: ${totalQty} ${totalQty === 1 ? 'artículo' : 'artículos'}, total ${fmt(totalPrice)}`);
                    }

                    if (btnVaciar) {
                        btnVaciar.style.display = totalQty > 0 ? 'inline-block' : 'none';
                    }

                    if (btnEnviar) {
                        btnEnviar.disabled = (totalQty === 0);
                        btnEnviar.setAttribute('aria-disabled', totalQty === 0 ? 'true' : 'false');
                    }

                    if (itemsContainer) {
                        const activo = document.activeElement;
                        const claveFoco = activo && itemsContainer.contains(activo) ? activo.dataset.qty : null;
                        if (cart.length === 0) {
                            itemsContainer.innerHTML = '<p class="py-6 text-center text-texto-suave text-sm">Tu carrito está vacío.</p>';
                        } else {
                            let html = '';
                            cart.forEach((item, idx) => {
                                const cantidad = parseInt(item.cantidad, 10) || 0;
                                const precio = Number(item.precio) || 0;
                                const vName = item.variante_nombre ? `<span class="text-xs text-texto-suave font-normal">(${escapeHtml(item.variante_nombre)})</span>` : '';
                                const itemNombreEsc = escapeHtml(item.nombre);
                                html += `
                                    <div class="py-3 border-b divisor">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <h4 class="text-sm font-bold text-texto-fuerte">${itemNombreEsc} ${vName}</h4>
                                                <span class="text-xs font-semibold text-texto-medio">${fmt(precio * cantidad)}</span>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <button
                                                    type="button"
                                                    onclick="cartUpdateQty(${idx}, -1)"
                                                    data-qty="${idx}:-1"
                                                    aria-label="Disminuir cantidad de ${itemNombreEsc}"
                                                    class="btn-cant min-w-[44px] min-h-[44px]"
                                                >
                                                    <span aria-hidden="true">-</span>
                                                </button>
                                                <span class="text-sm font-extrabold text-texto-fuerte px-2 min-w-[24px] text-center"><span class="sr-only">Cantidad: </span>${cantidad}</span>
                                                <button
                                                    type="button"
                                                    onclick="cartUpdateQty(${idx}, 1)"
                                                    data-qty="${idx}:1"
                                                    aria-label="Aumentar cantidad de ${itemNombreEsc}"
                                                    class="btn-cant min-w-[44px] min-h-[44px]"
                                                >
                                                    <span aria-hidden="true">+</span>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="mt-2">
                                            <input
                                                type="text"
                                                placeholder="Nota (ej. sin picante)"
                                                aria-label="Instrucciones especiales para ${itemNombreEsc}"
                                                value="${item.notas ? escapeHtml(item.notas) : ''}"
                                                oninput="cartUpdateItemNotes(${idx}, this.value)"
                                                onchange="cartUpdateItemNotes(${idx}, this.value)"
                                                class="campo"
                                            />
                                        </div>
                                    </div>
                                `;
                            });
                            itemsContainer.innerHTML = html;
                        }
                        if (claveFoco) {
                            const [i, d] = claveFoco.split(':');
                            const cercano = Math.max(0, Math.min(Number(i), cart.length - 1)) + ':' + d;
                            const destino = itemsContainer.querySelector('[data-qty="' + claveFoco + '"]')
                                || itemsContainer.querySelector('[data-qty="' + cercano + '"]')
                                || document.getElementById('btnCloseCartModal');
                            if (destino) destino.focus();
                        }
                    }
                }

                document.addEventListener('DOMContentLoaded', renderCart);

                document.addEventListener('DOMContentLoaded', function() {
                    if (!('IntersectionObserver' in window)) return;
                    const pills = Array.from(document.querySelectorAll('.sticky-nav a[href^="#"]'));
                    const porId = new Map(pills.map(a => [a.getAttribute('href').slice(1), a]));
                    const visibles = new Set();
                    const marcar = function() {
                        const primero = Array.from(porId.keys()).find(id => visibles.has(id));
                        pills.forEach(a => a.removeAttribute('aria-current'));
                        if (primero) {
                            const a = porId.get(primero);
                            a.setAttribute('aria-current', 'true');
                            const scroller = a.closest('.nav-scroller');
                            if (scroller) {
                                scroller.scrollTo({ left: Math.max(0, a.offsetLeft - (scroller.clientWidth / 2) + (a.offsetWidth / 2)), behavior: 'smooth' });
                            }
                        }
                    };
                    const obs = new IntersectionObserver(function(entradas) {
                        entradas.forEach(en => en.isIntersecting ? visibles.add(en.target.id) : visibles.delete(en.target.id));
                        marcar();
                    }, { rootMargin: '-20% 0px -70% 0px' });
                    porId.forEach((a, id) => {
                        const sec = document.getElementById(id);
                        if (sec) obs.observe(sec);
                    });
                });
            })();
        </script>
</x-layout.publico>
