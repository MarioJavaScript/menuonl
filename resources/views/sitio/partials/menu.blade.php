{{-- Menú por categorías con los datos reales del restaurante (precios, ofertas, variantes, etiquetas, agotados). --}}
@php
    $formatear = fn ($valor) => \App\Services\FormatoMoneda::formatear($valor, $restaurante->moneda, $idioma);
@endphp
<section id="menu" class="bg-papel py-20 md:py-28" aria-labelledby="titulo-menu">
    <div class="mx-auto max-w-6xl px-5">
        <div class="revelar mb-12 max-w-2xl">
            <p class="mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-terracota">La carta</p>
            <h2 id="titulo-menu" class="font-serif text-4xl font-semibold leading-tight tracking-tight md:text-5xl">Lo que cocinamos hoy</h2>
            <p class="mt-4 text-lg text-suave">Precios y disponibilidad al día, tal como están en nuestro menú digital.</p>
        </div>

        @if ($categorias->isNotEmpty())
            <nav class="-mx-5 mb-12 overflow-x-auto px-5" aria-label="Categorías del menú">
                <ul class="flex w-max gap-2 md:w-auto md:flex-wrap">
                    @foreach ($categorias as $categoria)
                        <li>
                            <a href="#cat-{{ $categoria->id }}" class="inline-flex min-h-11 items-center whitespace-nowrap rounded-full border border-linea bg-crema px-5 text-sm font-semibold hover:border-terracota hover:text-terracota">{{ $categoria->traduccion($idioma)?->nombre ?? $categoria->traduccion()?->nombre ?? 'Categoría' }}</a>
                        </li>
                    @endforeach
                </ul>
            </nav>

            <div class="space-y-16">
                @foreach ($categorias as $categoria)
                    @continue($categoria->productos->isEmpty())
                    <div id="cat-{{ $categoria->id }}" class="revelar scroll-mt-24">
                        <h3 class="mb-6 border-b border-linea pb-3 font-serif text-3xl font-semibold">{{ $categoria->traduccion($idioma)?->nombre ?? $categoria->traduccion()?->nombre ?? 'Categoría' }}</h3>
                        <ul class="grid grid-cols-1 gap-x-14 gap-y-8 md:grid-cols-2">
                            @foreach ($categoria->productos as $producto)
                                @php
                                    $nombreProducto = $producto->traduccion($idioma)?->nombre ?? $producto->traduccion()?->nombre ?? 'Platillo';
                                    $descripcionProducto = $producto->traduccion($idioma)?->descripcion ?? $producto->traduccion()?->descripcion;
                                    $enOferta = $producto->precio_oferta !== null && (float) $producto->precio_oferta < (float) $producto->precio;
                                @endphp
                                <li @class(["min-w-0", "flex items-start gap-4" => filled($producto->imagen)])>
                                    <div class="min-w-0 flex-1">
                                    <div class="flex items-baseline gap-3">
                                        <h4 class="min-w-0 font-serif text-xl font-semibold {{ $producto->disponible ? '' : 'text-suave' }}">{{ $nombreProducto }}</h4>
                                        <span class="min-w-2 flex-1 border-b-2 border-dotted border-linea" aria-hidden="true"></span>
                                        @unless ($producto->tieneVariantes())
                                            <p class="flex shrink-0 flex-col items-end whitespace-nowrap text-lg font-semibold leading-tight sm:flex-row sm:items-baseline">
                                                @if ($enOferta)
                                                    <span class="sr-only">Precio original:</span>
                                                    <s class="text-base sm:mr-2 font-normal text-suave">{{ $formatear($producto->precio) }}</s>
                                                    <span class="sr-only">Precio de oferta:</span>
                                                    <span class="text-terracota">{{ $formatear($producto->precio_oferta) }}</span>
                                                @else
                                                    {{ $formatear($producto->precio) }}
                                                @endif
                                            </p>
                                        @endunless
                                    </div>

                                    @if (filled($descripcionProducto))
                                        <p class="mt-1 text-suave">{{ $descripcionProducto }}</p>
                                    @endif

                                    @if ($producto->tieneVariantes())
                                        <ul class="mt-3 space-y-1">
                                            @foreach ($producto->variantes as $variante)
                                                <li class="flex items-baseline justify-between gap-3 text-sm">
                                                    <span>
                                                        {{ $variante->traduccion($idioma)?->nombre ?? $variante->traduccion()?->nombre ?? 'Opción' }}
                                                        @unless ($variante->disponible)
                                                            <span class="ml-1 rounded-full bg-rose-100 px-2 py-0.5 text-xs font-semibold uppercase text-rose-900">Agotado</span>
                                                        @endunless
                                                    </span>
                                                    <span class="font-semibold">{{ $formatear($variante->precio) }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif

                                    @if (! $producto->disponible || $producto->etiquetas->isNotEmpty())
                                        <ul class="mt-3 flex flex-wrap gap-2" aria-label="Notas de {{ $nombreProducto }}">
                                            @unless ($producto->disponible)
                                                <li class="rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-semibold uppercase text-rose-900">Agotado</li>
                                            @endunless
                                            @foreach ($producto->etiquetas as $etiqueta)
                                                <li class="rounded-full bg-crema px-2.5 py-0.5 text-xs font-semibold text-suave ring-1 ring-linea">#{{ $etiqueta->codigo }}</li>
                                            @endforeach
                                        </ul>
                                    @endif
                                    </div>
                                    @if (filled($producto->imagen))
                                        {{-- Misma URL que el menú público: Storage::url sobre la ruta guardada del producto. --}}
                                        <img src="{{ \Illuminate\Support\Facades\Storage::url($producto->imagen) }}" alt="{{ $nombreProducto }}" width="96" height="96" loading="lazy" decoding="async" class="size-20 shrink-0 rounded-2xl bg-crema object-cover sm:size-24">
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        @else
            <p class="text-lg text-suave">El menú se está actualizando. Vuelve pronto.</p>
        @endif

        <p class="mt-14">
            <a href="{{ route('menu.publico', ['slug' => $restaurante->slug]) }}" class="inline-flex min-h-12 items-center gap-2 rounded-full bg-tinta px-7 font-semibold text-papel hover:bg-terracota">
                Ver el menú digital completo<x-sitio.icono nombre="flecha" class="size-5" />
            </a>
        </p>
    </div>
</section>
