@php
    /*
     * Menú de la plantilla G · CafePastel. Recibe lo mismo que menu.publico y no usa menuonl.css ni el estilo (tema) del restaurante.
     * Contacto y horario salen de SitioPublicoDatos, igual que en el sitio. El pedido por WhatsApp usa la misma configuración que
     * menu.publico (pedidos activos, número de pedidos y horario); su script y su formato de mensaje están en resources/js/sitio-pastel-pedido*.js.
     */
    $d = app(\App\Services\SitioPublicoDatos::class)->para($restaurante);
    $formato = fn ($valor): string => \App\Services\FormatoMoneda::formatear($valor, $restaurante->moneda, $idioma);
    $nombreIdiomas = \App\Services\Catalogos::idiomas();

    $categoriasConPlatos = $categorias->filter(fn ($categoria): bool => $categoria->productos->isNotEmpty())->values();
    $nombreCategoria = fn ($categoria): string => (string) ($categoria->traduccion($idioma)?->nombre ?? '');

    $numeroPedidos = $restaurante->whatsappPedidosDigitos();
    $pedidosHabilitados = $restaurante->pedidos_activos && $numeroPedidos !== null;
    $puedePedir = $pedidosHabilitados && $estaAbierto;
    $agregar = function ($producto, string $nombre, ?object $variante = null, string $nombreVariante = '') use ($puedePedir): ?array {
        $disponible = $producto->disponible && ($variante === null || $variante->disponible);

        return $puedePedir && $disponible
            ? ['producto' => $producto->id, 'variante' => $variante?->id, 'nombre' => $nombre, 'nombreVariante' => $nombreVariante, 'precio' => $variante !== null ? (string) $variante->precio : $producto->precioVigente()]
            : null;
    };
    $hayPlatos = $categoriasConPlatos->contains(fn ($categoria): bool => $categoria->productos->contains(fn ($producto): bool => filled($producto->traduccion($idioma)?->nombre)));
@endphp
<x-sitio.pastel.layout :r="$restaurante" :titulo="$restaurante->nombre.' · Menú'" :descripcion="$restaurante->descripcion" pagina="menu" :lang="str_replace('_', '-', $idioma)" :js="true" :pedido="$puedePedir">
<x-sitio.pastel.cabecera :d="$d" actual="menu" :mesa="$mesa" />

<main id="principal" tabindex="-1">
    <section class="pas-franja pas-franja--abajo pas-hero pas-hero--menu" id="menu" aria-labelledby="menu-titulo">
        <div class="pas-contenedor">
            <h1 class="pas-hero__titulo" id="menu-titulo">Menú</h1>
            @if (filled($restaurante->eslogan))<p class="pas-hero__sub">{{ $restaurante->eslogan }}</p>@endif
            @if (count($idiomasDisponibles) > 1)
                <nav class="pas-idiomas" aria-label="Selector de idioma">
                    @foreach ($idiomasDisponibles as $opcion)
                        <a class="pas-idiomas__enlace" href="{{ route('menu.publico', ['slug' => $restaurante->slug, 'lang' => $opcion]) }}" hreflang="{{ $opcion }}" aria-label="Ver el menú en {{ $nombreIdiomas[$opcion] ?? strtoupper($opcion) }}" @if ($idioma === $opcion) aria-current="page" @endif>{{ $opcion }}</a>
                    @endforeach
                </nav>
            @endif
        </div>
    </section>

    <div class="pas-contenedor pas-seccion pas-seccion--menu">
        @if ($puedePedir)
            <p class="pas-lead">{{ filled($mesa) ? 'Mesa '.$mesa.': agrega lo que se te antoje y envía el pedido por WhatsApp.' : 'Agrega lo que se te antoje y envía el pedido por WhatsApp.' }}</p>
            <p><a class="pas-boton" href="https://wa.me/{{ $numeroPedidos }}" rel="noopener" data-pas-sin-js>Pedir por WhatsApp</a></p>
        @elseif ($pedidosHabilitados)
            <p class="pas-aviso" role="status">Estamos cerrados ahora. Puedes ver el menú; los pedidos se activan al abrir.</p>
        @endif

        @if (! $hayPlatos)
            <p class="pas-estado">Este menú se está preparando.</p>
        @else
            @if ($categoriasConPlatos->count() > 1)
                <div class="pas-filtros" role="group" aria-label="Filtrar por categoría" data-pas-filtros hidden>
                    <button class="pas-chip" type="button" aria-pressed="true" data-pas-filtro="">Todos</button>
                    @foreach ($categoriasConPlatos as $categoria)
                        <button class="pas-chip" type="button" aria-pressed="false" data-pas-filtro="{{ $categoria->id }}">{{ $nombreCategoria($categoria) }}</button>
                    @endforeach
                </div>
                <p class="pas-solo-lectores" role="status" aria-live="polite" data-pas-aviso></p>
            @endif

            @foreach ($categoriasConPlatos as $categoria)
                <section class="pas-grupo" data-pas-grupo="{{ $categoria->id }}" aria-labelledby="categoria-{{ $categoria->id }}">
                    <h2 class="pas-grupo__titulo" id="categoria-{{ $categoria->id }}">{{ $nombreCategoria($categoria) }}</h2>
                    <ul class="pas-lista" role="list">
                        @foreach ($categoria->productos as $producto)
                            @php
                                $traduccion = $producto->traduccion($idioma);
                                $nombrePlato = (string) ($traduccion?->nombre ?? '');
                                $enOferta = $producto->precioVigente() !== (string) $producto->precio;
                            @endphp
                            @continue($nombrePlato === '')
                            <li>
                                <article @class(['pas-plato', 'pas-plato--agotado' => ! $producto->disponible])>
                                    <div class="pas-plato__foto">
                                        <x-sitio.imagen :ruta="$producto->imagen" :alt="$nombrePlato" :ancho="900" :alto="675" sizes="(min-width:1024px) 30vw, (min-width:768px) 45vw, 90vw" class="pas-plato__imagen" />
                                        @unless ($producto->disponible)<div class="pas-plato__capa" aria-hidden="true">Agotado</div>@endunless
                                    </div>
                                    <div class="pas-plato__cuerpo">
                                        <h3>{{ $nombrePlato }}</h3>
                                        @if (filled($traduccion?->descripcion))<p class="pas-plato__desc">{{ $traduccion->descripcion }}</p>@endif
                                        @if (! $producto->disponible || $producto->etiquetas->isNotEmpty())
                                            <ul class="pas-etiquetas" role="list">
                                                @if (! $producto->disponible)<li class="pas-etiqueta pas-etiqueta--agotado">Agotado</li>@endif
                                                @foreach ($producto->etiquetas as $etiqueta)<li class="pas-etiqueta">{{ \App\Services\Catalogos::nombreEtiqueta((string) $etiqueta->codigo) }}</li>@endforeach
                                            </ul>
                                        @endif
                                        @if ($producto->tieneVariantes())
                                            <ul class="pas-variantes" role="list">
                                                @foreach ($producto->variantes as $variante)
                                                    @php
                                                        $nombreVariante = (string) ($variante->traduccion($idioma)?->nombre ?: 'Opción');
                                                        $articulo = $agregar($producto, $nombrePlato, $variante, $nombreVariante);
                                                    @endphp
                                                    <li class="pas-variantes__fila">
                                                        <span class="pas-variantes__nombre">{{ $variante->traduccion($idioma)?->nombre }}</span>
                                                        <span class="pas-precio">{{ $formato($variante->precio) }}</span>
                                                        @if ($articulo !== null)
                                                            <button class="pas-boton pas-boton--chico" type="button" hidden data-pas-agregar data-producto="{{ $articulo['producto'] }}" data-variante="{{ $articulo['variante'] }}" data-nombre="{{ $articulo['nombre'] }}" data-nombre-variante="{{ $articulo['nombreVariante'] }}" data-precio="{{ $articulo['precio'] }}" aria-label="Agregar {{ $nombrePlato }} ({{ $nombreVariante }}) al pedido">Agregar</button>
                                                        @elseif ($puedePedir)
                                                            <span class="pas-etiqueta pas-etiqueta--agotado">Agotado</span>
                                                        @endif
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @else
                                            <div class="pas-plato__pie">
                                                <p class="pas-precio">
                                                    {{ $formato($producto->precioVigente()) }}
                                                    @if ($enOferta)<s class="pas-precio__anterior"><span class="pas-solo-lectores">Precio anterior </span>{{ $formato($producto->precio) }}</s>@endif
                                                </p>
                                                @if (($articulo = $agregar($producto, $nombrePlato)) !== null)
                                                    <button class="pas-boton pas-boton--chico" type="button" hidden data-pas-agregar data-producto="{{ $articulo['producto'] }}" data-variante="" data-nombre="{{ $articulo['nombre'] }}" data-nombre-variante="" data-precio="{{ $articulo['precio'] }}" aria-label="Agregar {{ $nombrePlato }} al pedido">Agregar</button>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                </article>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
        @endif
    </div>
</main>

@if ($puedePedir)
    <x-sitio.pastel.pedido :restaurante="$restaurante" :numero="$numeroPedidos" :idioma="$idioma" :mesa="$mesa" />
@endif

<x-sitio.pastel.contacto :d="$d" />
<x-sitio.pastel.pie :d="$d" />
</x-sitio.pastel.layout>
