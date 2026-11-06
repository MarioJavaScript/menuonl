@php
    /*
     * Plantilla L · Negro y esmeralda. Todo sale de los datos del restaurante; una sección sin datos se omite.
     * Portada: nombre (h1), eslogan, descripción, abierto/cerrado ahora, horario de hoy y botones. Recomendados: los destacados
     * visibles (sin agotados), con su precio base. Galería: las fotos en el orden de SitioPublicoDatos, con su descripción como
     * texto alternativo.
     */
    $nombre = trim((string) $r->nombre);
    $visibles = collect($destacados)->filter(fn (array $p): bool => ! $p['agotado'])->values();

    $hoy = \Illuminate\Support\Carbon::now($r->zona_horaria ?: config('app.timezone'))->dayOfWeekIso - 1;
    $horarioHoy = collect($horarios)->get($hoy);
    $textoHoy = ($horarioHoy !== null && ! $horarioHoy['cerrado']) ? $horarioHoy['texto'] : null;
    $hayHorario = collect($horarios)->contains('cerrado', false);

    $d = compact('r', 'menu_url', 'horarios', 'direccion', 'telefono', 'telefono_enlace', 'whatsapp_digitos', 'reservas_activas', 'instagram_url', 'facebook_url', 'mapa_url', 'galeria_fotos');
@endphp
<x-sitio.esmeralda.layout :r="$r" :titulo="$nombre.(filled($r->eslogan) ? ' · '.$r->eslogan : '')" :descripcion="$r->descripcion" pagina="inicio" :reservas="$reservas_activas">
<x-sitio.esmeralda.cabecera :d="$d" actual="inicio" />

<main id="principal" tabindex="-1">
    <section class="esm-portada" id="inicio" aria-labelledby="portada-titulo">
        <div class="esm-contenedor">
            <div class="esm-portada__rejilla">
                <h1 class="esm-portada__titulo" id="portada-titulo">{{ $nombre }}</h1>
                <div class="esm-portada__cuerpo">
                    @if ($hayHorario)
                        <p @class(['esm-estado', 'esm-estado--abierto' => $esta_abierto])>{{ $esta_abierto ? 'Abierto ahora' : 'Cerrado ahora' }}</p>
                    @endif
                    @if (filled($r->eslogan))<p class="esm-portada__eslogan">{{ $r->eslogan }}</p>@endif
                    @if (filled($r->descripcion))<p class="esm-portada__descripcion">{{ $r->descripcion }}</p>@endif
                    @if ($textoHoy !== null)
                        <p class="esm-portada__hoy"><svg class="esm-icono" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18zM12 7v5l3 2"/></svg><span>Hoy: {{ $textoHoy }}</span></p>
                    @endif
                    <div class="esm-botones">
                        @if ($reservas_activas)<a class="esm-boton" href="#reservar">Reservar mesa</a>@endif
                        <a class="esm-boton esm-boton--borde" href="{{ $menu_url }}">{{ $r->pedidosHabilitados() ? 'Ver menú y pedir' : 'Ver menú' }}</a>
                    </div>
                </div>
            </div>
            @if (filled($hero))
                <div class="esm-portada__foto">
                    <x-sitio.imagen :ruta="$hero" :alt="'Foto de '.$nombre" :ancho="1600" :alto="700" sizes="(min-width:1200px) 1200px, 100vw" :prioridad="true" class="esm-portada__imagen" />
                </div>
            @endif
        </div>
    </section>

    @if ($visibles->isNotEmpty())
        <section class="esm-banda esm-seccion" id="destacados" aria-labelledby="destacados-titulo">
            <div class="esm-contenedor esm-recomendados">
                <div class="esm-recomendados__cabeza">
                    <h2 id="destacados-titulo">Recomendados</h2>
                    <a class="esm-enlace-verde" href="{{ $menu_url }}">Ver el menú completo</a>
                </div>
                <ul class="esm-platos" role="list">
                    @foreach ($visibles as $plato)
                        <li class="esm-plato">
                            <div class="esm-plato__linea">
                                <h3 class="esm-plato__nombre">{{ $plato['nombre'] }}</h3>
                                <span class="esm-plato__guia" aria-hidden="true"></span>
                                <p class="esm-precio">
                                    {{ $plato['precio'] }}
                                    @if (filled($plato['precio_anterior']))<s class="esm-precio__antes"><span class="esm-solo-lectores">Precio anterior </span>{{ $plato['precio_anterior'] }}</s>@endif
                                </p>
                            </div>
                            @if (filled($plato['descripcion']))<p class="esm-plato__desc">{{ $plato['descripcion'] }}</p>@endif
                            @if ($plato['etiquetas'] !== [])
                                <ul class="esm-etiquetas" role="list">
                                    @foreach ($plato['etiquetas'] as $etiqueta)<li class="esm-etiqueta">{{ $etiqueta }}</li>@endforeach
                                </ul>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    @if ($galeria)
        <section class="esm-seccion" id="galeria" aria-labelledby="galeria-titulo">
            <div class="esm-contenedor">
                <h2 id="galeria-titulo">Galería</h2>
                <ul class="esm-galeria" role="list">
                    @foreach ($galeria_fotos as $foto)
                        <li><x-sitio.imagen :ruta="$foto['imagen']" :alt="$foto['descripcion'] ?? 'Foto de '.$nombre" :ancho="800" :alto="600" sizes="(min-width:1024px) 25vw, (min-width:640px) 50vw, 100vw" class="esm-galeria__foto" /></li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    <x-sitio.esmeralda.visita :d="$d" />
    <x-sitio.esmeralda.reservar :d="$d" />
</main>

<x-sitio.esmeralda.pie :d="$d" />
</x-sitio.esmeralda.layout>
