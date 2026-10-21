<x-layout.publico
    titulo="MENUONL — Menú Digital Interactivo para Restaurantes"
    descripcion="Plataforma de menú digital interactivo con códigos QR de alta definición, pedidos directos a WhatsApp, reservas en línea y gestión en tiempo real para restaurantes y cafeterías."
    html-class="scroll-smooth"
    tema="calido"
    class="min-h-screen flex flex-col bg-fondo selection:bg-acento selection:text-acento-texto"
>
    @php
        $pasos = [
            ['n' => '1', 'titulo' => 'Sube tu menú', 'texto' => 'Captura categorías, platillos, precios y fotos desde el panel. Funciona igual de bien en el celular.'],
            ['n' => '2', 'titulo' => 'Imprime tu QR', 'texto' => 'Genera el código de cada mesa en SVG o PNG, con ficha imprimible lista para atriles y acrílicos.'],
            ['n' => '3', 'titulo' => 'Tus comensales piden', 'texto' => 'Escanean, ven la carta al instante y envían su pedido o su reserva sin instalar nada.'],
        ];
        $beneficios = [
            ['titulo' => 'Códigos QR de mesa', 'texto' => 'Generación nativa en formato vectorial SVG y ficha imprimible, con identificación automática de la mesa.', 'icono' => 'M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z'],
            ['titulo' => 'Velocidad y claridad', 'texto' => 'Carga instantánea en el teléfono, idioma detectado automáticamente, horarios en vivo y filtros claros de alérgenos.', 'icono' => 'M13 10V3L4 14h7v7l9-11h-7z'],
            ['titulo' => 'Pedidos a WhatsApp', 'texto' => 'Carrito en el navegador con variantes y notas por platillo, enviado desglosado con un solo toque. Sin comisiones.', 'icono' => 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z'],
            ['titulo' => 'Reservas en línea', 'texto' => 'Tus clientes reservan desde el sitio, con disponibilidad por horario y aforo. Tú las ves y confirmas desde el panel.', 'icono' => 'M8 7V3m8 4V3M3 11h18M5 5h14a2 2 0 012 2v12a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2z'],
            ['titulo' => 'Panel de servicio', 'texto' => 'Reservas, pedidos y menú del día en una sola pantalla, con roles para gerente, recepción y cocina.', 'icono' => 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
            ['titulo' => 'Tu marca, a tu estilo', 'texto' => 'Siete plantillas para el sitio, con tu logo, tus fotos y tus colores. Claro y oscuro incluidos.', 'icono' => 'M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01'],
        ];
    @endphp

    <header class="sticky top-0 z-40 w-full backdrop-blur-md bg-fondo/90 border-b border-borde">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between">
            <a href="{{ url('/') }}" class="inline-flex items-center gap-2.5 min-h-[44px] font-bold tracking-tight text-lg text-texto-fuerte focus:outline-none focus-visible:ring-2 focus-visible:ring-foco rounded-lg px-2 py-1" aria-label="MENUONL - Inicio">
                <span class="w-8 h-8 rounded-xl bg-accion text-accion-texto flex items-center justify-center font-titulo font-bold text-base shadow-sm" aria-hidden="true">M</span>
                <span class="text-base tracking-wide font-extrabold">MENUONL</span>
            </a>

            <nav class="flex items-center gap-1 sm:gap-2" aria-label="Navegación principal">
                <a href="#plantillas" class="hidden md:inline-flex items-center min-h-[44px] text-sm font-medium text-texto-suave hover:text-texto-fuerte px-3 rounded-lg transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-foco">Plantillas</a>
                <a href="#como-funciona" class="hidden md:inline-flex items-center min-h-[44px] text-sm font-medium text-texto-suave hover:text-texto-fuerte px-3 rounded-lg transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-foco">Cómo funciona</a>
                @if ($demoUrl)
                    <a href="{{ $demoUrl }}" class="hidden sm:inline-flex items-center min-h-[44px] text-sm font-medium text-texto-suave hover:text-texto-fuerte px-3 rounded-lg transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-foco whitespace-nowrap">
                        Ver menú de muestra
                    </a>
                @endif
                <a href="{{ url('/admin') }}" class="inline-flex items-center justify-center min-h-[44px] px-5 rounded-full text-sm font-semibold bg-accion hover:bg-accion-hover text-accion-texto transition-colors shadow-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-foco focus-visible:ring-offset-2 whitespace-nowrap">
                    Entrar al panel
                </a>
            </nav>
        </div>
    </header>

    <main class="flex-1">
        <section class="max-w-6xl mx-auto px-4 sm:px-6 pt-12 sm:pt-20 pb-14 sm:pb-20">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-14 items-center">
                <div class="lg:col-span-7 text-center lg:text-left">
                    <p class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-semibold bg-superficie-2 text-texto-medio border border-borde mb-6">
                        <span class="w-2 h-2 rounded-full bg-acento" aria-hidden="true"></span>
                        Menú digital, reservas y pedidos para restaurantes
                    </p>

                    <h1 class="font-titulo text-4xl sm:text-5xl lg:text-6xl font-semibold tracking-tight text-texto-fuerte leading-[1.08] mb-6">
                        La carta digital que tus comensales recordarán.
                    </h1>

                    <p class="text-lg sm:text-xl text-texto-medio max-w-2xl mx-auto lg:mx-0 leading-relaxed mb-8">
                        Transforma cada mesa en una experiencia interactiva. Menús ultrarrápidos, códigos QR de alta definición, reservas en línea y pedidos directos a WhatsApp, sin descargas ni comisiones intermedias.
                    </p>

                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-center lg:justify-start gap-3">
                        @if ($demoUrl)
                            <a href="{{ $demoUrl }}" class="inline-flex items-center justify-center min-h-[48px] px-7 rounded-full text-base font-semibold bg-accion hover:bg-accion-hover text-accion-texto transition-colors shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-foco focus-visible:ring-offset-2 whitespace-nowrap">
                                Explorar menú de muestra
                            </a>
                        @endif
                        <a href="{{ url('/admin') }}" class="inline-flex items-center justify-center min-h-[48px] px-7 rounded-full text-base font-semibold bg-superficie hover:bg-superficie-2 text-texto-fuerte border border-borde-control transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-foco focus-visible:ring-offset-2 whitespace-nowrap">
                            Acceder a la administración
                        </a>
                    </div>

                    <ul class="mt-8 flex flex-wrap justify-center lg:justify-start gap-x-6 gap-y-2 text-sm text-texto-suave">
                        @foreach (['Sin descargas', 'Sin comisiones por pedido', 'Claro y oscuro'] as $punto)
                            <li class="inline-flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-exito-texto" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                {{ $punto }}
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="lg:col-span-5" aria-hidden="true">
                    <div class="rounded-3xl bg-superficie border border-borde shadow-xl overflow-hidden max-w-md mx-auto">
                        <div class="p-5 bg-superficie-2/70 border-b border-borde">
                            <div class="flex items-center justify-between gap-2 mb-3">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-abierto-fondo text-abierto-texto">
                                    <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                                    Abierto ahora
                                </span>
                                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-mesa-fondo text-mesa-texto">Mesa 4</span>
                            </div>
                            <p class="text-[11px] font-bold uppercase tracking-widest text-acento">Cocina de origen y brasa</p>
                            <p class="font-titulo text-2xl font-semibold text-texto-fuerte mt-0.5">Casa Lumbre</p>
                            <div class="flex items-center gap-4 mt-4 pt-3 border-t border-borde text-xs font-semibold">
                                <span class="text-texto-fuerte border-b-2 border-acento pb-1">Destacados</span>
                                <span class="text-texto-suave pb-1">Entradas</span>
                                <span class="text-texto-suave pb-1">Fuertes</span>
                                <span class="text-texto-suave pb-1">Postres</span>
                            </div>
                        </div>

                        <div class="p-5 space-y-4">
                            <div class="p-4 rounded-2xl bg-superficie border border-borde shadow-sm">
                                <div class="inline-flex items-center gap-1 text-[11px] font-bold uppercase tracking-wider text-acento mb-1">
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M10 1.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8L10 14.9 4.8 17.6l1-5.8L1.5 7.7l5.9-.9z"/></svg>
                                    Recomendado
                                </div>
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="font-titulo font-semibold text-texto-fuerte text-base">Pulpo a las brasas</p>
                                        <p class="text-sm font-bold text-acento mt-0.5">$285.00 MXN</p>
                                        <p class="text-xs text-texto-suave mt-1 leading-relaxed">Adobo de chiles secos, puré rústico de papa con aceite de oliva y ceniza de totomoxtle.</p>
                                        <div class="flex flex-wrap gap-1.5 mt-2">
                                            <span class="px-2 py-0.5 rounded-full border border-borde text-[11px] font-semibold text-texto-medio">Sin gluten</span>
                                            <span class="px-2 py-0.5 rounded-full border border-borde text-[11px] font-semibold text-texto-medio">Picante</span>
                                        </div>
                                    </div>
                                    <div class="w-16 h-16 rounded-xl bg-superficie-2 border border-borde shrink-0 flex items-center justify-center text-acento">
                                        <svg class="w-9 h-9" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="8.5"/><circle cx="12" cy="12" r="4.5"/><path stroke-linecap="round" d="M3 5v5a2 2 0 002 2M7 3v7M21 3c-1.7 1.2-2.5 3-2.5 5.5V12"/></svg>
                                    </div>
                                </div>
                            </div>

                            <div class="pt-1 flex items-center justify-between gap-3 border-t border-borde">
                                <div>
                                    <p class="font-titulo font-semibold text-texto-fuerte text-sm">Tostada de atún fresco</p>
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

        <section id="plantillas" class="border-y border-borde bg-superficie-2/50 scroll-mt-20">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 py-14 sm:py-20">
                <div class="max-w-2xl mb-10">
                    <p class="text-xs font-bold uppercase tracking-widest text-acento mb-2">{{ count($plantillas) }} plantillas para tu sitio</p>
                    <h2 class="font-titulo text-3xl sm:text-4xl font-semibold text-texto-fuerte tracking-tight mb-3">Un sitio que se parece a tu restaurante</h2>
                    <p class="text-texto-medio text-lg leading-relaxed">Elige la plantilla que va con tu cocina y cámbiala cuando quieras desde el panel. Tu menú, tus fotos y tus horarios se adaptan solos.</p>
                </div>

                <ul class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    @foreach ($plantillas as $plantilla)
                        <li class="rounded-2xl bg-superficie border border-borde shadow-sm overflow-hidden flex flex-col">
                            <div class="h-28 p-4 flex flex-col justify-between" style="background-color: {{ $plantilla['fondo'] }}; color: {{ $plantilla['tinta'] }}" aria-hidden="true">
                                <span class="inline-flex w-7 h-7 rounded-full items-center justify-center text-xs font-extrabold" style="background-color: {{ $plantilla['acento'] }}; color: {{ $plantilla['fondo'] }}">{{ $plantilla['clave'] }}</span>
                                <div class="space-y-1.5">
                                    <span class="block h-2 w-3/4 rounded-full opacity-90" style="background-color: {{ $plantilla['tinta'] }}"></span>
                                    <span class="block h-2 w-1/2 rounded-full opacity-40" style="background-color: {{ $plantilla['tinta'] }}"></span>
                                </div>
                            </div>
                            <div class="p-4 flex-1">
                                <h3 class="font-titulo text-lg font-semibold text-texto-fuerte">{{ $plantilla['nombre'] }}</h3>
                                <p class="text-sm text-texto-suave mt-1 leading-relaxed">{{ $plantilla['descripcion'] }}</p>
                            </div>
                        </li>
                    @endforeach
                    <li class="rounded-2xl border border-dashed border-borde-control p-5 flex flex-col justify-center text-center gap-3">
                        <p class="font-titulo text-lg font-semibold text-texto-fuerte">¿Quieres verlo en vivo?</p>
                        @if ($demoUrl)
                            <a href="{{ $demoUrl }}" class="inline-flex items-center justify-center min-h-[44px] px-5 rounded-full text-sm font-semibold bg-accion hover:bg-accion-hover text-accion-texto transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-foco">Abrir el menú de muestra</a>
                        @else
                            <p class="text-sm text-texto-suave">Entra al panel y prueba una plantilla.</p>
                        @endif
                    </li>
                </ul>
            </div>
        </section>

        <section id="como-funciona" class="max-w-6xl mx-auto px-4 sm:px-6 py-14 sm:py-20 scroll-mt-20">
            <div class="max-w-2xl mb-10">
                <p class="text-xs font-bold uppercase tracking-widest text-acento mb-2">Cómo funciona</p>
                <h2 class="font-titulo text-3xl sm:text-4xl font-semibold text-texto-fuerte tracking-tight">De la carta de papel a la mesa en tres pasos</h2>
            </div>
            <ol class="grid grid-cols-1 md:grid-cols-3 gap-6">
                @foreach ($pasos as $paso)
                    <li class="relative p-7 rounded-3xl bg-superficie border border-borde shadow-sm">
                        <span class="inline-flex w-11 h-11 rounded-full items-center justify-center bg-accion text-accion-texto font-titulo text-xl font-semibold mb-5" aria-hidden="true">{{ $paso['n'] }}</span>
                        <h3 class="font-titulo text-xl font-semibold text-texto-fuerte mb-2"><span class="sr-only">Paso {{ $paso['n'] }}: </span>{{ $paso['titulo'] }}</h3>
                        <p class="text-texto-suave text-sm leading-relaxed">{{ $paso['texto'] }}</p>
                    </li>
                @endforeach
            </ol>
        </section>

        <section class="border-t border-borde bg-superficie-2/50">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 py-14 sm:py-20">
                <div class="max-w-2xl mb-10">
                    <p class="text-xs font-bold uppercase tracking-widest text-acento mb-2">Todo en un solo lugar</p>
                    <h2 class="font-titulo text-3xl sm:text-4xl font-semibold text-texto-fuerte tracking-tight">Menos papel, más mesas atendidas</h2>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach ($beneficios as $beneficio)
                        <article class="p-7 rounded-3xl bg-superficie border border-borde shadow-sm">
                            <div class="w-12 h-12 rounded-2xl bg-superficie-2 text-acento flex items-center justify-center mb-5">
                                <svg class="w-6 h-6" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $beneficio['icono'] }}"/>
                                </svg>
                            </div>
                            <h3 class="font-titulo text-xl font-semibold text-texto-fuerte mb-2">{{ $beneficio['titulo'] }}</h3>
                            <p class="text-texto-suave text-sm leading-relaxed">{{ $beneficio['texto'] }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="max-w-6xl mx-auto px-4 sm:px-6 py-14 sm:py-20">
            <div class="rounded-3xl bg-accion text-accion-texto px-6 sm:px-12 py-12 sm:py-16 text-center shadow-lg">
                <h2 class="font-titulo text-3xl sm:text-4xl font-semibold tracking-tight mb-3">Pon tu carta en cada mesa hoy</h2>
                <p class="max-w-xl mx-auto opacity-90 text-lg mb-8">Entra al panel, sube tu menú e imprime tu primer código QR en unos minutos.</p>
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-center gap-3">
                    <a href="{{ url('/admin') }}" class="inline-flex items-center justify-center min-h-[48px] px-7 rounded-full text-base font-semibold bg-accion-texto text-accion hover:opacity-90 transition-opacity focus:outline-none focus-visible:ring-2 focus-visible:ring-accion-texto focus-visible:ring-offset-2 focus-visible:ring-offset-accion whitespace-nowrap">Entrar al panel</a>
                    @if ($demoUrl)
                        <a href="{{ $demoUrl }}" class="inline-flex items-center justify-center min-h-[48px] px-7 rounded-full text-base font-semibold border border-current hover:bg-accion-texto/10 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-accion-texto focus-visible:ring-offset-2 focus-visible:ring-offset-accion whitespace-nowrap">Ver un menú de muestra</a>
                    @endif
                </div>
            </div>
        </section>
    </main>

    <footer class="w-full border-t border-borde py-8 text-sm text-texto-suave">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 flex flex-col md:flex-row items-center justify-between gap-5">
            <p>&copy; {{ date('Y') }} MENUONL. Hecho para la hostelería moderna.</p>
            <nav class="flex flex-wrap items-center justify-center gap-x-4" aria-label="Pie de página">
                <a href="#plantillas" class="inline-flex items-center min-h-[44px] px-2 hover:text-texto-fuerte transition-colors rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-foco">Plantillas</a>
                <a href="#como-funciona" class="inline-flex items-center min-h-[44px] px-2 hover:text-texto-fuerte transition-colors rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-foco">Cómo funciona</a>
                <a href="{{ url('/admin') }}" class="inline-flex items-center min-h-[44px] px-2 hover:text-texto-fuerte transition-colors rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-foco">Administración</a>
                @if ($demoUrl)
                    <a href="{{ $demoUrl }}" class="inline-flex items-center min-h-[44px] px-2 hover:text-texto-fuerte transition-colors rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-foco">Menú de muestra</a>
                @endif
            </nav>
            <x-apariencia nombre="apariencia-portada" />
        </div>
    </footer>
</x-layout.publico>
