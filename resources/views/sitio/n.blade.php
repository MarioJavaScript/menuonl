@php
    /*
     * Plantilla N · Mexicana colorida. Todo sale de los datos del restaurante; una sección sin datos se omite.
     * Portada: nombre (h1), eslogan, abierto/cerrado ahora, horario de hoy y botones; con portada, la foto en arco.
     * Nosotros: la descripción del restaurante. Recomendados: los destacados visibles (sin agotados), con su precio y su foto solo si la
     * tienen. Galería: las fotos en el orden de SitioPublicoDatos, con su descripción como texto alternativo.
     * Las montañas de la portada son un dibujo decorativo y estático (sin datos ni animación).
     */
    $nombre = trim((string) $r->nombre);
    $visibles = collect($destacados)->filter(fn (array $p): bool => ! $p['agotado'])->values();

    $hoy = \Illuminate\Support\Carbon::now($r->zona_horaria ?: config('app.timezone'))->dayOfWeekIso - 1;
    $horarioHoy = collect($horarios)->get($hoy);
    $textoHoy = ($horarioHoy !== null && ! $horarioHoy['cerrado']) ? $horarioHoy['texto'] : null;
    $hayHorario = collect($horarios)->contains('cerrado', false);

    $d = compact('r', 'menu_url', 'horarios', 'direccion', 'telefono', 'telefono_enlace', 'whatsapp_digitos', 'reservas_activas', 'instagram_url', 'facebook_url', 'mapa_url', 'galeria_fotos');
@endphp
<x-sitio.mexicana.layout :r="$r" :titulo="$nombre.(filled($r->eslogan) ? ' · '.$r->eslogan : '')" :descripcion="$r->descripcion" pagina="inicio" :reservas="$reservas_activas">
<x-sitio.mexicana.cabecera :d="$d" actual="inicio" />

<main id="principal" tabindex="-1">
    <section class="mex-portada" id="inicio" aria-labelledby="portada-titulo">
        <div @class(['mex-contenedor', 'mex-portada__rejilla', 'mex-portada__rejilla--foto' => filled($hero)])>
            <div class="mex-portada__cuerpo">
                @if ($hayHorario)
                    <p @class(['mex-estado', 'mex-estado--abierto' => $esta_abierto])>{{ $esta_abierto ? 'Abierto ahora' : 'Cerrado ahora' }}</p>
                @endif
                <h1 class="mex-portada__titulo" id="portada-titulo">{{ $nombre }}</h1>
                @if (filled($r->eslogan))<p class="mex-portada__eslogan">{{ $r->eslogan }}</p>@endif
                @if ($textoHoy !== null)
                    <p class="mex-portada__hoy"><svg class="mex-icono" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18zM12 7v5l3 2"/></svg><span>Hoy: {{ $textoHoy }}</span></p>
                @endif
                <div class="mex-botones">
                    <a class="mex-boton" href="{{ $menu_url }}">{{ $r->pedidosHabilitados() ? 'Ver menú y pedir' : 'Ver el menú' }}</a>
                    @if ($reservas_activas)<a class="mex-boton mex-boton--borde" href="#reservar">Reservar mesa</a>@endif
                </div>
            </div>
            @if (filled($hero))
                <div class="mex-portada__foto">
                    <x-sitio.imagen :ruta="$hero" :alt="'Foto de '.$nombre" :ancho="800" :alto="1000" sizes="(min-width:960px) 416px, 100vw" :prioridad="true" class="mex-portada__imagen" />
                </div>
            @endif
        </div>
        <svg class="mex-portada__montes" viewBox="0 0 1440 120" preserveAspectRatio="none" aria-hidden="true" focusable="false">
            <path class="mex-portada__monte-lejano" d="M0 90 L180 40 L300 75 L470 20 L640 70 L780 35 L960 80 L1120 30 L1290 72 L1440 45 L1440 120 L0 120 Z"/>
            <path class="mex-portada__monte-cercano" d="M0 100 L220 70 L380 95 L560 60 L760 98 L930 66 L1100 100 L1280 70 L1440 92 L1440 120 L0 120 Z"/>
            <path class="mex-portada__cactus" d="M1180 96 L1180 52 Q1180 44 1188 44 Q1196 44 1196 52 L1196 70 L1206 70 L1206 60 Q1206 54 1211 54 Q1216 54 1216 60 L1216 78 L1196 78 L1196 96 Z"/>
        </svg>
    </section>

    @if (filled($r->descripcion))
        <section class="mex-seccion" id="nosotros" aria-labelledby="nosotros-titulo">
            <div class="mex-contenedor mex-nosotros">
                <h2 id="nosotros-titulo">Nuestra historia</h2>
                <p class="mex-nosotros__texto">{{ $r->descripcion }}</p>
            </div>
        </section>
    @endif

    @if ($visibles->isNotEmpty())
        <section class="mex-seccion mex-seccion--destacados" id="destacados" aria-labelledby="destacados-titulo">
            <div class="mex-contenedor mex-recomendados">
                <div class="mex-recomendados__cabeza">
                    <h2 id="destacados-titulo">Recomendados</h2>
                    <a class="mex-enlace-rojo" href="{{ $menu_url }}">Ver el menú completo</a>
                </div>
                <ul class="mex-platos" role="list">
                    @foreach ($visibles as $plato)
                        <li>
                            <article class="mex-plato">
                                @if (filled($plato['imagen']))<x-sitio.imagen :ruta="$plato['imagen']" :alt="$plato['nombre']" :ancho="640" :alto="480" sizes="(min-width:1024px) 25vw, (min-width:640px) 50vw, 100vw" class="mex-plato__foto" />@endif
                                <div class="mex-plato__cuerpo">
                                    <div class="mex-plato__linea">
                                        <h3 class="mex-plato__nombre">{{ $plato['nombre'] }}</h3>
                                        <p class="mex-precio">
                                            {{ $plato['precio'] }}
                                            @if (filled($plato['precio_anterior']))<s class="mex-precio__antes"><span class="mex-solo-lectores">Precio anterior </span>{{ $plato['precio_anterior'] }}</s>@endif
                                        </p>
                                    </div>
                                    @if (filled($plato['descripcion']))<p class="mex-plato__desc">{{ $plato['descripcion'] }}</p>@endif
                                    @if ($plato['etiquetas'] !== [])
                                        <ul class="mex-etiquetas" role="list">
                                            @foreach ($plato['etiquetas'] as $etiqueta)<li class="mex-etiqueta">{{ $etiqueta }}</li>@endforeach
                                        </ul>
                                    @endif
                                </div>
                            </article>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    @if ($galeria)
        <section class="mex-seccion mex-seccion--galeria" id="galeria" aria-labelledby="galeria-titulo">
            <div class="mex-contenedor">
                <h2 id="galeria-titulo">Galería</h2>
                <ul class="mex-galeria" role="list">
                    @foreach ($galeria_fotos as $foto)
                        <li><x-sitio.imagen :ruta="$foto['imagen']" :alt="$foto['descripcion'] ?? 'Foto de '.$nombre" :ancho="800" :alto="600" sizes="(min-width:1024px) 25vw, (min-width:640px) 50vw, 100vw" class="mex-galeria__foto" /></li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    <x-sitio.mexicana.visita :d="$d" />
    <x-sitio.mexicana.reservar :d="$d" />
</main>

<x-sitio.mexicana.pie :d="$d" />
</x-sitio.mexicana.layout>
