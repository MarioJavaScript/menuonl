@php
    /*
     * Menú de la plantilla F · Bar. Recibe lo mismo que menu.publico y no usa menuonl.css ni el estilo (tema) del restaurante.
     * Contacto y horario salen de SitioPublicoDatos, igual que en el sitio. El pedido por WhatsApp usa la misma configuración que
     * menu.publico (pedidos activos, número de pedidos y horario); su script y su formato de mensaje están en resources/js/sitio-bar-pedido*.js.
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
    $hayPlatos = $categoriasConPlatos->contains(fn ($categoria): bool => $categoria->productos->contains(fn ($producto): bool => $producto->traduccion($idioma)?->nombre !== null && $producto->traduccion($idioma)->nombre !== ''));
@endphp
<x-sitio.bar.layout :r="$restaurante" :titulo="$restaurante->nombre.' · Menú'" :descripcion="$restaurante->descripcion" pagina="menu" :lang="str_replace('_', '-', $idioma)" :js="true" :pedido="$puedePedir">
<x-sitio.bar.cabecera :d="$d" actual="menu" :mesa="$mesa" />

<main id="principal" class="bar-principal" tabindex="-1">
    <section class="bar-seccion" id="menu" aria-labelledby="menu-titulo">
        <div class="bar-menu-encabezado">
            <div>
                <h1 class="bar-titulo" id="menu-titulo">Menú</h1>
                <span class="bar-neon" aria-hidden="true"></span>
            </div>
            @if (count($idiomasDisponibles) > 1)
                <nav class="bar-idiomas" aria-label="Selector de idioma">
                    @foreach ($idiomasDisponibles as $opcion)
                        <a class="bar-idiomas__enlace" href="{{ route('menu.publico', ['slug' => $restaurante->slug, 'lang' => $opcion]) }}" hreflang="{{ $opcion }}" aria-label="Ver el menú en {{ $nombreIdiomas[$opcion] ?? strtoupper($opcion) }}" @if ($idioma === $opcion) aria-current="page" @endif>{{ $opcion }}</a>
                    @endforeach
                </nav>
            @endif
        </div>

        @if ($puedePedir)
            <p class="bar-lead">{{ filled($mesa) ? 'Mesa '.$mesa.': agrega lo que se te antoje y envía el pedido por WhatsApp.' : 'Agrega lo que se te antoje y envía el pedido por WhatsApp.' }}</p>
            <a class="bar-boton" href="https://wa.me/{{ $numeroPedidos }}" rel="noopener" data-bar-sin-js>Pedir por WhatsApp</a>
        @elseif ($pedidosHabilitados)
            <p class="bar-aviso" role="status">Estamos cerrados ahora. Puedes ver el menú; los pedidos se activan al abrir.</p>
        @endif

        @if (! $hayPlatos)
            <p class="bar-vacio">Este menú se está preparando.</p>
        @else
            @if ($categoriasConPlatos->count() > 1)
                <div class="bar-filtros" role="group" aria-label="Filtrar por categoría" data-bar-filtros hidden>
                    <button class="bar-filtro" type="button" aria-pressed="true" data-bar-filtro="">Todo</button>
                    @foreach ($categoriasConPlatos as $categoria)
                        <button class="bar-filtro" type="button" aria-pressed="false" data-bar-filtro="{{ $categoria->id }}">{{ $nombreCategoria($categoria) }}</button>
                    @endforeach
                </div>
                <p class="bar-solo-lectores" role="status" aria-live="polite" data-bar-aviso></p>
            @endif

            <div class="bar-menu">
                @foreach ($categoriasConPlatos as $categoria)
                    <section class="bar-grupo" data-bar-grupo="{{ $categoria->id }}" aria-labelledby="categoria-{{ $categoria->id }}">
                        <h2 class="bar-grupo__titulo" id="categoria-{{ $categoria->id }}">{{ $nombreCategoria($categoria) }}</h2>
                        <ul class="bar-menu__lista" role="list">
                            @foreach ($categoria->productos as $producto)
                                @php
                                    $traduccion = $producto->traduccion($idioma);
                                    $nombrePlato = (string) ($traduccion?->nombre ?? '');
                                    $enOferta = $producto->precioVigente() !== (string) $producto->precio;
                                @endphp
                                @continue($nombrePlato === '')
                                <li @class(['bar-plato', 'bar-plato--foto' => filled($producto->imagen), 'bar-plato--agotado' => ! $producto->disponible])>
                                    @if (filled($producto->imagen))
                                        <x-sitio.imagen :ruta="$producto->imagen" :alt="$nombrePlato" :ancho="176" :alto="176" sizes="88px" class="bar-plato__foto" />
                                    @endif
                                    <div class="bar-plato__cuerpo">
                                        <div class="bar-plato__fila">
                                            <h3 class="bar-plato__nombre">{{ $nombrePlato }}</h3>
                                            @unless ($producto->tieneVariantes())
                                                <span class="bar-punteado" aria-hidden="true"></span>
                                                <span class="bar-precio">
                                                    @if ($enOferta)<s class="bar-precio__antes"><span class="bar-solo-lectores">Precio anterior </span>{{ $formato($producto->precio) }}</s>@endif
                                                    {{ $formato($producto->precioVigente()) }}
                                                </span>
                                            @endunless
                                        </div>
                                        @if (filled($traduccion?->descripcion))<p class="bar-plato__desc">{{ $traduccion->descripcion }}</p>@endif
                                        @if (! $producto->disponible || $producto->etiquetas->isNotEmpty())
                                            <ul class="bar-etiquetas" role="list">
                                                @if (! $producto->disponible)<li class="bar-etiqueta bar-etiqueta--agotado">Agotado</li>@endif
                                                @foreach ($producto->etiquetas as $etiqueta)<li class="bar-etiqueta">{{ \App\Services\Catalogos::nombreEtiqueta((string) $etiqueta->codigo) }}</li>@endforeach
                                            </ul>
                                        @endif
                                        @if ($producto->tieneVariantes())
                                            <ul class="bar-variantes" role="list">
                                                @foreach ($producto->variantes as $variante)
                                                    @php
                                                        $nombreVariante = (string) ($variante->traduccion($idioma)?->nombre ?: 'Opción');
                                                        $articulo = $agregar($producto, $nombrePlato, $variante, $nombreVariante);
                                                    @endphp
                                                    <li class="bar-variantes__fila">
                                                        <span class="bar-variantes__nombre">{{ $variante->traduccion($idioma)?->nombre }}</span>
                                                        <span class="bar-punteado" aria-hidden="true"></span>
                                                        <span class="bar-precio">{{ $formato($variante->precio) }}</span>
                                                        @if ($articulo !== null)
                                                            <button class="bar-boton bar-boton--chico" type="button" hidden data-bar-agregar data-producto="{{ $articulo['producto'] }}" data-variante="{{ $articulo['variante'] }}" data-nombre="{{ $articulo['nombre'] }}" data-nombre-variante="{{ $articulo['nombreVariante'] }}" data-precio="{{ $articulo['precio'] }}" aria-label="Agregar {{ $nombrePlato }} ({{ $nombreVariante }}) al pedido">Agregar</button>
                                                        @elseif ($puedePedir)
                                                            <span class="bar-etiqueta bar-etiqueta--agotado">Agotado</span>
                                                        @endif
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @elseif (($articulo = $agregar($producto, $nombrePlato)) !== null)
                                            <div class="bar-plato__pedir">
                                                <button class="bar-boton bar-boton--chico" type="button" hidden data-bar-agregar data-producto="{{ $articulo['producto'] }}" data-variante="" data-nombre="{{ $articulo['nombre'] }}" data-nombre-variante="" data-precio="{{ $articulo['precio'] }}" aria-label="Agregar {{ $nombrePlato }} al pedido">Agregar</button>
                                            </div>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endforeach
            </div>
        @endif
    </section>
</main>

@if ($puedePedir)
    <x-sitio.bar.pedido :restaurante="$restaurante" :numero="$numeroPedidos" :idioma="$idioma" :mesa="$mesa" />
@endif

<x-sitio.bar.contacto :d="$d" />
<x-sitio.bar.pie :d="$d" />
</x-sitio.bar.layout>
