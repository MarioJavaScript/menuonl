<x-layout.publico
    titulo="MENUONL — Menú Digital Interactivo para Restaurantes"
    descripcion="Plataforma de menú digital interactivo con códigos QR de alta definición, pedidos directos a WhatsApp, reservas en línea y gestión en tiempo real para restaurantes y cafeterías."
    html-class="scroll-smooth"
    tema="calido"
    class="min-h-screen flex flex-col bg-fondo selection:bg-acento selection:text-acento-texto"
>
    <x-slot:head>
        <script>
            {{-- Las animaciones de entrada solo se activan con JS y sin "menos movimiento"; si Motion no carga, se muestra todo. --}}
            (function () {
                if (matchMedia('(prefers-reduced-motion: reduce)').matches) { return; }
                var raiz = document.documentElement;
                raiz.classList.add('anim');
                setTimeout(function () { if (!window.__portadaLista) { raiz.classList.remove('anim'); } }, 3500);
            })();
        </script>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/portada.css', 'resources/js/portada.js'])
        @endif
    </x-slot:head>

    @php
        $cinta = ['Código QR', 'Pedidos por WhatsApp', 'Reservas en línea', 'Menú multi-idioma', 'Claro y oscuro', 'Sin comisiones', 'Panel de servicio', 'Siete plantillas'];
        $pasos = [
            ['titulo' => 'Sube tu menú', 'icono' => 'M4 6h16M4 12h16M4 18h10'],
            ['titulo' => 'Imprime tu QR', 'icono' => 'M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z'],
            ['titulo' => 'Tus comensales piden', 'icono' => 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z'],
        ];
        $faq = [
            ['p' => '¿Mis clientes tienen que instalar algo?', 'r' => 'No. Escanean el QR y el menú se abre en el navegador de su teléfono.'],
            ['p' => '¿Cómo me llegan los pedidos?', 'r' => 'Por WhatsApp, con el pedido desglosado por platillo, variante y nota. No cobramos comisión por pedido.'],
            ['p' => '¿Puedo cambiar el diseño de mi sitio?', 'r' => 'Sí. Eliges entre siete plantillas desde Ajustes en el panel y la cambias cuando quieras.'],
            ['p' => '¿Qué pasa si cambio un precio o agoto un platillo?', 'r' => 'Se actualiza en el menú al guardar. Un toque en el panel marca un platillo como agotado.'],
            ['p' => '¿Mi equipo puede usar el panel?', 'r' => 'Sí. Hay roles de propietario, gerente, editor, recepción y cocina, y cada uno ve solo lo que necesita.'],
        ];
        // Patrón decorativo tipo QR (9×9) con los tres cuadros de esquina fijos.
        $qr = [];
        for ($f = 0; $f < 9; $f++) {
            for ($c = 0; $c < 9; $c++) {
                $esquina = ($f < 3 && $c < 3) || ($f < 3 && $c > 5) || ($f > 5 && $c < 3);
                $qr[] = $esquina || ((($f * 7 + $c * 13 + $f * $c) % 5) < 3);
            }
        }
        $icoCheck = 'M5 13l4 4L19 7';
        $icoX = 'M6 6l12 12M18 6L6 18';
    @endphp

    <svg width="0" height="0" class="absolute" aria-hidden="true" focusable="false">
        <defs>
            <symbol id="pt-plato" viewBox="0 0 64 64">
                <circle cx="32" cy="32" r="30" fill="#f4ede0"/>
                <circle cx="32" cy="32" r="30" fill="none" stroke="#d9cdb6" stroke-width="2"/>
                <circle cx="32" cy="32" r="21" fill="#fffaf0"/>
                <circle cx="27" cy="29" r="8" fill="var(--c1, #c8501c)"/>
                <circle cx="37" cy="33" r="7" fill="var(--c2, #6a8a2c)"/>
                <circle cx="31" cy="38" r="5" fill="var(--c3, #f0b54a)"/>
                <circle cx="25" cy="27" r="2.2" fill="#fff" opacity=".45"/>
            </symbol>
        </defs>
    </svg>

    <header class="pt-cab">
        <div class="pt-wrap pt-cab-fila">
            <a href="{{ url('/') }}" class="inline-flex items-center gap-2.5 min-h-[44px] font-extrabold tracking-wide text-base rounded-lg px-1" aria-label="MENUONL - Inicio">
                <span class="w-8 h-8 rounded-xl flex items-center justify-center font-titulo font-bold text-base" style="background: var(--pt-ocre); color: var(--pt-ocre-tinta)" aria-hidden="true">M</span>
                MENUONL
            </a>
            <nav class="pt-nav" aria-label="Navegación principal">
                <a href="#inicio" aria-current="page">Inicio</a>
                <a href="#plantillas">Plantillas</a>
                <a href="#como-funciona">Cómo funciona</a>
                @if ($demoUrl)
                    <a href="{{ $demoUrl }}" class="whitespace-nowrap">Ver menú de muestra</a>
                @else
                    <a href="#preguntas">Preguntas</a>
                @endif
            </nav>
            <a href="{{ url('/admin') }}" class="pt-btn pt-btn-brasa">Entrar al panel</a>
        </div>
    </header>

    <main class="flex-1">
        <section id="inicio" class="pt-sec pt-hero">
            <div class="pt-wrap pt-grid-hero">
                <div>
                    <p class="pt-etq" style="color: var(--pt-brasa)">Menú digital para restaurantes</p>
                    <h1><span>La carta digital</span> <span>que tus comensales</span> <span>recordarán.</span></h1>
                    <p class="pt-lead">Menú, reservas y pedidos a WhatsApp, sin comisiones.</p>
                    <div class="pt-acciones">
                        @if ($demoUrl)
                            <a href="{{ $demoUrl }}" class="pt-btn pt-btn-brasa">Explorar menú de muestra</a>
                        @endif
                        <a href="{{ url('/admin') }}" class="pt-btn pt-btn-linea">Acceder a la administración</a>
                    </div>
                </div>

                <div class="pt-vitrina" aria-hidden="true">
                    <div class="pt-marco" data-parallax="12">
                        <div class="pt-disco"><svg viewBox="0 0 64 64" style="--c1:#c8501c;--c2:#6a8a2c;--c3:#f0b54a"><use href="#pt-plato"/></svg></div>
                    </div>

                    <div class="pt-flot pt-f1" data-parallax="26">
                        <div class="pt-vidrio">
                            <i><svg fill="none" stroke="currentColor" stroke-width="2.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icoCheck }}"/></svg></i>
                            <span>Reserva confirmada<small>Hoy · 8:30 pm · 4 personas</small></span>
                        </div>
                    </div>

                    <div class="pt-flot pt-f2" data-parallax="-22">
                        <div class="pt-phone" data-inclina>
                            <div class="pt-pantalla">
                                <div class="pt-notch"></div>
                                <div class="pt-mini-t">Casa Lumbre</div>
                                <div class="pt-mini-fila"><span><strong>Pulpo a las brasas</strong><b>$285</b></span><svg viewBox="0 0 64 64" style="--c1:#c8501c;--c2:#6a8a2c;--c3:#f0b54a"><use href="#pt-plato"/></svg></div>
                                <div class="pt-mini-fila"><span><strong>Tostada de atún</strong><b>$145</b></span><svg viewBox="0 0 64 64" style="--c1:#a3261a;--c2:#3d5a17;--c3:#f4ede0"><use href="#pt-plato"/></svg></div>
                                <div class="pt-mini-wa">Pedir por WhatsApp</div>
                            </div>
                        </div>
                    </div>

                    <div class="pt-flot pt-f3" data-parallax="22">
                        <div class="pt-vidrio">
                            <i><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 4h14l-1 16H6L5 4zM9 8h6"/></svg></i>
                            <span>Pedido nuevo<small>Mesa 4 · $430.00</small></span>
                        </div>
                    </div>

                    <div class="pt-flot pt-f4" data-parallax="-14">
                        <div class="pt-vidrio"><div class="pt-qr">@foreach ($qr as $punto)<i @class(['v' => ! $punto])></i>@endforeach</div></div>
                    </div>
                </div>
            </div>
        </section>

        <div class="pt-cinta" aria-hidden="true">
            <ul>
                @for ($i = 0; $i < 2; $i++)
                    @foreach ($cinta as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                @endfor
            </ul>
        </div>

        <section id="como-funciona" class="pt-sec pt-crema scroll-mt-16">
            <div class="pt-wrap">
                <p class="pt-etq" style="color: var(--mo-acento)" data-revela>Cómo funciona</p>
                <h2 class="pt-h2" data-revela>Listo en tres pasos</h2>
                <ol class="pt-pasos" data-grupo>
                    @foreach ($pasos as $paso)
                        <li class="pt-paso" data-revela>
                            <span class="pt-ico"><svg aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $paso['icono'] }}"/></svg></span>
                            <h3><span class="sr-only">Paso {{ $loop->iteration }}: </span>{{ $paso['titulo'] }}</h3>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>

        <section class="pt-sec pt-terracota">
            <div class="pt-wrap">
                <p class="pt-etq" style="color: var(--pt-ocre)" data-revela>Todo en un solo lugar</p>
                <h2 class="pt-h2" data-revela>Menos papel, más mesas atendidas</h2>
                <div class="pt-bento" data-grupo>
                    <article class="pt-card pt-b2" data-revela data-inclina>
                        <div class="pt-vis"><div class="pt-qr" aria-hidden="true">@foreach ($qr as $punto)<i @class(['v' => ! $punto])></i>@endforeach</div></div>
                        <h3>Código QR en cada mesa</h3>
                    </article>
                    <article class="pt-card pt-b4" data-revela data-inclina>
                        <div class="pt-vis"><div class="pt-burbujas" aria-hidden="true"><span>Hola, quiero pedir en Casa Lumbre · Mesa 4</span><span>2× Pulpo a las brasas · $430.00</span></div></div>
                        <h3>Pedidos directo a tu WhatsApp</h3>
                    </article>
                    <article class="pt-card pt-b3" data-revela data-inclina>
                        <div class="pt-vis"><div class="pt-horas" aria-hidden="true"><span>7:00 pm</span><span class="on">8:30 pm</span><span>9:00 pm</span><span>9:30 pm</span></div></div>
                        <h3>Reservas en línea</h3>
                    </article>
                    <article class="pt-card pt-b3" data-revela data-inclina>
                        <div class="pt-vis"><div class="pt-numeros" aria-hidden="true"><div><b>12</b><small>Reservas</small></div><div><b>8</b><small>Pedidos</small></div><div><b>$4.2k</b><small>Ventas</small></div></div></div>
                        <h3>Panel de servicio</h3>
                    </article>
                    <article class="pt-card pt-b3" data-revela data-inclina>
                        <div class="pt-vis"><div class="pt-puntos" aria-hidden="true">@foreach ($plantillas as $plantilla)<i style="background: {{ $plantilla['fondo'] }}"></i>@endforeach</div></div>
                        <h3>{{ count($plantillas) }} plantillas, tu marca</h3>
                    </article>
                    <article class="pt-card pt-b3" data-revela data-inclina>
                        <div class="pt-vis"><div class="pt-horas" aria-hidden="true"><span class="on">ES</span><span>EN</span><span>Claro</span><span>Oscuro</span></div></div>
                        <h3>Idiomas y modo oscuro</h3>
                    </article>
                </div>
            </div>
        </section>

        <section id="plantillas" class="pt-sec pt-ocre scroll-mt-16">
            <div class="pt-wrap">
                <p class="pt-etq" data-revela>{{ count($plantillas) }} plantillas</p>
                <h2 class="pt-h2" data-revela>Un sitio que se parece a tu restaurante</h2>
            </div>
            <div class="pt-wrap">
                <ul class="pt-tira" data-grupo>
                    @foreach ($plantillas as $plantilla)
                        <li class="pt-tpl" data-revela>
                            <div class="pt-tpl-vista" style="background: {{ $plantilla['fondo'] }}; color: {{ $plantilla['tinta'] }}" aria-hidden="true">
                                <span class="l" style="background: {{ $plantilla['acento'] }}; color: {{ $plantilla['fondo'] }}">{{ $plantilla['clave'] }}</span>
                                <div class="b">
                                    <span style="background: {{ $plantilla['tinta'] }}"></span>
                                    <span style="background: {{ $plantilla['tinta'] }}"></span>
                                    <span style="background: {{ $plantilla['acento'] }}"></span>
                                </div>
                            </div>
                            <h3>{{ $plantilla['nombre'] }}</h3>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>

        <section class="pt-sec pt-crema-2">
            <div class="pt-wrap">
                <p class="pt-etq" style="color: var(--mo-acento)" data-revela>Papel contra MENUONL</p>
                <h2 class="pt-h2" data-revela>Tu carta merece algo mejor que un PDF</h2>
                <div class="pt-vs" data-grupo>
                    <div class="pt-papel" data-revela>
                        <h3>Carta de papel</h3>
                        <ul>
                            @foreach (['Se imprime cada vez que cambia un precio', 'Se acaba el platillo y nadie avisa', 'Pedidos a gritos y notas perdidas', 'Reservar es llamar y esperar'] as $punto)
                                <li><svg aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24"><path stroke-linecap="round" d="{{ $icoX }}"/></svg>{{ $punto }}</li>
                            @endforeach
                        </ul>
                    </div>
                    <div class="pt-menuonl" data-revela>
                        <h3>MENUONL</h3>
                        <ul>
                            @foreach (['Cambias el precio y se actualiza al guardar', 'Un toque marca el platillo como agotado', 'Pedidos desglosados directo a WhatsApp', 'Reservas en línea con horarios y aforo'] as $punto)
                                <li><svg aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icoCheck }}"/></svg>{{ $punto }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <section class="pt-sec pt-carbon">
            <div class="pt-wrap">
                <div class="pt-nums" data-grupo>
                    <div class="pt-num" data-revela><b data-cuenta="0">0</b><span>% de comisión por pedido</span></div>
                    <div class="pt-num" data-revela><b data-cuenta="{{ count($plantillas) }}">{{ count($plantillas) }}</b><span>plantillas para tu sitio</span></div>
                    <div class="pt-num" data-revela><b data-cuenta="5">5</b><span>roles para tu equipo</span></div>
                </div>
            </div>
        </section>

        <section id="preguntas" class="pt-sec pt-crema scroll-mt-16">
            <div class="pt-wrap">
                <p class="pt-etq" style="color: var(--mo-acento)" data-revela>Preguntas frecuentes</p>
                <h2 class="pt-h2" data-revela>Lo que todos preguntan</h2>
                <div class="pt-faq" data-grupo>
                    @foreach ($faq as $item)
                        <details data-revela>
                            <summary>{{ $item['p'] }}</summary>
                            <p>{{ $item['r'] }}</p>
                        </details>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="pt-sec pt-cierre">
            <div class="pt-wrap">
                <h2 data-revela>Pon tu carta en cada mesa hoy</h2>
                <div class="pt-acciones" data-revela>
                    <a href="{{ url('/admin') }}" class="pt-btn pt-btn-oscuro">Entrar al panel</a>
                    @if ($demoUrl)
                        <a href="{{ $demoUrl }}" class="pt-btn pt-btn-linea">Ver un menú de muestra</a>
                    @endif
                </div>
            </div>
        </section>
    </main>

    <footer class="pt-pie">
        <div class="pt-wrap flex flex-col md:flex-row items-center justify-between gap-4">
            <p>&copy; {{ date('Y') }} MENUONL. Hecho para la hostelería moderna.</p>
            <nav class="flex flex-wrap items-center justify-center" aria-label="Pie de página">
                <a href="#plantillas">Plantillas</a>
                <a href="#como-funciona">Cómo funciona</a>
                <a href="{{ url('/admin') }}">Administración</a>
                @if ($demoUrl)
                    <a href="{{ $demoUrl }}">Menú de muestra</a>
                @endif
            </nav>
            <x-apariencia nombre="apariencia-portada" />
        </div>
    </footer>
</x-layout.publico>
