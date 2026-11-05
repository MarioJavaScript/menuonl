@php
    /*
     * Menú de la plantilla I · Japonés. Recibe lo mismo que menu.publico y no usa menuonl.css ni el estilo (tema) del restaurante.
     * Contacto y horario salen de SitioPublicoDatos, igual que en el sitio. Las categorías son enlaces ancla (funcionan sin JavaScript);
     * sitio-japones.js solo marca la que está a la vista. El pedido por WhatsApp usa la misma configuración que menu.publico
     * (pedidos activos, número de pedidos y horario) y el controlador compartido (resources/js/sitio-japones-pedido.js).
     * El kanji 品書 ("menú") es decorativo y queda oculto a lectores de pantalla.
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
<x-sitio.japones.layout :r="$restaurante" :titulo="$restaurante->nombre.' · Menú'" :descripcion="$restaurante->descripcion" pagina="menu" :lang="str_replace('_', '-', $idioma)" :js="$categoriasConPlatos->count() > 1" :pedido="$puedePedir">
<x-sitio.japones.cabecera :d="$d" actual="menu" :mesa="$mesa" :estado="$estaAbierto" />

<main id="principal" tabindex="-1">
    <section class="jap-contenedor jap-menu-cabeza" id="menu" aria-labelledby="menu-titulo">
        <div class="jap-menu-cabeza__fila">
            <h1 id="menu-titulo">Menú</h1>
            <p class="jap-kanji jap-kanji--menu" aria-hidden="true">品書</p>
        </div>
        @if (filled($restaurante->eslogan))<p class="jap-menu-cabeza__sub">{{ $restaurante->eslogan }}</p>@endif
        @if (count($idiomasDisponibles) > 1)
            <nav class="jap-idiomas" aria-label="Selector de idioma">
                @foreach ($idiomasDisponibles as $opcion)
                    <a class="jap-idiomas__enlace" href="{{ route('menu.publico', ['slug' => $restaurante->slug, 'lang' => $opcion]) }}" hreflang="{{ $opcion }}" aria-label="Ver el menú en {{ $nombreIdiomas[$opcion] ?? strtoupper($opcion) }}" @if ($idioma === $opcion) aria-current="page" @endif>{{ $opcion }}</a>
                @endforeach
            </nav>
        @endif
    </section>

    @if ($categoriasConPlatos->count() > 1)
        <nav class="jap-categorias" aria-label="Categorías" data-jap-categorias>
            <ul class="jap-categorias__lista" role="list">
                @foreach ($categoriasConPlatos as $categoria)
                    <li><a class="jap-categorias__enlace" href="#categoria-{{ $categoria->id }}">{{ $nombreCategoria($categoria) }}</a></li>
                @endforeach
            </ul>
        </nav>
    @endif

    <div class="jap-contenedor jap-seccion jap-seccion--menu">
        @if ($puedePedir)
            <p class="jap-lead">Toca "+" para agregar al pedido y envíalo por WhatsApp.</p>
            <p><a class="jap-boton" href="https://wa.me/{{ $numeroPedidos }}" rel="noopener" data-jap-sin-js>Pedir por WhatsApp</a></p>
        @elseif ($pedidosHabilitados)
            <p class="jap-aviso" role="status">Estamos cerrados ahora. Puedes ver el menú; los pedidos se activan al abrir.</p>
        @endif

        @if ($categoriasConPlatos->isEmpty())
            <p class="jap-estado-menu">Este menú se está preparando.</p>
        @else
            @foreach ($categoriasConPlatos as $categoria)
                <section class="jap-grupo" id="categoria-{{ $categoria->id }}" aria-labelledby="categoria-titulo-{{ $categoria->id }}">
                    <h2 class="jap-grupo__titulo" id="categoria-titulo-{{ $categoria->id }}">{{ $nombreCategoria($categoria) }}</h2>
                    <ul class="jap-lista" role="list">
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
                                <article @class(['jap-fila', 'jap-fila--variantes' => $conVariantes, 'jap-fila--agotado' => ! $producto->disponible])>
                                    <x-sitio.imagen :ruta="$producto->imagen" :alt="$nombrePlato" :ancho="144" :alto="144" sizes="72px" class="jap-fila__foto" />
                                    <div class="jap-fila__cuerpo">
                                        <div class="jap-fila__cabeza">
                                            <h3 class="jap-fila__nombre">{{ $nombrePlato }}</h3>
                                            @unless ($conVariantes)
                                                <p class="jap-precio">
                                                    {{ $formato($producto->precioVigente()) }}
                                                    @if ($enOferta)<s class="jap-precio__antes"><span class="jap-solo-lectores">Precio anterior </span>{{ $formato($producto->precio) }}</s>@endif
                                                </p>
                                            @endunless
                                        </div>
                                        @if (! $producto->disponible || $producto->etiquetas->isNotEmpty())
                                            <ul class="jap-etiquetas" role="list">
                                                @if (! $producto->disponible)<li class="jap-etiqueta jap-etiqueta--agotado">Agotado</li>@endif
                                                @foreach ($producto->etiquetas as $etiqueta)<li class="jap-etiqueta">{{ \App\Services\Catalogos::nombreEtiqueta((string) $etiqueta->codigo) }}</li>@endforeach
                                            </ul>
                                        @endif
                                        @if (filled($traduccion?->descripcion))<p class="jap-fila__desc">{{ $traduccion->descripcion }}</p>@endif
                                        @if ($conVariantes)
                                            <ul class="jap-variantes" role="list">
                                                @foreach ($producto->variantes as $variante)
                                                    @php
                                                        $nombreVariante = (string) ($variante->traduccion($idioma)?->nombre ?: 'Opción');
                                                        $articuloVariante = $agregar($producto, $nombrePlato, $variante, $nombreVariante);
                                                    @endphp
                                                    <li class="jap-variantes__fila">
                                                        <span class="jap-variantes__nombre">{{ $variante->traduccion($idioma)?->nombre }}</span>
                                                        <span class="jap-precio">{{ $formato($variante->precio) }}</span>
                                                        @if ($articuloVariante !== null)
                                                            <button class="jap-mas" type="button" hidden data-jap-agregar data-producto="{{ $articuloVariante['producto'] }}" data-variante="{{ $articuloVariante['variante'] }}" data-nombre="{{ $articuloVariante['nombre'] }}" data-nombre-variante="{{ $articuloVariante['nombreVariante'] }}" data-precio="{{ $articuloVariante['precio'] }}" aria-label="Agregar {{ $nombrePlato }} ({{ $nombreVariante }}) al pedido"><span aria-hidden="true">+</span></button>
                                                        @elseif ($puedePedir)
                                                            <span class="jap-etiqueta jap-etiqueta--agotado">Agotado</span>
                                                        @endif
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </div>
                                    @if ($articulo !== null)
                                        <button class="jap-mas" type="button" hidden data-jap-agregar data-producto="{{ $articulo['producto'] }}" data-variante="" data-nombre="{{ $articulo['nombre'] }}" data-nombre-variante="" data-precio="{{ $articulo['precio'] }}" aria-label="Agregar {{ $nombrePlato }} al pedido"><span aria-hidden="true">+</span></button>
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
    <x-sitio.japones.pedido :restaurante="$restaurante" :numero="$numeroPedidos" :idioma="$idioma" />
@endif

<x-sitio.japones.visita :d="$d" />
<x-sitio.japones.pie :d="$d" />
</x-sitio.japones.layout>
