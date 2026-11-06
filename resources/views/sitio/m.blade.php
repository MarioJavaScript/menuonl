@php
    /*
     * Plantilla M · Hamburguesas. Todo sale de los datos del restaurante; una sección sin datos se omite.
     * Portada: nombre (h1), eslogan, descripción, abierto/cerrado ahora, horario de hoy y botones; con portada, la foto cuadrada.
     * Cinta: los nombres reales de las categorías visibles con platillos (estática, decorativa); sin categorías no se dibuja.
     * Recomendados: los destacados visibles (sin agotados), con su precio y su foto solo si la tienen. Galería: las fotos en el orden
     * de SitioPublicoDatos, con su descripción como texto alternativo.
     */
    $nombre = trim((string) $r->nombre);
    $visibles = collect($destacados)->filter(fn (array $p): bool => ! $p['agotado'])->values();

    $hoy = \Illuminate\Support\Carbon::now($r->zona_horaria ?: config('app.timezone'))->dayOfWeekIso - 1;
    $horarioHoy = collect($horarios)->get($hoy);
    $textoHoy = ($horarioHoy !== null && ! $horarioHoy['cerrado']) ? $horarioHoy['texto'] : null;
    $hayHorario = collect($horarios)->contains('cerrado', false);

    $categoriasCinta = \App\Models\Categoria::where('restaurante_id', $r->id)
        ->visible()
        ->ordenado()
        ->whereHas('productos', fn ($q) => $q->visible()->when(! $r->mostrar_agotados, fn ($q) => $q->disponible()))
        ->with(['restaurante', 'traducciones'])
        ->get()
        ->map(fn ($categoria): string => trim((string) ($categoria->traduccion($idioma)?->nombre ?? '')))
        ->filter()
        ->values();

    $d = compact('r', 'menu_url', 'horarios', 'direccion', 'telefono', 'telefono_enlace', 'whatsapp_digitos', 'reservas_activas', 'instagram_url', 'facebook_url', 'mapa_url', 'galeria_fotos');
@endphp
<x-sitio.hamburguesas.layout :r="$r" :titulo="$nombre.(filled($r->eslogan) ? ' · '.$r->eslogan : '')" :descripcion="$r->descripcion" pagina="inicio" :reservas="$reservas_activas">
<x-sitio.hamburguesas.cabecera :d="$d" actual="inicio" />

<main id="principal" tabindex="-1">
    <section class="ham-portada" id="inicio" aria-labelledby="portada-titulo">
        <div @class(['ham-contenedor', 'ham-portada__rejilla', 'ham-portada__rejilla--foto' => filled($hero)])>
            <div class="ham-portada__cuerpo">
                @if ($hayHorario)
                    <p @class(['ham-estado', 'ham-estado--abierto' => $esta_abierto])>{{ $esta_abierto ? 'Abierto ahora' : 'Cerrado ahora' }}</p>
                @endif
                <h1 class="ham-portada__titulo" id="portada-titulo">{{ $nombre }}</h1>
                @if (filled($r->eslogan))<p class="ham-portada__eslogan">{{ $r->eslogan }}</p>@endif
                @if (filled($r->descripcion))<p class="ham-portada__descripcion">{{ $r->descripcion }}</p>@endif
                @if ($textoHoy !== null)
                    <p class="ham-portada__hoy"><svg class="ham-icono" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18zM12 7v5l3 2"/></svg><span>Hoy: {{ $textoHoy }}</span></p>
                @endif
                <div class="ham-botones">
                    <a class="ham-boton" href="{{ $menu_url }}">{{ $r->pedidosHabilitados() ? 'Arma tu pedido' : 'Ver el menú' }}</a>
                    @if ($reservas_activas)<a class="ham-boton ham-boton--borde" href="#reservar">Reservar</a>@endif
                </div>
            </div>
            @if (filled($hero))
                <div class="ham-portada__foto">
                    <x-sitio.imagen :ruta="$hero" :alt="'Foto de '.$nombre" :ancho="960" :alto="960" sizes="(min-width:960px) 480px, 100vw" :prioridad="true" class="ham-portada__imagen" />
                </div>
            @endif
        </div>
    </section>

    @if ($categoriasCinta->isNotEmpty())
        <div class="ham-cinta" aria-hidden="true">
            <p class="ham-cinta__texto">{{ $categoriasCinta->implode(' · ') }}</p>
        </div>
    @endif

    @if ($visibles->isNotEmpty())
        <section class="ham-seccion" id="destacados" aria-labelledby="destacados-titulo">
            <div class="ham-contenedor ham-recomendados">
                <div class="ham-recomendados__cabeza">
                    <h2 id="destacados-titulo">Recomendados</h2>
                    <a class="ham-enlace-rojo" href="{{ $menu_url }}">Ver el menú completo</a>
                </div>
                <ul class="ham-platos" role="list">
                    @foreach ($visibles as $plato)
                        <li>
                            <article class="ham-plato">
                                @if (filled($plato['imagen']))<x-sitio.imagen :ruta="$plato['imagen']" :alt="$plato['nombre']" :ancho="640" :alto="480" sizes="(min-width:1024px) 25vw, (min-width:640px) 50vw, 100vw" class="ham-plato__foto" />@endif
                                <div class="ham-plato__cuerpo">
                                    <div class="ham-plato__linea">
                                        <h3 class="ham-plato__nombre">{{ $plato['nombre'] }}</h3>
                                        <p class="ham-precio">
                                            {{ $plato['precio'] }}
                                            @if (filled($plato['precio_anterior']))<s class="ham-precio__antes"><span class="ham-solo-lectores">Precio anterior </span>{{ $plato['precio_anterior'] }}</s>@endif
                                        </p>
                                    </div>
                                    @if (filled($plato['descripcion']))<p class="ham-plato__desc">{{ $plato['descripcion'] }}</p>@endif
                                    @if ($plato['etiquetas'] !== [])
                                        <ul class="ham-etiquetas" role="list">
                                            @foreach ($plato['etiquetas'] as $etiqueta)<li class="ham-etiqueta">{{ $etiqueta }}</li>@endforeach
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
        <section class="ham-seccion ham-seccion--galeria" id="galeria" aria-labelledby="galeria-titulo">
            <div class="ham-contenedor">
                <h2 id="galeria-titulo">Galería</h2>
                <ul class="ham-galeria" role="list">
                    @foreach ($galeria_fotos as $foto)
                        <li><x-sitio.imagen :ruta="$foto['imagen']" :alt="$foto['descripcion'] ?? 'Foto de '.$nombre" :ancho="800" :alto="600" sizes="(min-width:1024px) 25vw, (min-width:640px) 50vw, 100vw" class="ham-galeria__foto" /></li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    <x-sitio.hamburguesas.visita :d="$d" />
    <x-sitio.hamburguesas.reservar :d="$d" />
</main>

<x-sitio.hamburguesas.pie :d="$d" />
</x-sitio.hamburguesas.layout>
