@php
    /*
     * Plantilla H · Pizzería. Todo sale de los datos del restaurante; una sección sin datos se omite.
     * Portada: nombre (h1), eslogan, descripción, horario de hoy y botones. El platillo destacado es el marcado "Nuevo" y, si no hay,
     * el primero con foto. Los destacados no traen variantes: los platillos con tamaños muestran su precio base aquí y todos sus
     * tamaños con precio en el menú.
     */
    $nombre = trim((string) $r->nombre);
    $nombreNuevo = \App\Services\Catalogos::nombreEtiqueta('nuevo');
    $visibles = collect($destacados)->filter(fn (array $p): bool => ! $p['agotado'])->values();
    $nuevo = $visibles->first(fn (array $p): bool => in_array($nombreNuevo, $p['etiquetas'], true));
    $destacado = $nuevo ?? $visibles->first(fn (array $p): bool => filled($p['imagen'])) ?? $visibles->first();

    $hoy = \Illuminate\Support\Carbon::now($r->zona_horaria ?: config('app.timezone'))->dayOfWeekIso - 1;
    $horarioHoy = collect($horarios)->get($hoy);
    $textoHoy = ($horarioHoy !== null && ! $horarioHoy['cerrado']) ? $horarioHoy['texto'] : null;

    $d = compact('r', 'menu_url', 'horarios', 'direccion', 'telefono', 'telefono_enlace', 'whatsapp_digitos', 'reservas_activas', 'instagram_url', 'facebook_url', 'mapa_url');
@endphp
<x-sitio.pizzeria.layout :r="$r" :titulo="$nombre.(filled($r->eslogan) ? ' · '.$r->eslogan : '')" :descripcion="$r->descripcion" pagina="inicio" :reservas="$reservas_activas">
<x-sitio.pizzeria.cabecera :d="$d" actual="inicio" />

<main id="principal" tabindex="-1">
    <section class="piz-seccion piz-portada" aria-labelledby="portada-titulo">
        <div class="piz-contenedor">
            <div @class(['piz-portada__rejilla', 'piz-portada__rejilla--sin-foto' => blank($hero)])>
                <div class="piz-portada__texto">
                    <h1 class="piz-portada__titulo" id="portada-titulo">{{ $nombre }}</h1>
                    @if (filled($r->eslogan))<p class="piz-portada__eslogan">{{ $r->eslogan }}</p>@endif
                    @if (filled($r->descripcion))<p class="piz-portada__descripcion">{{ $r->descripcion }}</p>@endif
                    @if ($textoHoy !== null)
                        <p class="piz-portada__hoy"><svg class="piz-icono" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18zM12 7v5l3 2"/></svg><span>Hoy: {{ $textoHoy }}</span></p>
                    @endif
                    <div class="piz-botones">
                        <a class="piz-boton" href="{{ $menu_url }}">Ver el menú</a>
                        @if ($reservas_activas)<a class="piz-boton piz-boton--borde" href="#reservar">Reservar mesa</a>@endif
                        @if (filled($whatsapp_digitos))
                            <a class="piz-boton piz-boton--verde" href="https://wa.me/{{ $whatsapp_digitos }}?text={{ rawurlencode('Hola, tengo una pregunta para '.$nombre.'.') }}" rel="noopener"><svg class="piz-icono" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 20l1.3-4.2A8 8 0 1 1 8.4 19z"/><path d="M9.2 8.8c.2 2.4 2.6 4.8 5.6 5.6l1.2-1.4-2-1-.8.8c-.9-.4-1.8-1.3-2.2-2.2l.8-.8-1-2z"/></svg><span>Escribir por WhatsApp</span></a>
                        @endif
                    </div>
                </div>
                @if (filled($hero))
                    <div class="piz-plato">
                        <div class="piz-plato__foto">
                            <x-sitio.imagen :ruta="$hero" :alt="'Portada de '.$nombre" :ancho="1600" :alto="1067" sizes="(min-width:768px) 40vw, 90vw" :prioridad="true" class="piz-plato__imagen" />
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </section>

    @if ($destacado !== null)
        <section class="piz-tomate piz-destacado piz-seccion" aria-labelledby="destacado-titulo">
            <div class="piz-contenedor">
                <div @class(['piz-destacado__rejilla', 'piz-destacado__rejilla--sin-foto' => blank($destacado['imagen'])])>
                    @if (filled($destacado['imagen']))
                        <div class="piz-plato">
                            <div class="piz-plato__foto">
                                <x-sitio.imagen :ruta="$destacado['imagen']" :alt="$destacado['nombre']" :ancho="900" :alto="900" sizes="(min-width:768px) 40vw, 90vw" class="piz-plato__imagen" />
                            </div>
                        </div>
                    @endif
                    <div class="piz-destacado__texto">
                        <h2 class="piz-destacado__titulo" id="destacado-titulo">{{ $nuevo !== null ? 'Nuevo' : 'Recomendado' }}</h2>
                        <p class="piz-destacado__nombre">{{ $destacado['nombre'] }}</p>
                        @if (filled($destacado['descripcion']))<p>{{ $destacado['descripcion'] }}</p>@endif
                        @if ($destacado['etiquetas'] !== [])
                            <ul class="piz-etiquetas" role="list">
                                @foreach ($destacado['etiquetas'] as $etiqueta)<li class="piz-etiqueta">{{ $etiqueta }}</li>@endforeach
                            </ul>
                        @endif
                        <p class="piz-precio">
                            {{ $destacado['precio'] }}
                            @if (filled($destacado['precio_anterior']))<s class="piz-precio__antes"><span class="piz-solo-lectores">Precio anterior </span>{{ $destacado['precio_anterior'] }}</s>@endif
                        </p>
                        <a class="piz-boton piz-boton--papel" href="{{ $menu_url }}">Verlo en el menú</a>
                    </div>
                </div>
            </div>
        </section>
    @endif

    @if ($visibles->isNotEmpty())
        <section class="piz-seccion" aria-labelledby="vista-titulo">
            <div class="piz-contenedor">
                <h2 class="piz-seccion__titulo" id="vista-titulo">Lo que sale del horno</h2>
                <ul class="piz-rejilla piz-rejilla--vista" role="list">
                    @foreach ($visibles as $plato)
                        <li>
                            <article class="piz-tarjeta">
                                <x-sitio.imagen :ruta="$plato['imagen']" :alt="$plato['nombre']" :ancho="800" :alto="800" sizes="(min-width:768px) 20vw, 30vw" class="piz-tarjeta__foto" />
                                <div class="piz-tarjeta__cuerpo">
                                    <h3 class="piz-tarjeta__nombre">{{ $plato['nombre'] }}</h3>
                                    @if ($plato['etiquetas'] !== [])
                                        <ul class="piz-etiquetas" role="list">
                                            @foreach ($plato['etiquetas'] as $etiqueta)<li class="piz-etiqueta">{{ $etiqueta }}</li>@endforeach
                                        </ul>
                                    @endif
                                    @if (filled($plato['descripcion']))<p class="piz-tarjeta__desc">{{ $plato['descripcion'] }}</p>@endif
                                    <p class="piz-precio">
                                        {{ $plato['precio'] }}
                                        @if (filled($plato['precio_anterior']))<s class="piz-precio__antes"><span class="piz-solo-lectores">Precio anterior </span>{{ $plato['precio_anterior'] }}</s>@endif
                                    </p>
                                </div>
                            </article>
                        </li>
                    @endforeach
                </ul>
                <div class="piz-menu-fin"><a class="piz-boton" href="{{ $menu_url }}">Ver el menú completo</a></div>
            </div>
        </section>
    @endif

    <x-sitio.pizzeria.contacto :d="$d" solo="visita" />
    <x-sitio.pizzeria.contacto :d="$d" solo="contacto" />
    <x-sitio.pizzeria.reservar :d="$d" />
</main>

<x-sitio.pizzeria.pie :d="$d" />
</x-sitio.pizzeria.layout>
