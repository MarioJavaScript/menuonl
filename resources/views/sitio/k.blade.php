@php
    /*
     * Plantilla K · Cortes premium. Todo sale de los datos del restaurante; una sección sin datos se omite.
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
<x-sitio.cortes.layout :r="$r" :titulo="$nombre.(filled($r->eslogan) ? ' · '.$r->eslogan : '')" :descripcion="$r->descripcion" pagina="inicio" :reservas="$reservas_activas">
<x-sitio.cortes.cabecera :d="$d" actual="inicio" />

<main id="principal" tabindex="-1">
    <section class="cor-portada" id="inicio" aria-labelledby="portada-titulo">
        <div class="cor-contenedor">
            <div class="cor-portada__rejilla">
                <h1 class="cor-portada__titulo" id="portada-titulo">{{ $nombre }}</h1>
                <div class="cor-portada__cuerpo">
                    @if ($hayHorario)
                        <p @class(['cor-estado', 'cor-estado--abierto' => $esta_abierto])>{{ $esta_abierto ? 'Abierto ahora' : 'Cerrado ahora' }}</p>
                    @endif
                    @if (filled($r->eslogan))<p class="cor-portada__eslogan">{{ $r->eslogan }}</p>@endif
                    @if (filled($r->descripcion))<p class="cor-portada__descripcion">{{ $r->descripcion }}</p>@endif
                    @if ($textoHoy !== null)
                        <p class="cor-portada__hoy"><svg class="cor-icono" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18zM12 7v5l3 2"/></svg><span>Hoy: {{ $textoHoy }}</span></p>
                    @endif
                    <div class="cor-botones">
                        @if ($reservas_activas)<a class="cor-boton cor-boton--tinta" href="#reservar">Reservar mesa</a>@endif
                        <a class="cor-boton cor-boton--borde" href="{{ $menu_url }}">{{ $r->pedidosHabilitados() ? 'Ver menú y pedir' : 'Ver menú' }}</a>
                    </div>
                </div>
            </div>
            @if (filled($hero))
                <div class="cor-portada__foto">
                    <x-sitio.imagen :ruta="$hero" :alt="'Foto de '.$nombre" :ancho="1600" :alto="900" sizes="(min-width:1200px) 1200px, 100vw" :prioridad="true" class="cor-portada__imagen" />
                </div>
            @endif
        </div>
    </section>

    @if ($visibles->isNotEmpty())
        <section class="cor-oscuro cor-seccion" id="destacados" aria-labelledby="destacados-titulo">
            <div class="cor-contenedor cor-recomendados">
                <div class="cor-recomendados__cabeza">
                    <h2 id="destacados-titulo">Recomendados</h2>
                    <a class="cor-enlace-oro" href="{{ $menu_url }}">Ver el menú completo</a>
                </div>
                <ul role="list">
                    @foreach ($visibles as $plato)
                        <li class="cor-corte">
                            <div class="cor-corte__cuerpo">
                                <h3 class="cor-corte__nombre">{{ $plato['nombre'] }}</h3>
                                @if (filled($plato['descripcion']))<p class="cor-corte__desc">{{ $plato['descripcion'] }}</p>@endif
                                @if ($plato['etiquetas'] !== [])
                                    <ul class="cor-etiquetas" role="list">
                                        @foreach ($plato['etiquetas'] as $etiqueta)<li class="cor-etiqueta">{{ $etiqueta }}</li>@endforeach
                                    </ul>
                                @endif
                            </div>
                            <p class="cor-precio">
                                {{ $plato['precio'] }}
                                @if (filled($plato['precio_anterior']))<s class="cor-precio__antes"><span class="cor-solo-lectores">Precio anterior </span>{{ $plato['precio_anterior'] }}</s>@endif
                            </p>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    @if ($galeria)
        <section class="cor-seccion" id="galeria" aria-labelledby="galeria-titulo">
            <div class="cor-contenedor">
                <h2 id="galeria-titulo">Galería</h2>
                <ul class="cor-galeria" role="list">
                    @foreach ($galeria_fotos as $foto)
                        <li><x-sitio.imagen :ruta="$foto['imagen']" :alt="$foto['descripcion'] ?? 'Foto de '.$nombre" :ancho="800" :alto="1000" sizes="(min-width:1024px) 25vw, (min-width:640px) 50vw, 100vw" class="cor-galeria__foto" /></li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    <x-sitio.cortes.visita :d="$d" />
    <x-sitio.cortes.reservar :d="$d" />
</main>

<x-sitio.cortes.pie :d="$d" />
</x-sitio.cortes.layout>
