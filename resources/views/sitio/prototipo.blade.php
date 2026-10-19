@php
    /*
     * Contenido propio del sitio: viene de sitio_configuraciones y sitio_galeria (editable desde el panel).
     * Cada bloque se degrada si falta su contenido; el sitio funciona sin ninguna foto ni texto de configuración.
     */
    $nombre = $restaurante->traduccion($idioma)->nombre;
    $descripcion = $restaurante->traduccion($idioma)->descripcion;
    $subtitulo = filled($sitio?->hero_subtitulo) ? $sitio->hero_subtitulo : $descripcion;
    $portadaUrl = filled($sitio?->hero_imagen) ? \Illuminate\Support\Facades\Storage::url($sitio->hero_imagen) : null;
    $historiaImagenUrl = filled($sitio?->historia_imagen) ? \Illuminate\Support\Facades\Storage::url($sitio->historia_imagen) : null;
    $estadisticas = collect($sitio?->estadisticas ?? [])
        ->filter(fn ($dato): bool => is_array($dato) && filled($dato['etiqueta'] ?? null) && filled($dato['valor'] ?? null))
        ->take(3);
    $hayHistoria = filled($sitio?->historia_titulo) || filled($sitio?->historia_parrafo_1) || filled($sitio?->historia_parrafo_2) || $historiaImagenUrl || $estadisticas->isNotEmpty();
    $hayGaleria = $galeria->isNotEmpty();
    $hayReservas = $sitio?->reservas_activas ?? true;

    $creditos = collect([
        $portadaUrl ? ['Portada', $sitio->hero_imagen_creditos] : null,
        $historiaImagenUrl ? ['Historia', $sitio->historia_imagen_creditos] : null,
        ...$galeria->map(fn ($foto): array => [$foto->titulo, $foto->creditos])->all(),
    ])->filter(fn ($fila): bool => is_array($fila) && filled($fila[1]));

    $enlaces = collect([
        'historia' => $hayHistoria ? 'Historia' : null,
        'menu' => 'Menú',
        'galeria' => $hayGaleria ? 'Galería' : null,
        'reservar' => $hayReservas ? 'Reservar' : null,
        'contacto' => 'Contacto',
    ])->filter();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $idioma) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="color-scheme" content="light">
        <meta name="robots" content="noindex">
        <title>{{ $nombre }}</title>
        @if (filled($subtitulo))
            <meta name="description" content="{{ $subtitulo }}">
        @endif
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        <link rel="preload" href="{{ asset('fonts/sitio/fraunces-variable.woff2') }}" as="font" type="font/woff2" crossorigin>
        @if ($hojasListas)
            @vite(['resources/css/sitio.css', 'resources/js/sitio.js'])
        @endif
    </head>
    <body class="bg-crema font-sans text-tinta antialiased">
        <a href="#contenido" class="sr-only z-50 rounded-md bg-papel px-4 py-3 font-semibold text-tinta focus:not-sr-only focus:fixed focus:left-3 focus:top-3">Saltar al contenido</a>

        {{-- ================= Cabecera ================= --}}
        <header class="absolute inset-x-0 top-0 z-30 text-papel">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-5 py-4">
                <a href="#inicio" class="font-serif text-xl font-semibold tracking-tight focus-visible:outline-papel">{{ $nombre }}</a>

                <nav class="hidden items-center gap-1 md:flex" aria-label="Secciones del sitio">
                    @foreach ($enlaces as $ancla => $texto)
                        @continue($ancla === 'reservar')
                        <a href="#{{ $ancla }}" class="inline-flex min-h-11 items-center rounded-full px-4 text-sm font-medium hover:bg-papel/15">{{ $texto }}</a>
                    @endforeach
                    @if ($hayReservas)
                        <a href="#reservar" class="ml-2 inline-flex min-h-11 items-center rounded-full bg-papel px-5 text-sm font-semibold text-tinta hover:bg-crema">Reservar</a>
                    @endif
                </nav>

                {{-- Menú móvil sin JavaScript: <details>/<summary>. --}}
                <details class="relative md:hidden" data-menu-movil>
                    <summary class="flex size-11 cursor-pointer list-none items-center justify-center rounded-full bg-noche/60 [&::-webkit-details-marker]:hidden" aria-label="Abrir el menú de secciones">
                        <x-sitio.icono nombre="menu" class="size-6" />
                    </summary>
                    <nav class="absolute right-0 mt-2 w-64 rounded-2xl bg-papel p-2 text-tinta shadow-xl" aria-label="Secciones del sitio">
                        @foreach ($enlaces as $ancla => $texto)
                            <a href="#{{ $ancla }}" class="flex min-h-11 items-center rounded-xl px-4 font-medium hover:bg-crema {{ $ancla === 'reservar' ? 'bg-terracota text-papel hover:bg-terracota-osc' : '' }}">{{ $texto }}</a>
                        @endforeach
                    </nav>
                </details>
            </div>
        </header>

        <main id="contenido">
            {{-- ================= Portada ================= --}}
            <section id="inicio" class="relative isolate flex min-h-[92svh] items-end overflow-hidden bg-noche text-papel" aria-labelledby="titulo-portada">
                @if ($portadaUrl)
                    <img src="{{ $portadaUrl }}" alt="" width="1200" height="800" fetchpriority="high" decoding="async" class="absolute inset-0 -z-20 size-full object-cover">
                    <div class="absolute inset-0 -z-10 bg-linear-to-t from-noche via-noche/70 to-noche/40" aria-hidden="true"></div>
                @else
                    {{-- Sin foto: fondo tipográfico de color. --}}
                    <div class="absolute inset-0 -z-10 bg-linear-to-br from-noche via-noche-2 to-terracota-osc" aria-hidden="true"></div>
                @endif

                <div class="mx-auto w-full max-w-6xl px-5 pb-14 pt-32 md:pb-20">
                    <div class="max-w-3xl">
                        <p class="mb-4 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm font-semibold uppercase tracking-[0.18em] text-brasa">
                            @if ($estaAbierto)
                                <span class="inline-flex items-center gap-2 rounded-full bg-noche/70 px-3 py-1.5 text-papel ring-1 ring-papel/30">
                                    <span class="size-2.5 rounded-full bg-emerald-400" aria-hidden="true"></span>Abierto ahora
                                </span>
                            @else
                                <span class="inline-flex items-center gap-2 rounded-full bg-noche/70 px-3 py-1.5 text-papel ring-1 ring-papel/30">
                                    <span class="size-2.5 rounded-full bg-rose-400" aria-hidden="true"></span>Cerrado ahora
                                </span>
                            @endif
                            @if ($turnosHoy->isNotEmpty())
                                <span class="inline-flex items-center gap-2 text-papel">
                                    <x-sitio.icono nombre="reloj" class="size-4" />
                                    Hoy
                                    @foreach ($turnosHoy as $turno)
                                        {{ substr($turno->abre, 0, 5) }} - {{ substr($turno->cierra, 0, 5) }}@if (! $loop->last),@endif
                                    @endforeach
                                </span>
                            @endif
                        </p>
                        <h1 id="titulo-portada" class="font-serif text-6xl font-semibold leading-[0.95] tracking-tight text-balance md:text-8xl">{{ $nombre }}</h1>
                        @if (filled($subtitulo))
                            <p class="mt-6 max-w-xl text-lg text-papel/90 md:text-xl">{{ $subtitulo }}</p>
                        @endif
                        <div class="mt-8 flex flex-wrap gap-3">
                            @if ($hayReservas)
                                <a href="#reservar" class="inline-flex min-h-12 items-center gap-2 rounded-full bg-terracota px-7 text-base font-semibold text-papel hover:bg-terracota-osc">
                                    <x-sitio.icono nombre="calendario" class="size-5" />Reservar una mesa
                                </a>
                            @endif
                            <a href="#menu" class="inline-flex min-h-12 items-center gap-2 rounded-full px-7 text-base font-semibold text-papel ring-2 ring-papel/70 hover:bg-papel/15">
                                Ver el menú<x-sitio.icono nombre="flecha" class="size-5" />
                            </a>
                        </div>
                    </div>
                </div>
            </section>

            {{-- ================= Historia ================= --}}
            @if ($hayHistoria)
                <section id="historia" class="mx-auto grid max-w-6xl gap-10 px-5 py-20 md:py-28 {{ $historiaImagenUrl ? 'md:grid-cols-2 md:items-center md:gap-16' : 'md:max-w-3xl' }}" aria-labelledby="titulo-historia">
                    <div class="revelar">
                        @if (filled($sitio?->historia_etiqueta))
                            <p class="mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-terracota">{{ $sitio->historia_etiqueta }}</p>
                        @endif
                        @if (filled($sitio?->historia_titulo))
                            <h2 id="titulo-historia" class="font-serif text-4xl font-semibold leading-tight tracking-tight text-balance md:text-5xl">{{ $sitio->historia_titulo }}</h2>
                        @else
                            <h2 id="titulo-historia" class="sr-only">Sobre {{ $nombre }}</h2>
                        @endif
                        @if (filled($sitio?->historia_parrafo_1) || filled($sitio?->historia_parrafo_2))
                            <div class="mt-6 space-y-4 text-lg leading-relaxed text-suave">
                                @if (filled($sitio?->historia_parrafo_1))
                                    <p>{{ $sitio->historia_parrafo_1 }}</p>
                                @endif
                                @if (filled($sitio?->historia_parrafo_2))
                                    <p>{{ $sitio->historia_parrafo_2 }}</p>
                                @endif
                            </div>
                        @endif
                        @if ($estadisticas->isNotEmpty())
                            <dl class="mt-8 flex flex-wrap gap-x-10 gap-y-4 border-t border-linea pt-6">
                                @foreach ($estadisticas as $dato)
                                    <div><dt class="text-sm text-suave">{{ $dato['etiqueta'] }}</dt><dd class="font-serif text-3xl font-semibold">{{ $dato['valor'] }}</dd></div>
                                @endforeach
                            </dl>
                        @endif
                    </div>
                    @if ($historiaImagenUrl)
                        <figure class="revelar">
                            <img src="{{ $historiaImagenUrl }}" alt="Foto de {{ $nombre }}" width="640" height="480" loading="lazy" decoding="async" class="aspect-4/3 w-full rounded-3xl object-cover shadow-lg">
                            @if (filled($sitio->historia_imagen_creditos))
                                <figcaption class="mt-3 text-sm text-suave">{{ $sitio->historia_imagen_creditos }}</figcaption>
                            @endif
                        </figure>
                    @endif
                </section>
            @endif

            {{-- ================= Menú ================= --}}
            @include('sitio.partials.menu')

            {{-- ================= Galería ================= --}}
            @if ($hayGaleria)
                <section id="galeria" class="mx-auto max-w-6xl px-5 py-20 md:py-28" aria-labelledby="titulo-galeria">
                    <div class="revelar mb-10 max-w-2xl">
                        <p class="mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-terracota">Galería</p>
                        <h2 id="titulo-galeria" class="font-serif text-4xl font-semibold leading-tight tracking-tight md:text-5xl">Lo que sale de la cocina</h2>
                    </div>
                    <ul class="grid grid-cols-2 gap-3 md:grid-cols-3 md:gap-5">
                        @foreach ($galeria as $foto)
                            <li class="revelar group relative overflow-hidden rounded-2xl bg-noche-2">
                                <figure>
                                    <img src="{{ \Illuminate\Support\Facades\Storage::url($foto->imagen) }}" alt="{{ $foto->texto_alternativo }}" width="640" height="480" loading="lazy" decoding="async" class="aspect-4/3 size-full object-cover transition-transform duration-700 motion-reduce:transition-none group-hover:scale-105">
                                    <figcaption class="absolute inset-x-0 bottom-0 bg-linear-to-t from-noche/90 to-transparent p-4 pt-10 text-papel">
                                        <span class="block font-serif text-lg font-semibold">{{ $foto->titulo }}</span>
                                        @if (filled($foto->creditos))
                                            <span class="block text-xs text-papel/85">{{ $foto->creditos }}</span>
                                        @endif
                                    </figcaption>
                                </figure>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            {{-- ================= Reservaciones ================= --}}
            @if ($hayReservas)
                @include('sitio.partials.reservas')
            @endif

            {{-- ================= Contacto, horarios y ubicación ================= --}}
            @include('sitio.partials.contacto')
        </main>

        {{-- ================= Pie ================= --}}
        <footer class="bg-noche px-5 pb-24 pt-12 text-papel/85 md:pb-12">
            <div class="mx-auto flex max-w-6xl flex-col gap-8 md:flex-row md:justify-between">
                <div>
                    <p class="font-serif text-2xl font-semibold text-papel">{{ $nombre }}</p>
                    <p class="mt-4 text-sm">
                        <a href="{{ route('menu.publico', ['slug' => $restaurante->slug]) }}" class="inline-flex min-h-11 items-center font-semibold text-brasa underline underline-offset-4">Abrir el menú digital para pedir</a>
                    </p>
                </div>
                @if ($creditos->isNotEmpty())
                    <div class="text-sm">
                        <p class="font-semibold text-papel">Créditos de las fotos</p>
                        <ul class="mt-2 space-y-1">
                            @foreach ($creditos as [$donde, $credito])
                                <li>{{ $donde }}: {{ $credito }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
            <p class="mx-auto mt-10 max-w-6xl border-t border-papel/15 pt-6 text-xs text-papel/70">Prototipo creado con {{ config('app.name') }}</p>
        </footer>

        {{-- Barra fija de reserva en móvil. --}}
        @if ($hayReservas)
            <a href="#reservar" class="fixed inset-x-4 bottom-4 z-40 inline-flex min-h-12 items-center justify-center gap-2 rounded-full bg-terracota px-6 font-semibold text-papel shadow-xl hover:bg-terracota-osc md:hidden">
                <x-sitio.icono nombre="calendario" class="size-5" />Reservar una mesa
            </a>
        @endif
    </body>
</html>
