@php
    /*
     * Plantilla O · Negro y cobre. Todo sale de los datos del restaurante; una sección sin datos se omite.
     * Portada: nombre (h1), eslogan, descripción, abierto/cerrado ahora, horario de hoy y botones; con portada, la foto a la izquierda.
     * Recomendados: los destacados visibles (sin agotados), con su precio y su foto solo si la tienen. Galería: las fotos en el orden
     * de SitioPublicoDatos, con su descripción como texto alternativo. Los números de sección (01, 02...) se cuentan solo entre las
     * secciones que se dibujan y son decorativos.
     */
    $nombre = trim((string) $r->nombre);
    $visibles = collect($destacados)->filter(fn (array $p): bool => ! $p['agotado'])->values();

    $hoy = \Illuminate\Support\Carbon::now($r->zona_horaria ?: config('app.timezone'))->dayOfWeekIso - 1;
    $horarioHoy = collect($horarios)->get($hoy);
    $textoHoy = ($horarioHoy !== null && ! $horarioHoy['cerrado']) ? $horarioHoy['texto'] : null;
    $hayHorario = collect($horarios)->contains('cerrado', false);

    $d = compact('r', 'menu_url', 'horarios', 'direccion', 'telefono', 'telefono_enlace', 'whatsapp_digitos', 'reservas_activas', 'instagram_url', 'facebook_url', 'mapa_url', 'galeria_fotos');
    $numeroSeccion = 0;
@endphp
<x-sitio.cobre.layout :r="$r" :titulo="$nombre.(filled($r->eslogan) ? ' · '.$r->eslogan : '')" :descripcion="$r->descripcion" pagina="inicio" :reservas="$reservas_activas">
<x-sitio.cobre.cabecera :d="$d" actual="inicio" />

<main id="principal" tabindex="-1">
    <section class="cob-portada" id="inicio" aria-labelledby="portada-titulo">
        <div class="cob-contenedor">
            <div @class(['cob-portada__tarjeta', 'cob-portada__tarjeta--foto' => filled($hero)])>
                @if (filled($hero))
                    <div class="cob-portada__foto">
                        <x-sitio.imagen :ruta="$hero" :alt="'Foto de '.$nombre" :ancho="960" :alto="960" sizes="(min-width:960px) 600px, 100vw" :prioridad="true" class="cob-portada__imagen" />
                    </div>
                @endif
                <div class="cob-portada__cuerpo">
                    @if ($hayHorario)
                        <p @class(['cob-estado', 'cob-estado--abierto' => $esta_abierto])>{{ $esta_abierto ? 'Abierto ahora' : 'Cerrado ahora' }}</p>
                    @endif
                    <h1 class="cob-portada__titulo" id="portada-titulo">{{ $nombre }}</h1>
                    @if (filled($r->eslogan))<p class="cob-portada__eslogan">{{ $r->eslogan }}</p>@endif
                    @if (filled($r->descripcion))<p class="cob-portada__descripcion">{{ $r->descripcion }}</p>@endif
                    @if ($textoHoy !== null)
                        <p class="cob-portada__hoy"><svg class="cob-icono" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18zM12 7v5l3 2"/></svg><span>Hoy: {{ $textoHoy }}</span></p>
                    @endif
                    <div class="cob-botones">
                        @if ($reservas_activas)<a class="cob-boton" href="#reservar">Reservar mesa</a>@endif
                        <a @class(['cob-boton', 'cob-boton--borde' => $reservas_activas]) href="{{ $menu_url }}">{{ $r->pedidosHabilitados() ? 'Ver menú y pedir' : 'Ver el menú' }}</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    @if ($visibles->isNotEmpty())
        @php($numeroSeccion++)
        <section class="cob-seccion cob-seccion--banda" id="destacados" aria-labelledby="destacados-titulo">
            <div class="cob-contenedor cob-recomendados">
                <div class="cob-recomendados__cabeza">
                    <div class="cob-encabezado-seccion">
                        <span class="cob-numero" aria-hidden="true">{{ sprintf('%02d', $numeroSeccion) }}</span>
                        <h2 id="destacados-titulo">Recomendados</h2>
                    </div>
                    <a class="cob-enlace-cobre" href="{{ $menu_url }}">Ver el menú completo</a>
                </div>
                <ul class="cob-platos" role="list">
                    @foreach ($visibles as $plato)
                        <li>
                            <article class="cob-plato">
                                @if (filled($plato['imagen']))<x-sitio.imagen :ruta="$plato['imagen']" :alt="$plato['nombre']" :ancho="640" :alto="480" sizes="(min-width:1024px) 25vw, (min-width:640px) 50vw, 100vw" class="cob-plato__foto" />@endif
                                <div class="cob-plato__cuerpo">
                                    <div class="cob-plato__linea">
                                        <h3 class="cob-plato__nombre">{{ $plato['nombre'] }}</h3>
                                        <p class="cob-precio">
                                            {{ $plato['precio'] }}
                                            @if (filled($plato['precio_anterior']))<s class="cob-precio__antes"><span class="cob-solo-lectores">Precio anterior </span>{{ $plato['precio_anterior'] }}</s>@endif
                                        </p>
                                    </div>
                                    @if (filled($plato['descripcion']))<p class="cob-plato__desc">{{ $plato['descripcion'] }}</p>@endif
                                    @if ($plato['etiquetas'] !== [])
                                        <ul class="cob-etiquetas" role="list">
                                            @foreach ($plato['etiquetas'] as $etiqueta)<li class="cob-etiqueta">{{ $etiqueta }}</li>@endforeach
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
        @php($numeroSeccion++)
        <section class="cob-seccion cob-seccion--galeria" id="galeria" aria-labelledby="galeria-titulo">
            <div class="cob-contenedor">
                <div class="cob-encabezado-seccion">
                    <span class="cob-numero" aria-hidden="true">{{ sprintf('%02d', $numeroSeccion) }}</span>
                    <h2 id="galeria-titulo">Galería</h2>
                </div>
                <ul class="cob-galeria" role="list">
                    @foreach ($galeria_fotos as $foto)
                        <li><x-sitio.imagen :ruta="$foto['imagen']" :alt="$foto['descripcion'] ?? 'Foto de '.$nombre" :ancho="800" :alto="600" sizes="(min-width:1024px) 25vw, (min-width:640px) 50vw, 100vw" class="cob-galeria__foto" /></li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    <x-sitio.cobre.visita :d="$d" :numero="$numeroSeccion" />
    <x-sitio.cobre.reservar :d="$d" />
</main>

<x-sitio.cobre.pie :d="$d" />
</x-sitio.cobre.layout>
