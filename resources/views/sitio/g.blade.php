@php
    /*
     * Plantilla G · CafePastel. Todo sale de los datos del restaurante; una sección sin datos se omite.
     * Portada: eslogan (rótulo cursivo), nombre y botones; la descripción va en "Sobre". El platillo destacado es el marcado "Nuevo" y, si no hay,
     * el primero con foto. "Nuestras favoritas" usa las categorías de los destacados visibles (hasta tres) y se omite con menos de dos.
     * Los precios de la vista previa son los del platillo; los platillos con variantes muestran sus precios en el menú.
     */
    $nombre = trim((string) $r->nombre);
    $nombreNuevo = \App\Services\Catalogos::nombreEtiqueta('nuevo');
    $visibles = collect($destacados)->filter(fn (array $p): bool => ! $p['agotado'])->values();
    $nuevo = $visibles->first(fn (array $p): bool => in_array($nombreNuevo, $p['etiquetas'], true));
    $destacado = $nuevo ?? $visibles->first(fn (array $p): bool => filled($p['imagen'])) ?? $visibles->first();
    $favoritas = $visibles->filter(fn (array $p): bool => filled($p['categoria']))->unique('categoria')->take(3)->values();
    $tonos = ['rosa', 'durazno', 'lila'];

    $hoy = \Illuminate\Support\Carbon::now($r->zona_horaria ?: config('app.timezone'))->dayOfWeekIso - 1;
    $horarioHoy = collect($horarios)->get($hoy);
    $datosRapidos = collect([
        ($horarioHoy !== null && ! $horarioHoy['cerrado']) ? ['icono' => '<path d="M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18zM12 7v5l3 2"/>', 'titulo' => 'Hoy, '.$horarioHoy['dia'], 'texto' => $horarioHoy['texto']] : null,
        filled($direccion) ? ['icono' => '<path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11zM12 7a3 3 0 1 0 0 6 3 3 0 0 0 0-6z"/>', 'titulo' => 'Visítanos', 'texto' => $direccion] : null,
    ])->filter()->values();

    $d = compact('r', 'menu_url', 'horarios', 'direccion', 'telefono', 'telefono_enlace', 'whatsapp_digitos', 'reservas_activas', 'instagram_url', 'facebook_url', 'mapa_url');
@endphp
<x-sitio.pastel.layout :r="$r" :titulo="$nombre.(filled($r->eslogan) ? ' · '.$r->eslogan : '')" :descripcion="$r->descripcion" pagina="inicio" :reservas="$reservas_activas">
<x-sitio.pastel.cabecera :d="$d" actual="inicio" />

<main id="principal" tabindex="-1">
    <section class="pas-franja pas-franja--abajo pas-hero" aria-labelledby="portada-titulo">
        <div class="pas-contenedor">
            <div @class(['pas-hero__rejilla', 'pas-hero__rejilla--sin-foto' => blank($hero)])>
                <div class="pas-hero__texto">
                    @if (filled($r->eslogan))<span class="pas-cursiva">{{ $r->eslogan }}</span>@endif
                    <h1 class="pas-hero__titulo" id="portada-titulo">{{ $nombre }}</h1>
                    <div class="pas-acciones">
                        <a class="pas-boton" href="{{ $menu_url }}">Ver el menú</a>
                        @if ($reservas_activas)<a class="pas-boton pas-boton--contorno" href="#reservar">Reservar mesa</a>@endif
                    </div>
                </div>
                @if (filled($hero))
                    <div class="pas-hero__foto">
                        <x-sitio.imagen :ruta="$hero" :alt="'Portada de '.$nombre" :ancho="1600" :alto="1067" sizes="(min-width:768px) 40vw, 90vw" :prioridad="true" class="pas-hero__imagen" />
                    </div>
                @endif
            </div>
        </div>
    </section>

    @if ($datosRapidos->isNotEmpty())
        <div class="pas-contenedor pas-datos">
            @foreach ($datosRapidos as $dato)
                <div class="pas-dato">
                    <span class="pas-dato__icono"><svg class="pas-icono" viewBox="0 0 24 24" aria-hidden="true" focusable="false">{!! $dato['icono'] !!}</svg></span>
                    <p><strong>{{ $dato['titulo'] }}</strong>{{ $dato['texto'] }}</p>
                </div>
            @endforeach
        </div>
    @endif

    @if ($favoritas->count() >= 2)
        <section class="pas-seccion" aria-labelledby="favoritas-titulo">
            <div class="pas-contenedor">
                <div class="pas-encabezado-seccion pas-encabezado-seccion--centro">
                    <span class="pas-cursiva" aria-hidden="true">Para endulzar el día</span>
                    <h2 id="favoritas-titulo">Nuestras favoritas</h2>
                </div>
                <ul class="pas-tarjetas" role="list">
                    @foreach ($favoritas as $i => $plato)
                        <li>
                            <a class="pas-tarjeta pas-tarjeta--{{ $tonos[$i] }}" href="{{ $menu_url }}">
                                <h3 class="pas-tarjeta__titulo">{{ $plato['categoria'] }}</h3>
                                <div class="pas-tarjeta__foto">
                                    <x-sitio.imagen :ruta="$plato['imagen']" :alt="$plato['nombre']" :ancho="900" :alto="675" sizes="(min-width:768px) 30vw, 90vw" class="pas-tarjeta__imagen" />
                                </div>
                                <span class="pas-tarjeta__etiqueta">Ver todo</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    @if (filled($r->descripcion))
        <section class="pas-seccion" aria-labelledby="sobre-titulo">
            <div class="pas-contenedor">
                <div class="pas-encabezado-seccion">
                    <span class="pas-cursiva" aria-hidden="true">Nuestra casa</span>
                    <h2 id="sobre-titulo">Sobre {{ $nombre }}</h2>
                </div>
                <p class="pas-sobre">{{ $r->descripcion }}</p>
            </div>
        </section>
    @endif

    @if ($destacado !== null)
        <section class="pas-seccion" aria-labelledby="destacado-titulo">
            <div class="pas-contenedor">
                <div class="pas-destacado__panel">
                    <div @class(['pas-dividido', 'pas-dividido--invertido', 'pas-dividido--sin-foto' => blank($destacado['imagen'])])>
                        @if (filled($destacado['imagen']))
                            <div class="pas-dividido__foto pas-dividido__foto--arco">
                                <x-sitio.imagen :ruta="$destacado['imagen']" :alt="$destacado['nombre']" :ancho="900" :alto="900" sizes="(min-width:768px) 40vw, 90vw" class="pas-dividido__imagen" />
                            </div>
                        @endif
                        <div>
                            <h2 id="destacado-titulo"><span class="pas-cursiva">{{ $nuevo !== null ? 'Nuevo' : 'Recomendado' }}</span><span class="pas-solo-lectores">: </span>{{ $destacado['nombre'] }}</h2>
                            @if (filled($destacado['descripcion']))<p>{{ $destacado['descripcion'] }}</p>@endif
                            @if ($destacado['etiquetas'] !== [])
                                <ul class="pas-etiquetas" role="list">
                                    @foreach ($destacado['etiquetas'] as $etiqueta)<li class="pas-etiqueta">{{ $etiqueta }}</li>@endforeach
                                </ul>
                            @endif
                            <p class="pas-precio">
                                {{ $destacado['precio'] }}
                                @if (filled($destacado['precio_anterior']))<s class="pas-precio__anterior"><span class="pas-solo-lectores">Precio anterior </span>{{ $destacado['precio_anterior'] }}</s>@endif
                            </p>
                            <a class="pas-boton" href="{{ $menu_url }}">Verlo en el menú</a>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    @endif

    @if ($visibles->isNotEmpty())
        <section class="pas-seccion" aria-labelledby="vista-titulo">
            <div class="pas-contenedor">
                <div class="pas-encabezado-seccion pas-encabezado-seccion--centro">
                    <span class="pas-cursiva" aria-hidden="true">Un vistazo</span>
                    <h2 id="vista-titulo">Del menú</h2>
                </div>
                <ul class="pas-lista" role="list">
                    @foreach ($visibles as $plato)
                        <li>
                            <article class="pas-plato">
                                <div class="pas-plato__foto">
                                    <x-sitio.imagen :ruta="$plato['imagen']" :alt="$plato['nombre']" :ancho="900" :alto="675" sizes="(min-width:1024px) 30vw, (min-width:768px) 45vw, 90vw" class="pas-plato__imagen" />
                                </div>
                                <div class="pas-plato__cuerpo">
                                    <h3>{{ $plato['nombre'] }}</h3>
                                    @if (filled($plato['descripcion']))<p class="pas-plato__desc">{{ $plato['descripcion'] }}</p>@endif
                                    @if ($plato['etiquetas'] !== [])
                                        <ul class="pas-etiquetas" role="list">
                                            @foreach ($plato['etiquetas'] as $etiqueta)<li class="pas-etiqueta">{{ $etiqueta }}</li>@endforeach
                                        </ul>
                                    @endif
                                    <p class="pas-precio">
                                        {{ $plato['precio'] }}
                                        @if (filled($plato['precio_anterior']))<s class="pas-precio__anterior"><span class="pas-solo-lectores">Precio anterior </span>{{ $plato['precio_anterior'] }}</s>@endif
                                    </p>
                                </div>
                            </article>
                        </li>
                    @endforeach
                </ul>
                <div class="pas-acciones pas-acciones--centro"><a class="pas-boton" href="{{ $menu_url }}">Ver el menú completo</a></div>
            </div>
        </section>
    @endif

    <x-sitio.pastel.contacto :d="$d" solo="visita" />
    <x-sitio.pastel.reservar :d="$d" />
    <x-sitio.pastel.contacto :d="$d" solo="contacto" />
</main>

<x-sitio.pastel.pie :d="$d" />
</x-sitio.pastel.layout>
