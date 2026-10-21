@php
    /*
     * Plantilla F · Bar. Todo sale de los datos del restaurante; una sección sin datos se omite.
     * Portada: nombre, eslogan y descripción tal cual. El platillo destacado es el marcado "Nuevo" y, si no hay, el primero con foto.
     * Los precios de la vista previa son los del platillo; los platillos con variantes muestran sus precios en el menú.
     */
    $nombre = trim((string) $r->nombre);
    $nombreNuevo = \App\Services\Catalogos::nombreEtiqueta('nuevo');
    $visibles = collect($destacados)->filter(fn (array $p): bool => ! $p['agotado'])->values();
    $nuevo = $visibles->first(fn (array $p): bool => in_array($nombreNuevo, $p['etiquetas'], true));
    $destacado = $nuevo ?? $visibles->first(fn (array $p): bool => filled($p['imagen'])) ?? $visibles->first();

    $d = compact('r', 'menu_url', 'horarios', 'direccion', 'telefono', 'telefono_enlace', 'whatsapp_digitos', 'reservas_activas', 'instagram_url', 'facebook_url', 'mapa_url');
@endphp
<x-sitio.bar.layout :r="$r" :titulo="$nombre.(filled($r->eslogan) ? ' · '.$r->eslogan : '')" :descripcion="$r->descripcion" pagina="inicio" :reservas="$reservas_activas">
<x-sitio.bar.cabecera :d="$d" actual="inicio" />

<main id="principal" class="bar-principal" tabindex="-1">
    <section @class(['bar-portada', 'bar-portada--sin-foto' => blank($hero)]) aria-labelledby="portada-titulo">
        @if (filled($hero))
            <x-sitio.imagen :ruta="$hero" :alt="'Portada de '.$nombre" :ancho="1600" :alto="978" sizes="100vw" :prioridad="true" class="bar-portada__foto" />
        @endif
        <div class="bar-portada__texto">
            <h1 class="bar-portada__nombre" id="portada-titulo">{{ $nombre }}</h1>
            @if (filled($r->eslogan))<p class="bar-portada__eslogan">{{ $r->eslogan }}</p>@endif
            @if (filled($r->descripcion))<p class="bar-portada__desc">{{ $r->descripcion }}</p>@endif
            <div class="bar-portada__acciones">
                <a class="bar-boton" href="{{ $menu_url }}">Ver el menú</a>
                @if ($reservas_activas)<a class="bar-boton bar-boton--contorno" href="#reservar">Reservar mesa</a>@endif
            </div>
        </div>
    </section>

    @if ($destacado !== null)
        <section class="bar-seccion" aria-labelledby="casa-titulo">
            <article @class(['bar-casa', 'bar-casa--sin-foto' => blank($destacado['imagen'])])>
                @if (filled($destacado['imagen']))
                    <x-sitio.imagen :ruta="$destacado['imagen']" :alt="$destacado['nombre']" :ancho="675" :alto="900" sizes="(min-width:768px) 40vw, 100vw" class="bar-casa__foto" />
                @endif
                <div class="bar-casa__cuerpo">
                    <h2 class="bar-casa__etiqueta" id="casa-titulo">{{ $nuevo !== null ? 'Nuevo' : 'Recomendado' }}</h2>
                    <p class="bar-casa__nombre">{{ $destacado['nombre'] }}</p>
                    @if (filled($destacado['descripcion']))<p class="bar-casa__desc">{{ $destacado['descripcion'] }}</p>@endif
                    <p class="bar-casa__precio"><span class="bar-precio">
                        @if (filled($destacado['precio_anterior']))<s class="bar-precio__antes"><span class="bar-solo-lectores">Precio anterior </span>{{ $destacado['precio_anterior'] }}</s>@endif
                        {{ $destacado['precio'] }}
                    </span></p>
                    <a class="bar-boton" href="{{ $menu_url }}">Verlo en el menú</a>
                </div>
            </article>
        </section>
    @endif

    @if ($visibles->isNotEmpty())
        <section class="bar-seccion" aria-labelledby="menu-titulo">
            <h2 class="bar-titulo" id="menu-titulo">Destacados</h2>
            <span class="bar-neon" aria-hidden="true"></span>
            <div class="bar-menu">
                <ul class="bar-menu__lista" role="list">
                    @foreach ($visibles as $plato)
                        <li @class(['bar-plato', 'bar-plato--foto' => filled($plato['imagen'])])>
                            @if (filled($plato['imagen']))
                                <x-sitio.imagen :ruta="$plato['imagen']" :alt="$plato['nombre']" :ancho="176" :alto="176" sizes="88px" class="bar-plato__foto" />
                            @endif
                            <div class="bar-plato__cuerpo">
                                <div class="bar-plato__fila">
                                    <h3 class="bar-plato__nombre">{{ $plato['nombre'] }}</h3>
                                    <span class="bar-punteado" aria-hidden="true"></span>
                                    <span class="bar-precio">
                                        @if (filled($plato['precio_anterior']))<s class="bar-precio__antes"><span class="bar-solo-lectores">Precio anterior </span>{{ $plato['precio_anterior'] }}</s>@endif
                                        {{ $plato['precio'] }}
                                    </span>
                                </div>
                                @if (filled($plato['descripcion']))<p class="bar-plato__desc">{{ $plato['descripcion'] }}</p>@endif
                                @if ($plato['etiquetas'] !== [])
                                    <ul class="bar-etiquetas" role="list">
                                        @foreach ($plato['etiquetas'] as $etiqueta)<li class="bar-etiqueta">{{ $etiqueta }}</li>@endforeach
                                    </ul>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
                <div class="bar-menu__mas"><a class="bar-boton" href="{{ $menu_url }}">Ver el menú completo</a></div>
            </div>
        </section>
    @endif

    <x-sitio.bar.contacto :d="$d" solo="horario" />
    <x-sitio.bar.reservar :d="$d" />
    <x-sitio.bar.contacto :d="$d" solo="resto" />
</main>

<x-sitio.bar.pie :d="$d" />
</x-sitio.bar.layout>
