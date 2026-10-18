<x-layout.publico
    titulo="MENUONL — Menú Digital Interactivo para Restaurantes"
    descripcion="Plataforma de menú digital interactivo con códigos QR de alta definición, pedidos directos a WhatsApp y gestión en tiempo real para restaurantes y cafeterías."
    html-class="scroll-smooth"
    class="min-h-screen flex flex-col selection:bg-amber-500 selection:text-white"
>
        <!-- Barra de Navegación Superior -->
        <header class="sticky top-0 z-40 w-full backdrop-blur-md bg-fondo/80 border-b border-borde transition-colors">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between">
                <a href="{{ url('/') }}" class="flex items-center gap-2.5 font-bold tracking-tight text-lg text-texto-fuerte focus:outline-none focus-visible:ring-2 focus-visible:ring-foco rounded-lg px-1.5 py-1" aria-label="MENUONL - Inicio">
                    <span class="w-8 h-8 rounded-xl bg-accion text-accion-texto flex items-center justify-center font-black text-sm shadow-sm">M</span>
                    <span class="text-base tracking-wide font-extrabold">MENUONL</span>
                </a>

                <nav class="flex items-center gap-3">
                    @if ($demoUrl)
                        <a href="{{ $demoUrl }}" class="hidden sm:inline-flex items-center text-sm font-medium text-texto-suave hover:text-texto-fuerte px-3 py-2 rounded-lg transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-foco">
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
            <section class="max-w-4xl mx-auto px-4 sm:px-6 pt-16 sm:pt-24 pb-16 text-center">
                <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-semibold bg-amber-500/10 text-amber-900 dark:bg-amber-400/10 dark:text-amber-300 border border-amber-500/20 mb-8">
                    <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse" aria-hidden="true"></span>
                    <span>Menú digital de nueva generación para restaurantes</span>
                </div>

                <h1 class="text-4xl sm:text-6xl font-extrabold tracking-tight text-texto-fuerte leading-[1.1] mb-6">
                    La carta digital que tus comensales recordarán.
                </h1>

                <p class="text-lg sm:text-xl text-texto-medio max-w-2xl mx-auto leading-relaxed mb-10">
                    Transforma cada mesa en una experiencia gastronómica interactiva. Menús digitales ultrarrápidos, códigos QR vectoriales de alta definición y pedidos directos a WhatsApp, sin descargas ni comisiones intermedias.
                </p>

                <div class="flex flex-col sm:flex-row items-center justify-center gap-4 max-w-md mx-auto">
                    @if ($demoUrl)
                        <a href="{{ $demoUrl }}" class="w-full sm:w-auto inline-flex items-center justify-center min-h-[48px] px-7 py-3 rounded-full text-base font-semibold bg-accion hover:bg-accion-hover text-accion-texto transition-all shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-foco">
                            Explorar menú de muestra
                        </a>
                    @endif
                    <a href="{{ url('/admin') }}" class="w-full sm:w-auto inline-flex items-center justify-center min-h-[48px] px-7 py-3 rounded-full text-base font-semibold bg-superficie-2 hover:bg-borde text-texto-fuerte transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-foco">
                        Acceder a la administración
                    </a>
                </div>
            </section>

            <!-- Tres Pilares de Diseño y Funcionalidad -->
            <section class="max-w-6xl mx-auto px-4 sm:px-6 py-12 sm:py-16">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 sm:gap-8">
                    <div class="p-8 rounded-3xl bg-superficie border border-borde shadow-sm flex flex-col justify-between">
                        <div>
                            <div class="w-12 h-12 rounded-2xl bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center mb-6">
                                <svg class="w-6 h-6" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>
                                </svg>
                            </div>
                            <h2 class="text-xl font-bold text-texto-fuerte mb-2">Códigos QR de Mesa</h2>
                            <p class="text-texto-suave text-sm leading-relaxed">
                                Generación nativa en formato vectorial SVG y ficha imprimible lista para doblar y colocar en atriles de mesa o acrílicos, con identificación automática de comensal.
                            </p>
                        </div>
                    </div>

                    <div class="p-8 rounded-3xl bg-superficie border border-borde shadow-sm flex flex-col justify-between">
                        <div>
                            <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center mb-6">
                                <svg class="w-6 h-6" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                            </div>
                            <h2 class="text-xl font-bold text-texto-fuerte mb-2">Velocidad y Claridad</h2>
                            <p class="text-texto-suave text-sm leading-relaxed">
                                Carga instantánea optimizada para teléfonos móviles, detección automática del idioma del cliente, control de horarios en vivo y filtros claros de alérgenos.
                            </p>
                        </div>
                    </div>

                    <div class="p-8 rounded-3xl bg-superficie border border-borde shadow-sm flex flex-col justify-between">
                        <div>
                            <div class="w-12 h-12 rounded-2xl bg-blue-500/10 text-blue-600 dark:text-blue-400 flex items-center justify-center mb-6">
                                <svg class="w-6 h-6" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                </svg>
                            </div>
                            <h2 class="text-xl font-bold text-texto-fuerte mb-2">Pedidos a WhatsApp</h2>
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
                <div class="flex items-center gap-6">
                    <a href="{{ url('/admin') }}" class="hover:text-texto-fuerte transition-colors">Administración</a>
                    @if ($demoUrl)
                        <a href="{{ $demoUrl }}" class="hover:text-texto-fuerte transition-colors">Menú de muestra</a>
                    @endif
                </div>
            </div>
        </footer>
</x-layout.publico>
