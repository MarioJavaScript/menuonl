@php
    /*
     * Menú de la plantilla N · Mexicana. Recibe lo mismo que menu.publico y no usa menuonl.css ni el estilo (tema) del restaurante.
     * Contacto y horario salen de SitioPublicoDatos, igual que en el sitio. Las categorías son enlaces ancla (funcionan sin JavaScript);
     * sitio-mexicana.js solo marca la que está a la vista. El pedido por WhatsApp usa la misma configuración que menu.publico
     * (pedidos activos, número de pedidos y horario) y el controlador compartido (resources/js/sitio-mexicana-pedido.js).
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
<x-sitio.mexicana.layout :r="$restaurante" :titulo="$restaurante->nombre.' · Menú'" :descripcion="$restaurante->descripcion" pagina="menu" :lang="str_replace('_', '-', $idioma)" :js="$categoriasConPlatos->count() > 1" :pedido="$puedePedir">
<div class="mex-banda">
    <x-sitio.mexicana.cabecera :d="$d" actual="menu" :estado="$estaAbierto" />

    <div class="mex-contenedor mex-menu-cabeza" id="menu">
        <h1 id="menu-titulo">Menú</h1>
        @if (count($idiomasDisponibles) > 1)
            <nav class="mex-idiomas" aria-label="Selector de idioma">
                @foreach ($idiomasDisponibles as $opcion)
                    <a class="mex-idiomas__enlace" href="{{ route('menu.publico', ['slug' => $restaurante->slug, 'lang' => $opcion]) }}" hreflang="{{ $opcion }}" aria-label="Ver el menú en {{ $nombreIdiomas[$opcion] ?? strtoupper($opcion) }}" @if ($idioma === $opcion) aria-current="page" @endif>{{ $opcion }}</a>
                @endforeach
            </nav>
        @endif
    </div>
</div>

<main id="principal" tabindex="-1" aria-labelledby="menu-titulo">
    @if ($categoriasConPlatos->count() > 1)
        <nav class="mex-categorias" aria-label="Categorías" data-mex-categorias>
            <ul class="mex-categorias__lista" role="list">
                @foreach ($categoriasConPlatos as $categoria)
                    <li><a class="mex-categorias__enlace" href="#categoria-{{ $categoria->id }}">{{ $nombreCategoria($categoria) }}</a></li>
                @endforeach
            </ul>
        </nav>
    @endif

    <div class="mex-contenedor mex-seccion mex-seccion--menu">
        @if ($puedePedir)
            <p class="mex-lead">Toca "+" para armar tu pedido y envíalo por WhatsApp.</p>
            <p><a class="mex-boton" href="https://wa.me/{{ $numeroPedidos }}" rel="noopener" data-mex-sin-js>Pedir por WhatsApp</a></p>
        @elseif ($pedidosHabilitados)
            <p class="mex-aviso" role="status">Estamos cerrados ahora. Puedes ver el menú; los pedidos se activan al abrir.</p>
        @endif

        @if ($categoriasConPlatos->isEmpty())
            <p class="mex-estado-menu">Este menú se está preparando.</p>
        @else
            @foreach ($categoriasConPlatos as $categoria)
                <section class="mex-grupo" id="categoria-{{ $categoria->id }}" aria-labelledby="categoria-titulo-{{ $categoria->id }}">
                    <h2 class="mex-grupo__titulo" id="categoria-titulo-{{ $categoria->id }}">{{ $nombreCategoria($categoria) }}</h2>
                    <ul class="mex-lista" role="list">
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
                                <article @class(['mex-fila', 'mex-fila--variantes' => $conVariantes, 'mex-fila--agotado' => ! $producto->disponible])>
                                    @if (filled($producto->imagen))<x-sitio.imagen :ruta="$producto->imagen" :alt="$nombrePlato" :ancho="128" :alto="128" sizes="64px" class="mex-fila__foto" />@endif
                                    <div class="mex-fila__cuerpo">
                                        <div class="mex-fila__cabeza">
                                            <h3 class="mex-fila__nombre">{{ $nombrePlato }}</h3>
                                            @unless ($conVariantes)
                                                <p class="mex-precio">
                                                    {{ $formato($producto->precioVigente()) }}
                                                    @if ($enOferta)<s class="mex-precio__antes"><span class="mex-solo-lectores">Precio anterior </span>{{ $formato($producto->precio) }}</s>@endif
                                                </p>
                                            @endunless
                                        </div>
                                        @if (! $producto->disponible || $producto->etiquetas->isNotEmpty())
                                            <ul class="mex-etiquetas" role="list">
                                                @if (! $producto->disponible)<li class="mex-etiqueta mex-etiqueta--agotado">Agotado</li>@endif
                                                @foreach ($producto->etiquetas as $etiqueta)<li class="mex-etiqueta">{{ \App\Services\Catalogos::nombreEtiqueta((string) $etiqueta->codigo) }}</li>@endforeach
                                            </ul>
                                        @endif
                                        @if (filled($traduccion?->descripcion))<p class="mex-fila__desc">{{ $traduccion->descripcion }}</p>@endif
                                        @if ($conVariantes)
                                            <ul class="mex-variantes" role="list">
                                                @foreach ($producto->variantes as $variante)
                                                    @php
                                                        $nombreVariante = (string) ($variante->traduccion($idioma)?->nombre ?: 'Opción');
                                                        $articuloVariante = $agregar($producto, $nombrePlato, $variante, $nombreVariante);
                                                    @endphp
                                                    <li class="mex-variantes__fila">
                                                        <span class="mex-variantes__nombre">{{ $variante->traduccion($idioma)?->nombre }}</span>
                                                        <span class="mex-precio">{{ $formato($variante->precio) }}</span>
                                                        @if ($articuloVariante !== null)
                                                            <button class="mex-mas" type="button" hidden data-mex-agregar data-producto="{{ $articuloVariante['producto'] }}" data-variante="{{ $articuloVariante['variante'] }}" data-nombre="{{ $articuloVariante['nombre'] }}" data-nombre-variante="{{ $articuloVariante['nombreVariante'] }}" data-precio="{{ $articuloVariante['precio'] }}" aria-label="Agregar {{ $nombrePlato }} ({{ $nombreVariante }}) al pedido"><span aria-hidden="true">+</span></button>
                                                        @elseif ($puedePedir)
                                                            <span class="mex-etiqueta mex-etiqueta--agotado">Agotado</span>
                                                        @endif
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </div>
                                    @if ($articulo !== null)
                                        <button class="mex-mas" type="button" hidden data-mex-agregar data-producto="{{ $articulo['producto'] }}" data-variante="" data-nombre="{{ $articulo['nombre'] }}" data-nombre-variante="" data-precio="{{ $articulo['precio'] }}" aria-label="Agregar {{ $nombrePlato }} al pedido"><span aria-hidden="true">+</span></button>
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
    <x-sitio.mexicana.pedido :restaurante="$restaurante" :numero="$numeroPedidos" :idioma="$idioma" />
@endif

<x-sitio.mexicana.visita :d="$d" />
<x-sitio.mexicana.pie :d="$d" />
</x-sitio.mexicana.layout>
