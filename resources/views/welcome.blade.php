<x-layout.publico
    titulo="MENUONL — Menú Digital Interactivo para Restaurantes"
    descripcion="Plataforma de menú digital interactivo con códigos QR de alta definición, pedidos directos a WhatsApp y gestión en tiempo real para restaurantes y cafeterías."
    html-class="scroll-smooth"
    tema="calido"
    class="min-h-screen flex flex-col bg-fondo selection:bg-amber-600 selection:text-white"
>
        <!-- Barra de Navegación Superior -->
        <header class="sticky top-0 z-40 w-full backdrop-blur-md bg-fondo/90 border-b border-borde transition-colors">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between">
                <a href="{{ url('/') }}" class="inline-flex items-center gap-2.5 min-h-[44px] font-bold tracking-tight text-lg text-texto-fuerte focus:outline-none focus-visible:ring-2 focus-visible:ring-foco rounded-lg px-2 py-1" aria-label="MENUONL - Inicio">
                    <span class="w-8 h-8 rounded-xl bg-accion text-accion-texto flex items-center justify-center font-black text-sm shadow-sm">M</span>
                    <span class="text-base tracking-wide font-extrabold">MENUONL</span>
                </a>

                <nav class="flex items-center gap-2 sm:gap-3" aria-label="Navegación principal">
                    @if ($demoUrl)
                        <a href="{{ $demoUrl }}" class="hidden sm:inline-flex items-center min-h-[44px] text-sm font-medium text-texto-suave hover:text-texto-fuerte px-3.5 py-2 rounded-lg transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-foco">
                            Ver menú de muestra
                        </a>
                    @endif
                    <a href="{{ url('/admin') }}" class="inline-flex items-center justify-center min-h-[44px] px-5 py-2 rounded-full text-sm font-semibold bg-accion hover:bg-accion-hover text-accion-texto transition-all shadow-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-foco focus-visible:ring-offset-2">
                        Entrar al panel
                    </a>
                </nav>
            </div>
        </header>

        <!-- Sección Principal (Hero) -->
        <main class="flex-1">
            <section class="max-w-6xl mx-auto px-4 sm:px-6 pt-12 sm:pt-20 pb-14">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-12 items-center">
                    <div class="lg:col-span-7 text-center lg:text-left">
                        <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-semibold bg-amber-500/10 text-amber-900 border border-amber-600/20 mb-6">
                            <span class="w-2 h-2 rounded-full bg-amber-600 animate-pulse" aria-hidden="true"></span>
                            <span>Menú digital de nueva generación para restaurantes</span>
                        </div>

                        <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight text-texto-fuerte leading-[1.08] mb-6" style="font-family: var(--mo-fuente-titulo)">
                            La carta digital que tus comensales recordarán.
                        </h1>

                        <p class="text-lg sm:text-xl text-texto-medio max-w-2xl mx-auto lg:mx-0 leading-relaxed mb-8">
                            Transforma cada mesa en una experiencia gastronómica interactiva. Menús digitales ultrarrápidos, códigos QR vectoriales de alta definición y pedidos directos a WhatsApp, sin descargas ni comisiones intermedias.
                        </p>

                        <div class="flex flex-col sm:flex-row items-center justify-center lg:justify-start gap-3.5 max-w-md mx-auto lg:mx-0">
                            @if ($demoUrl)
                                <a href="{{ $demoUrl }}" class="w-full sm:w-auto inline-flex items-center justify-center min-h-[48px] px-7 py-3 rounded-full text-base font-semibold bg-accion hover:bg-accion-hover text-accion-texto transition-all shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-foco">
                                    Explorar menú de muestra
                                </a>
                            @endif
                            <a href="{{ url('/admin') }}" class="w-full sm:w-auto inline-flex items-center justify-center min-h-[48px] px-7 py-3 rounded-full text-base font-semibold bg-superficie-2 hover:bg-borde text-texto-fuerte border border-borde transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-foco">
                                Acceder a la administración
                            </a>
                        </div>

                        <!-- Selector visual de temas editoriales incluidos -->
                        <div class="mt-10 pt-8 border-t border-borde">
                            <p class="text-xs font-bold uppercase tracking-widest text-texto-suave mb-3">
                                4 personalidades visuales listas para tu restaurante
                            </p>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 text-left">
                                <div class="p-3 rounded-xl bg-superficie border border-borde">
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="w-3 h-3 rounded-full bg-[#a8541c]" aria-hidden="true"></span>
                                        <span class="text-xs font-bold text-texto-fuerte">Cálido</span>
                                    </div>
                                    <p class="text-[11px] text-texto-suave">Crema, serifa y terracota</p>
                                </div>
                                <div class="p-3 rounded-xl bg-[#17171e] border border-[#2e2e3a] text-[#fbf8f1]">
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="w-3 h-3 rounded-full bg-[#c9a24a]" aria-hidden="true"></span>
                                        <span class="text-xs font-bold">Editorial oscuro</span>
                                    </div>
                                    <p class="text-[11px] text-[#a39d90]">Contraste nocturno y dorado</p>
                                </div>
                                <div class="p-3 rounded-xl bg-[#f8f1e1] border border-[#cdbd9a] text-[#2a1c10]">
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="w-3 h-3 rounded-full bg-[#55672f]" aria-hidden="true"></span>
                                        <span class="text-xs font-bold">Rústico</span>
                                    </div>
                                    <p class="text-[11px] text-[#6e5a43]">Papel artesanal y olivo</p>
                                </div>
                                <div class="p-3 rounded-xl bg-white border border-slate-200 text-slate-900">
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="w-3 h-3 rounded-full bg-slate-900" aria-hidden="true"></span>
                                        <span class="text-xs font-bold">Clásico</span>
                                    </div>
                                    <p class="text-[11px] text-slate-500">Limpio, neutro y directo</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Vista previa editorial de la carta digital -->
                    <div class="lg:col-span-5" aria-hidden="true">
                        <div class="rounded-3xl bg-superficie border border-borde shadow-xl overflow-hidden max-w-md mx-auto">
                            <div class="p-5 bg-superficie-2/70 border-b border-borde">
                                <div class="flex items-center justify-between gap-2 mb-3">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-abierto-fondo text-abierto-texto">
                                        <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                                        Abierto ahora
                                    </span>
                                    <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-mesa-fondo text-mesa-texto">
                                        Mesa 4
                                    </span>
                                </div>
                                <p class="text-[11px] font-bold uppercase tracking-widest text-acento">Cocina de origen y brasa</p>
                                <p class="text-2xl font-bold text-texto-fuerte mt-0.5" style="font-family: var(--mo-fuente-titulo)">Casa Lumbre</p>
                                <div class="flex items-center gap-4 mt-4 pt-3 border-t border-borde text-xs font-semibold">
                                    <span class="text-texto-fuerte border-b-2 border-acento pb-1">⭐ Destacados</span>
                                    <span class="text-texto-suave pb-1">Entradas</span>
                                    <span class="text-texto-suave pb-1">Fuertes</span>
                                    <span class="text-texto-suave pb-1">Postres</span>
                                </div>
                            </div>

                            <div class="p-5 space-y-4">
                                <div class="p-4 rounded-2xl bg-superficie border border-borde shadow-sm">
                                    <div class="text-[11px] font-bold uppercase tracking-wider text-acento mb-1">⭐ Recomendado</div>
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="font-bold text-texto-fuerte text-base" style="font-family: var(--mo-fuente-titulo)">Pulpo a las Brasas</p>
                                            <p class="text-sm font-bold text-acento mt-0.5">$285.00 MXN</p>
                                            <p class="text-xs text-texto-suave mt-1 leading-relaxed">Adobo de chiles secos, puré rústico de papa con aceite de oliva y ceniza de totomoxtle.</p>
                                            <div class="flex flex-wrap gap-1.5 mt-2">
                                                <span class="px-2 py-0.5 rounded-full border border-borde text-[11px] font-semibold text-texto-medio">Sin gluten</span>
                                                <span class="px-2 py-0.5 rounded-full border border-borde text-[11px] font-semibold text-texto-medio">Picante</span>
                                            </div>
                                        </div>
                                        <div class="w-16 h-16 rounded-xl bg-superficie-2 border border-borde shrink-0 flex items-center justify-center text-2xl">
                                            🐙
                                        </div>
                                    </div>
                                </div>

                                <div class="pt-1 flex items-center justify-between gap-3 border-t border-borde">
                                    <div>
                                        <p class="font-bold text-texto-fuerte text-sm" style="font-family: var(--mo-fuente-titulo)">Tostada de Atún Fresco</p>
                                        <p class="text-xs text-texto-suave mt-0.5">Aguacate tatemado, poro crujiente y ponzu cítrico.</p>
                                    </div>
                                    <span class="text-sm font-bold text-acento shrink-0">$145.00 MXN</span>
                                </div>

                                <div class="rounded-xl bg-accion text-accion-texto px-4 py-3 flex items-center justify-between text-xs font-bold shadow-sm">
                                    <span class="inline-flex items-center gap-2">
                                        <span class="px-2 py-0.5 rounded-full bg-accion-texto text-accion font-extrabold">2</span>
                                        <span>Pedido listo para WhatsApp</span>
                                    </span>
                                    <span>$430.00 MXN</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Tres Pilares de Diseño y Funcionalidad -->
            <section class="max-w-6xl mx-auto px-4 sm:px-6 py-12 sm:py-16">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 sm:gap-8">
                    <div class="p-8 rounded-3xl bg-superficie border border-borde shadow-sm flex flex-col justify-between">
                        <div>
                            <div class="w-12 h-12 rounded-2xl bg-amber-500/10 text-amber-700 flex items-center justify-center mb-6">
                                <svg class="w-6 h-6" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>
                                </svg>
                            </div>
                            <h2 class="text-xl font-bold text-texto-fuerte mb-2" style="font-family: var(--mo-fuente-titulo)">Códigos QR de Mesa</h2>
                            <p class="text-texto-suave text-sm leading-relaxed">
                                Generación nativa en formato vectorial SVG y ficha imprimible lista para doblar y colocar en atriles de mesa o acrílicos, con identificación automática de comensal.
                            </p>
                        </div>
                    </div>

                    <div class="p-8 rounded-3xl bg-superficie border border-borde shadow-sm flex flex-col justify-between">
                        <div>
                            <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 text-emerald-700 flex items-center justify-center mb-6">
                                <svg class="w-6 h-6" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                            </div>
                            <h2 class="text-xl font-bold text-texto-fuerte mb-2" style="font-family: var(--mo-fuente-titulo)">Velocidad y Claridad</h2>
                            <p class="text-texto-suave text-sm leading-relaxed">
                                Carga instantánea optimizada para teléfonos móviles, detección automática del idioma del cliente, control de horarios en vivo y filtros claros de alérgenos.
                            </p>
                        </div>
                    </div>

                    <div class="p-8 rounded-3xl bg-superficie border border-borde shadow-sm flex flex-col justify-between">
                        <div>
                            <div class="w-12 h-12 rounded-2xl bg-blue-500/10 text-blue-700 flex items-center justify-center mb-6">
                                <svg class="w-6 h-6" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                </svg>
                            </div>
                            <h2 class="text-xl font-bold text-texto-fuerte mb-2" style="font-family: var(--mo-fuente-titulo)">Pedidos a WhatsApp</h2>
                            <p class="text-texto-suave text-sm leading-relaxed">
                                Carrito integrado en el navegador para armar órdenes personalizadas con variantes y notas por platillo, enviándolas desglosadas con un solo toque.
                            </p>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <!-- Pie de Página Sobrio y Accesible -->
        <footer class="w-full border-t border-borde py-8 text-center text-xs text-texto-suave">
            <div class="max-w-6xl mx-auto px-4 flex flex-col sm:flex-row items-center justify-between gap-4">
                <p>&copy; {{ date('Y') }} MENUONL. Todos los derechos reservados. Diseñado para la hostelería moderna.</p>
                <div class="flex items-center gap-4">
                    <a href="{{ url('/admin') }}" class="inline-flex items-center min-h-[44px] px-2 hover:text-texto-fuerte transition-colors rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-foco">Administración</a>
                    @if ($demoUrl)
                        <a href="{{ $demoUrl }}" class="inline-flex items-center min-h-[44px] px-2 hover:text-texto-fuerte transition-colors rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-foco">Menú de muestra</a>
                    @endif
                </div>
            </div>
        </footer>
</x-layout.publico>
