@php
    /*
     * Plantilla I · Japonés. Todo sale de los datos del restaurante; una sección sin datos se omite.
     * Portada: nombre (h1), eslogan, descripción, abierto/cerrado ahora, horario de hoy y botones. Recomendados: los destacados
     * visibles (sin agotados), con su precio base. Galería: las fotos en el orden de SitioPublicoDatos, con su descripción como
     * texto alternativo. El kanji 和食 ("comida japonesa") es decorativo y queda oculto a lectores de pantalla.
     */
    $nombre = trim((string) $r->nombre);
    $visibles = collect($destacados)->filter(fn (array $p): bool => ! $p['agotado'])->values();

    $hoy = \Illuminate\Support\Carbon::now($r->zona_horaria ?: config('app.timezone'))->dayOfWeekIso - 1;
    $horarioHoy = collect($horarios)->get($hoy);
    $textoHoy = ($horarioHoy !== null && ! $horarioHoy['cerrado']) ? $horarioHoy['texto'] : null;
    $hayHorario = collect($horarios)->contains('cerrado', false);

    $d = compact('r', 'menu_url', 'horarios', 'direccion', 'telefono', 'telefono_enlace', 'whatsapp_digitos', 'reservas_activas', 'instagram_url', 'facebook_url', 'mapa_url', 'galeria_fotos');
@endphp
<x-sitio.japones.layout :r="$r" :titulo="$nombre.(filled($r->eslogan) ? ' · '.$r->eslogan : '')" :descripcion="$r->descripcion" pagina="inicio" :reservas="$reservas_activas">
<x-sitio.japones.cabecera :d="$d" actual="inicio" />

<main id="principal" tabindex="-1">
    <section class="jap-seccion jap-portada" id="inicio" aria-labelledby="portada-titulo">
        <div class="jap-contenedor">
            <div @class(['jap-portada__rejilla', 'jap-portada__rejilla--sin-foto' => blank($hero)])>
                <div class="jap-portada__texto">
                    <p class="jap-kanji" aria-hidden="true">和食</p>
                    <div class="jap-portada__cuerpo">
                        @if ($hayHorario)
                            <p @class(['jap-estado', 'jap-estado--abierto' => $esta_abierto])>{{ $esta_abierto ? 'Abierto ahora' : 'Cerrado ahora' }}</p>
                        @endif
                        <h1 id="portada-titulo">{{ $nombre }}</h1>
                        @if (filled($r->eslogan))<p class="jap-portada__eslogan">{{ $r->eslogan }}</p>@endif
                        @if (filled($r->descripcion))<p class="jap-portada__descripcion">{{ $r->descripcion }}</p>@endif
                        @if ($textoHoy !== null)
                            <p class="jap-portada__hoy"><svg class="jap-icono" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18zM12 7v5l3 2"/></svg><span>Hoy: {{ $textoHoy }}</span></p>
                        @endif
                        <div class="jap-botones">
                            <a class="jap-boton" href="{{ $menu_url }}">{{ $r->pedidosHabilitados() ? 'Ver menú y pedir' : 'Ver el menú' }}</a>
                            @if ($reservas_activas)<a class="jap-boton jap-boton--borde" href="#reservar">Reservar mesa</a>@endif
                        </div>
                    </div>
                </div>
                @if (filled($hero))
                    <div class="jap-portada__foto">
                        <x-sitio.imagen :ruta="$hero" :alt="'Portada de '.$nombre" :ancho="1200" :alto="1200" sizes="(min-width:768px) 40vw, 90vw" :prioridad="true" class="jap-portada__imagen" />
                    </div>
                @endif
            </div>
        </div>
    </section>

    @if ($visibles->isNotEmpty())
        <section class="jap-blanco jap-seccion" id="destacados" aria-labelledby="destacados-titulo">
            <div class="jap-contenedor">
                <div class="jap-cabeza-seccion">
                    <h2 id="destacados-titulo">Recomendados</h2>
                    <a class="jap-enlace-fuerte" href="{{ $menu_url }}">Ver el menú completo</a>
                </div>
                <ul class="jap-rejilla" role="list">
                    @foreach ($visibles as $plato)
                        <li>
                            <article class="jap-plato">
                                <x-sitio.imagen :ruta="$plato['imagen']" :alt="$plato['nombre']" :ancho="800" :alto="800" sizes="(min-width:1024px) 22vw, (min-width:640px) 45vw, 90vw" class="jap-plato__foto" />
                                <div class="jap-plato__fila">
                                    <h3 class="jap-plato__nombre">{{ $plato['nombre'] }}</h3>
                                    <p class="jap-precio">
                                        {{ $plato['precio'] }}
                                        @if (filled($plato['precio_anterior']))<s class="jap-precio__antes"><span class="jap-solo-lectores">Precio anterior </span>{{ $plato['precio_anterior'] }}</s>@endif
                                    </p>
                                </div>
                                @if ($plato['etiquetas'] !== [])
                                    <ul class="jap-etiquetas" role="list">
                                        @foreach ($plato['etiquetas'] as $etiqueta)<li class="jap-etiqueta">{{ $etiqueta }}</li>@endforeach
                                    </ul>
                                @endif
                                @if (filled($plato['descripcion']))<p class="jap-plato__desc">{{ $plato['descripcion'] }}</p>@endif
                            </article>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    @if ($galeria)
        <section class="jap-seccion" id="galeria" aria-labelledby="galeria-titulo">
            <div class="jap-contenedor">
                <h2 id="galeria-titulo">Galería</h2>
                <ul class="jap-galeria" role="list">
                    @foreach ($galeria_fotos as $foto)
                        <li><x-sitio.imagen :ruta="$foto['imagen']" :alt="$foto['descripcion'] ?? 'Foto de '.$nombre" :ancho="960" :alto="1200" sizes="(min-width:1024px) 25vw, (min-width:640px) 50vw, 100vw" class="jap-galeria__foto" /></li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    <x-sitio.japones.visita :d="$d" />
    <x-sitio.japones.reservar :d="$d" />
</main>

<x-sitio.japones.pie :d="$d" />
</x-sitio.japones.layout>
