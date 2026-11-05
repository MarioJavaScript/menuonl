@php
    /*
     * Menú de la plantilla J · Mariscos. Recibe lo mismo que menu.publico y no usa menuonl.css ni el estilo (tema) del restaurante.
     * Contacto y horario salen de SitioPublicoDatos, igual que en el sitio. Las categorías son enlaces ancla (funcionan sin JavaScript);
     * sitio-mariscos.js solo marca la que está a la vista. El pedido por WhatsApp usa la misma configuración que menu.publico
     * (pedidos activos, número de pedidos y horario) y el controlador compartido (resources/js/sitio-mariscos-pedido.js).
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
<x-sitio.mariscos.layout :r="$restaurante" :titulo="$restaurante->nombre.' · Menú'" :descripcion="$restaurante->descripcion" pagina="menu" :lang="str_replace('_', '-', $idioma)" :js="$categoriasConPlatos->count() > 1" :pedido="$puedePedir">
<div class="mar-marino mar-menu-marino">
    <x-sitio.mariscos.cabecera :d="$d" actual="menu" :estado="$estaAbierto" />

    <div class="mar-contenedor mar-menu-cabeza" id="menu">
        <h1 id="menu-titulo">Menú</h1>
        @if (filled($restaurante->eslogan))<p class="mar-menu-cabeza__sub">{{ $restaurante->eslogan }}</p>@endif
        @if (count($idiomasDisponibles) > 1)
            <nav class="mar-idiomas" aria-label="Selector de idioma">
                @foreach ($idiomasDisponibles as $opcion)
                    <a class="mar-idiomas__enlace" href="{{ route('menu.publico', ['slug' => $restaurante->slug, 'lang' => $opcion]) }}" hreflang="{{ $opcion }}" aria-label="Ver el menú en {{ $nombreIdiomas[$opcion] ?? strtoupper($opcion) }}" @if ($idioma === $opcion) aria-current="page" @endif>{{ $opcion }}</a>
                @endforeach
            </nav>
        @endif
    </div>
</div>

<main id="principal" tabindex="-1" aria-labelledby="menu-titulo">
    @if ($categoriasConPlatos->count() > 1)
        <nav class="mar-categorias" aria-label="Categorías" data-mar-categorias>
            <ul class="mar-categorias__lista" role="list">
                @foreach ($categoriasConPlatos as $categoria)
                    <li><a class="mar-categorias__enlace" href="#categoria-{{ $categoria->id }}">{{ $nombreCategoria($categoria) }}</a></li>
                @endforeach
            </ul>
        </nav>
    @endif

    <div class="mar-contenedor mar-seccion mar-seccion--menu">
        @if ($puedePedir)
            <p class="mar-lead">Toca "+" para armar tu pedido y envíalo por WhatsApp.</p>
            <p><a class="mar-boton" href="https://wa.me/{{ $numeroPedidos }}" rel="noopener" data-mar-sin-js>Pedir por WhatsApp</a></p>
        @elseif ($pedidosHabilitados)
            <p class="mar-aviso" role="status">Estamos cerrados ahora. Puedes ver el menú; los pedidos se activan al abrir.</p>
        @endif

        @if ($categoriasConPlatos->isEmpty())
            <p class="mar-estado-menu">Este menú se está preparando.</p>
        @else
            @foreach ($categoriasConPlatos as $categoria)
                <section class="mar-grupo" id="categoria-{{ $categoria->id }}" aria-labelledby="categoria-titulo-{{ $categoria->id }}">
                    <h2 class="mar-grupo__titulo" id="categoria-titulo-{{ $categoria->id }}">{{ $nombreCategoria($categoria) }}</h2>
                    <ul class="mar-lista" role="list">
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
                                <article @class(['mar-fila', 'mar-fila--variantes' => $conVariantes, 'mar-fila--agotado' => ! $producto->disponible])>
                                    <x-sitio.imagen :ruta="$producto->imagen" :alt="$nombrePlato" :ancho="144" :alto="144" sizes="72px" class="mar-fila__foto" />
                                    <div class="mar-fila__cuerpo">
                                        <div class="mar-fila__cabeza">
                                            <h3 class="mar-fila__nombre">{{ $nombrePlato }}</h3>
                                            @unless ($conVariantes)
                                                <p class="mar-precio">
                                                    {{ $formato($producto->precioVigente()) }}
                                                    @if ($enOferta)<s class="mar-precio__antes"><span class="mar-solo-lectores">Precio anterior </span>{{ $formato($producto->precio) }}</s>@endif
                                                </p>
                                            @endunless
                                        </div>
                                        @if (! $producto->disponible || $producto->etiquetas->isNotEmpty())
                                            <ul class="mar-etiquetas" role="list">
                                                @if (! $producto->disponible)<li class="mar-etiqueta mar-etiqueta--agotado">Agotado</li>@endif
                                                @foreach ($producto->etiquetas as $etiqueta)<li class="mar-etiqueta">{{ \App\Services\Catalogos::nombreEtiqueta((string) $etiqueta->codigo) }}</li>@endforeach
                                            </ul>
                                        @endif
                                        @if (filled($traduccion?->descripcion))<p class="mar-fila__desc">{{ $traduccion->descripcion }}</p>@endif
                                        @if ($conVariantes)
                                            <ul class="mar-variantes" role="list">
                                                @foreach ($producto->variantes as $variante)
                                                    @php
                                                        $nombreVariante = (string) ($variante->traduccion($idioma)?->nombre ?: 'Opción');
                                                        $articuloVariante = $agregar($producto, $nombrePlato, $variante, $nombreVariante);
                                                    @endphp
                                                    <li class="mar-variantes__fila">
                                                        <span class="mar-variantes__nombre">{{ $variante->traduccion($idioma)?->nombre }}</span>
                                                        <span class="mar-precio">{{ $formato($variante->precio) }}</span>
                                                        @if ($articuloVariante !== null)
                                                            <button class="mar-mas" type="button" hidden data-mar-agregar data-producto="{{ $articuloVariante['producto'] }}" data-variante="{{ $articuloVariante['variante'] }}" data-nombre="{{ $articuloVariante['nombre'] }}" data-nombre-variante="{{ $articuloVariante['nombreVariante'] }}" data-precio="{{ $articuloVariante['precio'] }}" aria-label="Agregar {{ $nombrePlato }} ({{ $nombreVariante }}) al pedido"><span aria-hidden="true">+</span></button>
                                                        @elseif ($puedePedir)
                                                            <span class="mar-etiqueta mar-etiqueta--agotado">Agotado</span>
                                                        @endif
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </div>
                                    @if ($articulo !== null)
                                        <button class="mar-mas" type="button" hidden data-mar-agregar data-producto="{{ $articulo['producto'] }}" data-variante="" data-nombre="{{ $articulo['nombre'] }}" data-nombre-variante="" data-precio="{{ $articulo['precio'] }}" aria-label="Agregar {{ $nombrePlato }} al pedido"><span aria-hidden="true">+</span></button>
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
    <x-sitio.mariscos.pedido :restaurante="$restaurante" :numero="$numeroPedidos" :idioma="$idioma" />
@endif

<x-sitio.mariscos.visita :d="$d" />
<x-sitio.mariscos.pie :d="$d" />
</x-sitio.mariscos.layout>
