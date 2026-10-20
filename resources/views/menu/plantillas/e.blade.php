@php
    /*
     * Menú de la plantilla E · Cafetería. Recibe lo mismo que menu.publico y no usa menuonl.css ni el estilo (tema) del restaurante.
     * Contacto y horario salen de SitioPublicoDatos, igual que en el sitio. Sin carrito: el pedido se hace por WhatsApp.
     */
    $d = app(\App\Services\SitioPublicoDatos::class)->para($restaurante);
    $formato = fn ($valor): string => \App\Services\FormatoMoneda::formatear($valor, $restaurante->moneda, $idioma);
    $nombreIdiomas = \App\Services\Catalogos::idiomas();

    $categoriasConPlatos = $categorias->filter(fn ($categoria): bool => $categoria->productos->isNotEmpty())->values();
    $platos = $categoriasConPlatos->flatMap(fn ($categoria) => $categoria->productos->map(fn ($producto): array => ['producto' => $producto, 'categoria' => $categoria->id]))->values();
    $nombreCategoria = fn ($categoria): string => (string) ($categoria->traduccion($idioma)?->nombre ?? '');

    $nuevo = $destacados->first(fn ($producto): bool => $producto->etiquetas->contains('codigo', 'nuevo'));
    $destacado = $nuevo ?? $destacados->first(fn ($producto): bool => filled($producto->imagen)) ?? $destacados->first();
    $nombreDestacado = $destacado?->traduccion($idioma)?->nombre;
    $puedePedirPorWhatsapp = $restaurante->pedidos_activos && filled($d['whatsapp_digitos']);
@endphp
<x-sitio.cafeteria.layout :r="$restaurante" :titulo="$restaurante->nombre.' · Menú'" :descripcion="$restaurante->descripcion" pagina="menu" :lang="str_replace('_', '-', $idioma)" :js="true">
<div class="cal-portada cal-portada--corta">
    <x-sitio.cafeteria.cabecera :d="$d" actual="menu" />
</div>

<main id="principal" tabindex="-1">
    <section class="cal-menu" id="menu" aria-labelledby="menu-titulo">
        <div class="cal-menu__encabezado">
            <div class="cal-menu__titulos">
                <h1 class="cal-titulo" id="menu-titulo">Menú</h1>
                <p class="cal-acento" aria-hidden="true">{{ $restaurante->nombre }}</p>
            </div>
            <div class="cal-menu__acciones">
                @if (count($idiomasDisponibles) > 1)
                    <nav class="cal-idiomas" aria-label="Selector de idioma">
                        @foreach ($idiomasDisponibles as $opcion)
                            <a class="cal-idiomas__enlace" href="{{ route('menu.publico', ['slug' => $restaurante->slug, 'lang' => $opcion]) }}" hreflang="{{ $opcion }}" aria-label="Ver el menú en {{ $nombreIdiomas[$opcion] ?? strtoupper($opcion) }}" @if ($idioma === $opcion) aria-current="page" @endif>{{ $opcion }}</a>
                        @endforeach
                    </nav>
                @endif
                @if ($puedePedirPorWhatsapp)
                    <a class="cal-boton cal-boton--arcilla" href="https://wa.me/{{ $d['whatsapp_digitos'] }}" rel="noopener">Pedir por WhatsApp</a>
                @endif
            </div>
        </div>

        @if ($platos->isEmpty())
            <p class="cal-vacio">Este menú se está preparando.</p>
        @else
            <h2 class="cal-visualmente-oculto">Platillos disponibles</h2>
            @if ($categoriasConPlatos->count() > 1)
                <div class="cal-categorias" role="group" aria-label="Categorías del menú" data-cal-categorias hidden>
                    <button class="cal-categoria" type="button" aria-pressed="true" data-categoria="todas">Todas</button>
                    @foreach ($categoriasConPlatos as $categoria)
                        <button class="cal-categoria" type="button" aria-pressed="false" data-categoria="{{ $categoria->id }}">{{ $nombreCategoria($categoria) }}</button>
                    @endforeach
                </div>
            @endif

            <div class="cal-carrusel" data-cal-carrusel>
                <button class="cal-flecha cal-flecha--prev" type="button" aria-label="Ver anteriores" data-cal-flecha="-1" hidden>
                    <svg class="cal-flecha__svg" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M14.5 5.5 8 12l6.5 6.5"/></svg>
                </button>
                <ul class="cal-carrusel__pista" role="list" tabindex="0" aria-label="Platillos del menú. Desplázate con las flechas del teclado" data-cal-pista>
                    @foreach ($platos as $plato)
                        @php
                            $producto = $plato['producto'];
                            $traduccion = $producto->traduccion($idioma);
                            $nombrePlato = (string) ($traduccion?->nombre ?? '');
                            $enOferta = $producto->precioVigente() !== (string) $producto->precio;
                        @endphp
                        @continue($nombrePlato === '')
                        <li data-cal-plato data-categoria="{{ $plato['categoria'] }}">
                            <article @class(['cal-tarjeta', 'cal-tarjeta--agotado' => ! $producto->disponible])>
                                <div class="cal-tarjeta__marco">
                                    <x-sitio.imagen :ruta="$producto->imagen" :alt="$nombrePlato" :ancho="600" :alto="600" sizes="(min-width:768px) 21vw, 72vw" class="cal-tarjeta__foto" />
                                </div>
                                <div class="cal-tarjeta__cuerpo">
                                    <h3 class="cal-tarjeta__nombre">{{ $nombrePlato }}</h3>
                                    @if (filled($traduccion?->descripcion))<p class="cal-tarjeta__descripcion">{{ $traduccion->descripcion }}</p>@endif
                                    @if (! $producto->disponible || $producto->etiquetas->isNotEmpty())
                                        <ul class="cal-marcas" role="list">
                                            @if (! $producto->disponible)<li class="cal-marcas__agotado">Agotado</li>@endif
                                            @foreach ($producto->etiquetas as $etiqueta)<li>{{ \App\Services\Catalogos::nombreEtiqueta((string) $etiqueta->codigo) }}</li>@endforeach
                                        </ul>
                                    @endif
                                </div>
                                <div class="cal-tarjeta__pie">
                                    @if ($producto->tieneVariantes())
                                        <ul class="cal-variantes" role="list">
                                            @foreach ($producto->variantes as $variante)
                                                <li><span>{{ $variante->traduccion($idioma)?->nombre }}</span> <span class="cal-precio">{{ $formato($variante->precio) }}</span></li>
                                            @endforeach
                                        </ul>
                                    @else
                                        <p class="cal-precio-linea">
                                            @if ($enOferta)<s class="cal-precio-antes"><span class="cal-visualmente-oculto">Precio anterior </span>{{ $formato($producto->precio) }}</s>@endif
                                            <span class="cal-precio">{{ $formato($producto->precioVigente()) }}</span>
                                        </p>
                                    @endif
                                </div>
                            </article>
                        </li>
                    @endforeach
                </ul>
                <button class="cal-flecha cal-flecha--sig" type="button" aria-label="Ver más platillos" data-cal-flecha="1" hidden>
                    <svg class="cal-flecha__svg" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M9.5 5.5 16 12l-6.5 6.5"/></svg>
                </button>
                <button class="cal-boton cal-boton--arcilla cal-carrusel__mas" type="button" data-cal-mas aria-expanded="false" hidden>Más</button>
            </div>
            <p class="cal-visualmente-oculto" role="status" aria-live="polite" data-cal-aviso></p>
        @endif
    </section>

    @if ($destacado !== null && filled($nombreDestacado))
        @php
            $descripcionDestacado = $destacado->traduccion($idioma)?->descripcion;
            $ofertaDestacado = $destacado->precioVigente() !== (string) $destacado->precio;
        @endphp
        <section @class(['cal-nueva', 'cal-nueva--sin-foto' => blank($destacado->imagen)]) aria-labelledby="nueva-titulo">
            <div class="cal-nueva__texto">
                <h2 class="cal-nueva__titulo" id="nueva-titulo">
                    <span class="cal-nueva__nueva">{{ $nuevo !== null ? 'Nuevo' : 'Recomendado' }}</span>
                    <span class="cal-nueva__bebida">{{ $nombreDestacado }}</span>
                </h2>
                @if (filled($descripcionDestacado))<p class="cal-nueva__descripcion">{{ $descripcionDestacado }}</p>@endif
                @unless ($destacado->tieneVariantes())
                    <div class="cal-nueva__precios">
                        <span class="cal-nueva__precio">{{ $formato($destacado->precioVigente()) }}</span>
                        @if ($ofertaDestacado)<s class="cal-nueva__anterior"><span class="cal-visualmente-oculto">Precio anterior </span>{{ $formato($destacado->precio) }}</s>@endif
                    </div>
                @endunless
                @if ($destacado->etiquetas->isNotEmpty())
                    <ul class="cal-marcas" role="list">
                        @foreach ($destacado->etiquetas as $etiqueta)<li>{{ \App\Services\Catalogos::nombreEtiqueta((string) $etiqueta->codigo) }}</li>@endforeach
                    </ul>
                @endif
            </div>
            @if (filled($destacado->imagen))
                <x-sitio.imagen :ruta="$destacado->imagen" :alt="$nombreDestacado" :ancho="800" :alto="1000" sizes="(min-width:900px) 30vw, 80vw" class="cal-nueva__foto" />
            @endif
        </section>
    @endif
</main>

<x-sitio.cafeteria.contacto :d="$d" />
<x-sitio.cafeteria.pie :d="$d" />
</x-sitio.cafeteria.layout>
