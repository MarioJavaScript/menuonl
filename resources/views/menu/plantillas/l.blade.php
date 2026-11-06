@php
    /*
     * Menú de la plantilla L · Negro y esmeralda. Recibe lo mismo que menu.publico y no usa menuonl.css ni el estilo (tema) del restaurante.
     * Contacto y horario salen de SitioPublicoDatos, igual que en el sitio. Las categorías son enlaces ancla (funcionan sin JavaScript);
     * sitio-esmeralda.js solo marca la que está a la vista. El pedido por WhatsApp usa la misma configuración que menu.publico
     * (pedidos activos, número de pedidos y horario) y el controlador compartido (resources/js/sitio-esmeralda-pedido.js).
     * ?mesa= se ignora por completo: no se lee ni se muestra.
     */
    $d = app(\App\Services\SitioPublicoDatos::class)->para($restaurante);
    $formato = fn ($valor): string => \App\Services\FormatoMoneda::formatear($valor, $restaurante->moneda, $idioma);
    $nombreIdiomas = \App\Services\Catalogos::idiomas();

    $nombreCategoria = fn ($categoria): string => (string) ($categoria->traduccion($idioma)?->nombre ?? '');
    $categoriasConPlatos = $categorias
        ->filter(fn ($categoria): bool => $categoria->productos->contains(fn ($producto): bool => filled($producto->traduccion($idioma)?->nombre)))
        ->values();

    $numeroPedidos = $restaurante->whatsappPedidosDigitos();
    $pedidosHabilitados = $restaurante->pedidosHabilitados();
    $puedePedir = $pedidosHabilitados && $estaAbierto;
    $agregar = function ($producto, string $nombre, ?object $variante = null, string $nombreVariante = '') use ($puedePedir): ?array {
        $disponible = $producto->disponible && ($variante === null || $variante->disponible);

        return $puedePedir && $disponible
            ? ['producto' => $producto->id, 'variante' => $variante?->id, 'nombre' => $nombre, 'nombreVariante' => $nombreVariante, 'precio' => $variante !== null ? (string) $variante->precio : $producto->precioVigente()]
            : null;
    };
@endphp
<x-sitio.esmeralda.layout :r="$restaurante" :titulo="$restaurante->nombre.' · Menú'" :descripcion="$restaurante->descripcion" pagina="menu" :lang="str_replace('_', '-', $idioma)" :js="$categoriasConPlatos->count() > 1" :pedido="$puedePedir">
<div class="esm-banda">
    <x-sitio.esmeralda.cabecera :d="$d" actual="menu" :estado="$estaAbierto" />

    <div class="esm-contenedor esm-menu-cabeza" id="menu">
        <h1 id="menu-titulo">Menú</h1>
        @if (filled($restaurante->eslogan))<p class="esm-menu-cabeza__sub">{{ $restaurante->eslogan }}</p>@endif
        @if (count($idiomasDisponibles) > 1)
            <nav class="esm-idiomas" aria-label="Selector de idioma">
                @foreach ($idiomasDisponibles as $opcion)
                    <a class="esm-idiomas__enlace" href="{{ route('menu.publico', ['slug' => $restaurante->slug, 'lang' => $opcion]) }}" hreflang="{{ $opcion }}" aria-label="Ver el menú en {{ $nombreIdiomas[$opcion] ?? strtoupper($opcion) }}" @if ($idioma === $opcion) aria-current="page" @endif>{{ $opcion }}</a>
                @endforeach
            </nav>
        @endif
    </div>
</div>

<main id="principal" tabindex="-1" aria-labelledby="menu-titulo">
    @if ($categoriasConPlatos->count() > 1)
        <nav class="esm-categorias" aria-label="Categorías" data-esm-categorias>
            <ul class="esm-categorias__lista" role="list">
                @foreach ($categoriasConPlatos as $categoria)
                    <li><a class="esm-categorias__enlace" href="#categoria-{{ $categoria->id }}">{{ $nombreCategoria($categoria) }}</a></li>
                @endforeach
            </ul>
        </nav>
    @endif

    <div class="esm-contenedor esm-seccion esm-seccion--menu">
        @if ($puedePedir)
            <p class="esm-lead">Toca "+" para armar tu pedido y envíalo por WhatsApp.</p>
            <p><a class="esm-boton" href="https://wa.me/{{ $numeroPedidos }}" rel="noopener" data-esm-sin-js>Pedir por WhatsApp</a></p>
        @elseif ($pedidosHabilitados)
            <p class="esm-aviso" role="status">Estamos cerrados ahora. Puedes ver el menú; los pedidos se activan al abrir.</p>
        @endif

        @if ($categoriasConPlatos->isEmpty())
            <p class="esm-estado-menu">Este menú se está preparando.</p>
        @else
            @foreach ($categoriasConPlatos as $categoria)
                <section class="esm-grupo" id="categoria-{{ $categoria->id }}" aria-labelledby="categoria-titulo-{{ $categoria->id }}">
                    <h2 class="esm-grupo__titulo" id="categoria-titulo-{{ $categoria->id }}">{{ $nombreCategoria($categoria) }}</h2>
                    <ul class="esm-lista" role="list">
                        @foreach ($categoria->productos as $producto)
                            @php
                                $traduccion = $producto->traduccion($idioma);
                                $nombrePlato = (string) ($traduccion?->nombre ?? '');
                                $enOferta = $producto->precioVigente() !== (string) $producto->precio;
                                $conVariantes = $producto->tieneVariantes();
                                $articulo = $conVariantes ? null : $agregar($producto, $nombrePlato);
                            @endphp
                            @continue($nombrePlato === '')
                            <li>
                                <article @class(['esm-fila', 'esm-fila--variantes' => $conVariantes, 'esm-fila--agotado' => ! $producto->disponible])>
                                    @if (filled($producto->imagen))<x-sitio.imagen :ruta="$producto->imagen" :alt="$nombrePlato" :ancho="128" :alto="128" sizes="64px" class="esm-fila__foto" />@endif
                                    <div class="esm-fila__cuerpo">
                                        <div class="esm-fila__cabeza">
                                            <h3 class="esm-fila__nombre">{{ $nombrePlato }}</h3>
                                            @unless ($conVariantes)
                                                <p class="esm-precio">
                                                    {{ $formato($producto->precioVigente()) }}
                                                    @if ($enOferta)<s class="esm-precio__antes"><span class="esm-solo-lectores">Precio anterior </span>{{ $formato($producto->precio) }}</s>@endif
                                                </p>
                                            @endunless
                                        </div>
                                        @if (! $producto->disponible || $producto->etiquetas->isNotEmpty())
                                            <ul class="esm-etiquetas" role="list">
                                                @if (! $producto->disponible)<li class="esm-etiqueta esm-etiqueta--agotado">Agotado</li>@endif
                                                @foreach ($producto->etiquetas as $etiqueta)<li class="esm-etiqueta">{{ \App\Services\Catalogos::nombreEtiqueta((string) $etiqueta->codigo) }}</li>@endforeach
                                            </ul>
                                        @endif
                                        @if (filled($traduccion?->descripcion))<p class="esm-fila__desc">{{ $traduccion->descripcion }}</p>@endif
                                        @if ($conVariantes)
                                            <ul class="esm-variantes" role="list">
                                                @foreach ($producto->variantes as $variante)
                                                    @php
                                                        $nombreVariante = (string) ($variante->traduccion($idioma)?->nombre ?: 'Opción');
                                                        $articuloVariante = $agregar($producto, $nombrePlato, $variante, $nombreVariante);
                                                    @endphp
                                                    <li class="esm-variantes__fila">
                                                        <span class="esm-variantes__nombre">{{ $variante->traduccion($idioma)?->nombre }}</span>
                                                        <span class="esm-precio">{{ $formato($variante->precio) }}</span>
                                                        @if ($articuloVariante !== null)
                                                            <button class="esm-mas" type="button" hidden data-esm-agregar data-producto="{{ $articuloVariante['producto'] }}" data-variante="{{ $articuloVariante['variante'] }}" data-nombre="{{ $articuloVariante['nombre'] }}" data-nombre-variante="{{ $articuloVariante['nombreVariante'] }}" data-precio="{{ $articuloVariante['precio'] }}" aria-label="Agregar {{ $nombrePlato }} ({{ $nombreVariante }}) al pedido"><span aria-hidden="true">+</span></button>
                                                        @elseif ($puedePedir)
                                                            <span class="esm-etiqueta esm-etiqueta--agotado">Agotado</span>
                                                        @endif
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </div>
                                    @if ($articulo !== null)
                                        <button class="esm-mas" type="button" hidden data-esm-agregar data-producto="{{ $articulo['producto'] }}" data-variante="" data-nombre="{{ $articulo['nombre'] }}" data-nombre-variante="" data-precio="{{ $articulo['precio'] }}" aria-label="Agregar {{ $nombrePlato }} al pedido"><span aria-hidden="true">+</span></button>
                                    @endif
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
    <x-sitio.esmeralda.pedido :restaurante="$restaurante" :numero="$numeroPedidos" :idioma="$idioma" />
@endif

<x-sitio.esmeralda.visita :d="$d" />
<x-sitio.esmeralda.pie :d="$d" />
</x-sitio.esmeralda.layout>
