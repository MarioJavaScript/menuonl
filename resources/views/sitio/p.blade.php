@php
    /*
     * Plantilla P · Cafetería. Todo sale de los datos del restaurante; una sección sin datos se omite.
     * Portada: nombre (h1), descripción, abierto/cerrado ahora y botones; con portada, la foto vertical. Sin eslogan ni textos de relleno.
     * Favoritos: los destacados visibles (sin agotados), con su precio y su foto solo si la tienen. Galería: las fotos en el orden
     * de SitioPublicoDatos, con su descripción como texto alternativo.
     */
    $nombre = trim((string) $r->nombre);
    $visibles = collect($destacados)->filter(fn (array $p): bool => ! $p['agotado'])->values();
    $hayHorario = collect($horarios)->contains('cerrado', false);

    $d = compact('r', 'menu_url', 'horarios', 'direccion', 'telefono', 'telefono_enlace', 'whatsapp_digitos', 'reservas_activas', 'instagram_url', 'facebook_url', 'mapa_url', 'galeria_fotos');
@endphp
<x-sitio.cafe.layout :r="$r" :titulo="$nombre" :descripcion="$r->descripcion" pagina="inicio" :reservas="$reservas_activas">
<x-sitio.cafe.cabecera :d="$d" actual="inicio" />

<main id="principal" tabindex="-1">
    <section class="caf-portada" id="inicio" aria-labelledby="portada-titulo">
        <div class="caf-contenedor">
            <div @class(['caf-portada__tarjeta', 'caf-portada__tarjeta--foto' => filled($hero)])>
                <div class="caf-portada__cuerpo">
                    @if ($hayHorario)
                        <p @class(['caf-estado', 'caf-estado--abierto' => $esta_abierto])>{{ $esta_abierto ? 'Abierto ahora' : 'Cerrado ahora' }}</p>
                    @endif
                    <h1 class="caf-portada__titulo" id="portada-titulo">{{ $nombre }}</h1>
                    @if (filled($r->descripcion))<p class="caf-portada__descripcion">{{ $r->descripcion }}</p>@endif
                    <div class="caf-botones">
                        <a class="caf-boton" href="{{ $menu_url }}">{{ $r->pedidosHabilitados() ? 'Ver menú y pedir' : 'Ver el menú' }}</a>
                        @if ($reservas_activas)<a class="caf-boton caf-boton--borde" href="#reservar">Reservar mesa</a>@endif
                    </div>
                </div>
                @if (filled($hero))
                    <div class="caf-portada__foto">
                        <x-sitio.imagen :ruta="$hero" :alt="'Foto de '.$nombre" :ancho="800" :alto="1000" sizes="(min-width:960px) 480px, 100vw" :prioridad="true" class="caf-portada__imagen" />
                    </div>
                @endif
            </div>
        </div>
    </section>

    @if ($visibles->isNotEmpty())
        <section class="caf-seccion" id="destacados" aria-labelledby="destacados-titulo">
            <div class="caf-contenedor caf-favoritos">
                <div class="caf-favoritos__cabeza">
                    <h2 id="destacados-titulo">Los favoritos</h2>
                    <a class="caf-enlace" href="{{ $menu_url }}">Ver el menú completo</a>
                </div>
                <ul class="caf-platos" role="list">
                    @foreach ($visibles as $plato)
                        <li>
                            <article class="caf-plato">
                                @if (filled($plato['imagen']))<x-sitio.imagen :ruta="$plato['imagen']" :alt="$plato['nombre']" :ancho="640" :alto="640" sizes="(min-width:1024px) 25vw, (min-width:640px) 50vw, 100vw" class="caf-plato__foto" />@endif
                                <div class="caf-plato__linea">
                                    <h3 class="caf-plato__nombre">{{ $plato['nombre'] }}</h3>
                                    <p class="caf-precio">
                                        {{ $plato['precio'] }}
                                        @if (filled($plato['precio_anterior']))<s class="caf-precio__antes"><span class="caf-solo-lectores">Precio anterior </span>{{ $plato['precio_anterior'] }}</s>@endif
                                    </p>
                                </div>
                                @if (filled($plato['descripcion']))<p class="caf-plato__desc">{{ $plato['descripcion'] }}</p>@endif
                                @if ($plato['etiquetas'] !== [])
                                    <ul class="caf-etiquetas" role="list">
                                        @foreach ($plato['etiquetas'] as $etiqueta)<li class="caf-etiqueta">{{ $etiqueta }}</li>@endforeach
                                    </ul>
                                @endif
                            </article>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    @if ($galeria)
        <section class="caf-galeria-banda" id="galeria" aria-labelledby="galeria-titulo">
            <div class="caf-contenedor caf-galeria-cuerpo">
                <h2 id="galeria-titulo">Galería</h2>
                <ul class="caf-galeria" role="list">
                    @foreach ($galeria_fotos as $foto)
                        <li><x-sitio.imagen :ruta="$foto['imagen']" :alt="$foto['descripcion'] ?? 'Foto de '.$nombre" :ancho="800" :alto="600" sizes="(min-width:1024px) 25vw, (min-width:640px) 50vw, 100vw" class="caf-galeria__foto" /></li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    <x-sitio.cafe.visita :d="$d" />
    <x-sitio.cafe.reservar :d="$d" />
</main>

<x-sitio.cafe.pie :d="$d" />
</x-sitio.cafe.layout>
