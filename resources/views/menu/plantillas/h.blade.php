@php
    /*
     * Menú de la plantilla H · Pizzería. Recibe lo mismo que menu.publico y no usa menuonl.css ni el estilo (tema) del restaurante.
     * Contacto y horario salen de SitioPublicoDatos, igual que en el sitio. El pedido por WhatsApp usa la misma configuración que
     * menu.publico (pedidos activos, número de pedidos y horario); su script y su formato de mensaje están en resources/js/sitio-pizzeria-pedido*.js.
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
<x-sitio.pizzeria.layout :r="$restaurante" :titulo="$restaurante->nombre.' · Menú'" :descripcion="$restaurante->descripcion" pagina="menu" :lang="str_replace('_', '-', $idioma)" :js="true" :pedido="$puedePedir">
<x-sitio.pizzeria.cabecera :d="$d" actual="menu" :mesa="$mesa" />

<main id="principal" tabindex="-1">
    <section class="piz-contenedor piz-menu-cabeza" id="menu" aria-labelledby="menu-titulo">
        <h1 id="menu-titulo">Menú</h1>
        @if (filled($restaurante->eslogan))<p class="piz-menu-cabeza__sub">{{ $restaurante->eslogan }}</p>@endif
        @if (count($idiomasDisponibles) > 1)
            <nav class="piz-idiomas" aria-label="Selector de idioma">
                @foreach ($idiomasDisponibles as $opcion)
                    <a class="piz-idiomas__enlace" href="{{ route('menu.publico', ['slug' => $restaurante->slug, 'lang' => $opcion]) }}" hreflang="{{ $opcion }}" aria-label="Ver el menú en {{ $nombreIdiomas[$opcion] ?? strtoupper($opcion) }}" @if ($idioma === $opcion) aria-current="page" @endif>{{ $opcion }}</a>
                @endforeach
            </nav>
        @endif
    </section>

    <div class="piz-contenedor piz-seccion piz-seccion--menu">
        @if ($puedePedir)
            <p class="piz-lead">{{ filled($mesa) ? 'Mesa '.$mesa.': agrega lo que se te antoje y envía el pedido por WhatsApp.' : 'Agrega lo que se te antoje y envía el pedido por WhatsApp.' }}</p>
            <p><a class="piz-boton" href="https://wa.me/{{ $numeroPedidos }}" rel="noopener" data-piz-sin-js>Pedir por WhatsApp</a></p>
        @elseif ($pedidosHabilitados)
            <p class="piz-aviso" role="status">Estamos cerrados ahora. Puedes ver el menú; los pedidos se activan al abrir.</p>
        @endif

        @if (! $hayPlatos)
            <p class="piz-estado">Este menú se está preparando.</p>
        @else
            @if ($categoriasConPlatos->count() > 1)
                <div class="piz-filtros" role="group" aria-label="Filtrar por categoría" data-piz-filtros hidden>
                    <button class="piz-chip" type="button" aria-pressed="true" data-piz-filtro="">Todos</button>
                    @foreach ($categoriasConPlatos as $categoria)
                        <button class="piz-chip" type="button" aria-pressed="false" data-piz-filtro="{{ $categoria->id }}">{{ $nombreCategoria($categoria) }}</button>
                    @endforeach
                </div>
                <p class="piz-solo-lectores" role="status" aria-live="polite" data-piz-aviso></p>
            @endif

            @foreach ($categoriasConPlatos as $categoria)
                <section class="piz-grupo" data-piz-grupo="{{ $categoria->id }}" aria-labelledby="categoria-{{ $categoria->id }}">
                    <h2 class="piz-grupo__titulo" id="categoria-{{ $categoria->id }}">{{ $nombreCategoria($categoria) }}</h2>
                    <ul class="piz-rejilla" role="list">
                        @foreach ($categoria->productos as $producto)
                            @php
                                $traduccion = $producto->traduccion($idioma);
                                $nombrePlato = (string) ($traduccion?->nombre ?? '');
                                $enOferta = $producto->precioVigente() !== (string) $producto->precio;
                            @endphp
                            @continue($nombrePlato === '')
                            <li>
                                <article @class(['piz-tarjeta', 'piz-tarjeta--agotado' => ! $producto->disponible])>
                                    <x-sitio.imagen :ruta="$producto->imagen" :alt="$nombrePlato" :ancho="800" :alto="800" sizes="(min-width:1024px) 20vw, (min-width:768px) 30vw, 30vw" class="piz-tarjeta__foto" />
                                    <div class="piz-tarjeta__cuerpo">
                                        <h3 class="piz-tarjeta__nombre">{{ $nombrePlato }}</h3>
                                        @if (! $producto->disponible || $producto->etiquetas->isNotEmpty())
                                            <ul class="piz-etiquetas" role="list">
                                                @if (! $producto->disponible)<li class="piz-etiqueta piz-etiqueta--agotado">Agotado</li>@endif
                                                @foreach ($producto->etiquetas as $etiqueta)<li class="piz-etiqueta">{{ \App\Services\Catalogos::nombreEtiqueta((string) $etiqueta->codigo) }}</li>@endforeach
                                            </ul>
                                        @endif
                                        @if (filled($traduccion?->descripcion))<p class="piz-tarjeta__desc">{{ $traduccion->descripcion }}</p>@endif
                                        @if ($producto->tieneVariantes())
                                            <ul class="piz-variantes" role="list">
                                                @foreach ($producto->variantes as $variante)
                                                    @php
                                                        $nombreVariante = (string) ($variante->traduccion($idioma)?->nombre ?: 'Opción');
                                                        $articulo = $agregar($producto, $nombrePlato, $variante, $nombreVariante);
                                                    @endphp
                                                    <li class="piz-variantes__fila">
                                                        <span class="piz-variantes__nombre">{{ $variante->traduccion($idioma)?->nombre }}</span>
                                                        <span class="piz-precio">{{ $formato($variante->precio) }}</span>
                                                        @if ($articulo !== null)
                                                            <button class="piz-boton piz-boton--chico" type="button" hidden data-piz-agregar data-producto="{{ $articulo['producto'] }}" data-variante="{{ $articulo['variante'] }}" data-nombre="{{ $articulo['nombre'] }}" data-nombre-variante="{{ $articulo['nombreVariante'] }}" data-precio="{{ $articulo['precio'] }}" aria-label="Agregar {{ $nombrePlato }} ({{ $nombreVariante }}) al pedido">Agregar</button>
                                                        @elseif ($puedePedir)
                                                            <span class="piz-etiqueta piz-etiqueta--agotado">Agotado</span>
                                                        @endif
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @else
                                            <div class="piz-tarjeta__acciones">
                                                <p class="piz-precio">
                                                    {{ $formato($producto->precioVigente()) }}
                                                    @if ($enOferta)<s class="piz-precio__antes"><span class="piz-solo-lectores">Precio anterior </span>{{ $formato($producto->precio) }}</s>@endif
                                                </p>
                                                @if (($articulo = $agregar($producto, $nombrePlato)) !== null)
                                                    <button class="piz-boton piz-boton--chico" type="button" hidden data-piz-agregar data-producto="{{ $articulo['producto'] }}" data-variante="" data-nombre="{{ $articulo['nombre'] }}" data-nombre-variante="" data-precio="{{ $articulo['precio'] }}" aria-label="Agregar {{ $nombrePlato }} al pedido">Agregar</button>
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
    <x-sitio.pizzeria.pedido :restaurante="$restaurante" :numero="$numeroPedidos" :idioma="$idioma" :mesa="$mesa" />
@endif

<x-sitio.pizzeria.contacto :d="$d" />
<x-sitio.pizzeria.pie :d="$d" />
</x-sitio.pizzeria.layout>
