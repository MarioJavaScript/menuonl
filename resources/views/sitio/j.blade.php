@php
    /*
     * Plantilla J · Mariscos. Todo sale de los datos del restaurante; una sección sin datos se omite.
     * Portada: nombre (h1), eslogan, descripción, abierto/cerrado ahora, horario de hoy y botones. Recomendados: los destacados
     * visibles (sin agotados), con su precio base. Galería: las fotos en el orden de SitioPublicoDatos, con su descripción como
     * texto alternativo. Las olas de la portada son decorativas y quedan ocultas a lectores de pantalla.
     */
    $nombre = trim((string) $r->nombre);
    $visibles = collect($destacados)->filter(fn (array $p): bool => ! $p['agotado'])->values();

    $hoy = \Illuminate\Support\Carbon::now($r->zona_horaria ?: config('app.timezone'))->dayOfWeekIso - 1;
    $horarioHoy = collect($horarios)->get($hoy);
    $textoHoy = ($horarioHoy !== null && ! $horarioHoy['cerrado']) ? $horarioHoy['texto'] : null;
    $hayHorario = collect($horarios)->contains('cerrado', false);

    $d = compact('r', 'menu_url', 'horarios', 'direccion', 'telefono', 'telefono_enlace', 'whatsapp_digitos', 'reservas_activas', 'instagram_url', 'facebook_url', 'mapa_url', 'galeria_fotos');
@endphp
<x-sitio.mariscos.layout :r="$r" :titulo="$nombre.(filled($r->eslogan) ? ' · '.$r->eslogan : '')" :descripcion="$r->descripcion" pagina="inicio" :reservas="$reservas_activas">
<section class="mar-marino mar-portada" id="inicio" aria-labelledby="portada-titulo">
    <x-sitio.mariscos.cabecera :d="$d" actual="inicio" />

    <div class="mar-contenedor">
        <div @class(['mar-portada__rejilla', 'mar-portada__rejilla--sin-foto' => blank($hero)])>
            <div class="mar-portada__cuerpo">
                @if ($hayHorario)
                    <p @class(['mar-estado', 'mar-estado--abierto' => $esta_abierto])>{{ $esta_abierto ? 'Abierto ahora' : 'Cerrado ahora' }}</p>
                @endif
                <h1 id="portada-titulo">{{ $nombre }}</h1>
                @if (filled($r->eslogan))<p class="mar-portada__eslogan">{{ $r->eslogan }}</p>@endif
                @if (filled($r->descripcion))<p class="mar-portada__descripcion">{{ $r->descripcion }}</p>@endif
                @if ($textoHoy !== null)
                    <p class="mar-portada__hoy"><svg class="mar-icono" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18zM12 7v5l3 2"/></svg><span>Hoy: {{ $textoHoy }}</span></p>
                @endif
                <div class="mar-botones">
                    <a class="mar-boton" href="{{ $menu_url }}">{{ $r->pedidosHabilitados() ? 'Ver menú y pedir' : 'Ver el menú' }}</a>
                    @if ($reservas_activas)<a class="mar-boton mar-boton--claro" href="#reservar">Reservar mesa</a>@endif
                </div>
            </div>
            @if (filled($hero))
                <div class="mar-portada__foto">
                    <x-sitio.imagen :ruta="$hero" :alt="'Portada de '.$nombre" :ancho="1200" :alto="1200" sizes="(min-width:768px) 40vw, 90vw" :prioridad="true" class="mar-portada__imagen" />
                </div>
            @endif
        </div>
    </div>
    <svg class="mar-olas" viewBox="0 0 1440 100" preserveAspectRatio="none" aria-hidden="true" focusable="false">
        <path class="mar-olas__fondo" d="M0 50 C 180 10, 360 90, 540 50 S 900 10, 1080 50 S 1350 90, 1440 50 L1440 100 L0 100 Z"/>
        <path class="mar-olas__papel" d="M0 70 C 200 40, 380 100, 560 70 S 920 40, 1100 70 S 1360 100, 1440 70 L1440 100 L0 100 Z"/>
    </svg>
</section>

<main id="principal" tabindex="-1">
    @if ($visibles->isNotEmpty())
        <section class="mar-seccion" id="destacados" aria-labelledby="destacados-titulo">
            <div class="mar-contenedor">
                <div class="mar-cabeza-seccion">
                    <h2 id="destacados-titulo">Recomendados</h2>
                    <a class="mar-enlace-fuerte" href="{{ $menu_url }}">Ver el menú completo</a>
                </div>
                <ul class="mar-rejilla" role="list">
                    @foreach ($visibles as $plato)
                        <li>
                            <article class="mar-plato">
                                <x-sitio.imagen :ruta="$plato['imagen']" :alt="$plato['nombre']" :ancho="800" :alto="600" sizes="(min-width:1024px) 22vw, (min-width:640px) 45vw, 90vw" class="mar-plato__foto" />
                                <div class="mar-plato__cuerpo">
                                    <div class="mar-plato__fila">
                                        <h3 class="mar-plato__nombre">{{ $plato['nombre'] }}</h3>
                                        <p class="mar-precio">
                                            {{ $plato['precio'] }}
                                            @if (filled($plato['precio_anterior']))<s class="mar-precio__antes"><span class="mar-solo-lectores">Precio anterior </span>{{ $plato['precio_anterior'] }}</s>@endif
                                        </p>
                                    </div>
                                    @if ($plato['etiquetas'] !== [])
                                        <ul class="mar-etiquetas" role="list">
                                            @foreach ($plato['etiquetas'] as $etiqueta)<li class="mar-etiqueta">{{ $etiqueta }}</li>@endforeach
                                        </ul>
                                    @endif
                                    @if (filled($plato['descripcion']))<p class="mar-plato__desc">{{ $plato['descripcion'] }}</p>@endif
                                </div>
                            </article>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    @if ($galeria)
        <section class="mar-seccion mar-seccion--galeria" id="galeria" aria-labelledby="galeria-titulo">
            <div class="mar-contenedor">
                <h2 id="galeria-titulo">Galería</h2>
                <ul class="mar-galeria" role="list">
                    @foreach ($galeria_fotos as $foto)
                        <li><x-sitio.imagen :ruta="$foto['imagen']" :alt="$foto['descripcion'] ?? 'Foto de '.$nombre" :ancho="800" :alto="800" sizes="(min-width:1024px) 25vw, (min-width:640px) 50vw, 100vw" class="mar-galeria__foto" /></li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    <x-sitio.mariscos.visita :d="$d" />
    <x-sitio.mariscos.reservar :d="$d" />
</main>

<x-sitio.mariscos.pie :d="$d" />
</x-sitio.mariscos.layout>
