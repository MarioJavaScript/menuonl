@props(['d', 'numero' => null])
@php
    /*
     * Horario y visita de la plantilla O: tarjeta con el horario (lunes primero, hoy marcado según la zona del restaurante)
     * y la dirección con sus botones. Cada pieza se omite si falta su dato; sin nada que mostrar, la tarjeta no se dibuja.
     * $numero es la cuenta de secciones numeradas ya dibujadas en el sitio (null en el menú: sin números decorativos).
     */
    $hayHorario = collect($d['horarios'])->contains('cerrado', false);
    $hayUbicacion = filled($d['direccion']) || filled($d['mapa_url']);
    $hayBotones = filled($d['mapa_url']) || filled($d['telefono_enlace']) || filled($d['whatsapp_digitos']) || filled($d['instagram_url']) || filled($d['facebook_url']);
    $siguiente = $numero;
    $hoy = \Illuminate\Support\Carbon::now($d['r']->zona_horaria ?: config('app.timezone'))->dayOfWeekIso - 1;
@endphp
@if ($hayHorario || $hayUbicacion || $hayBotones)
<section class="cob-visita cob-seccion" id="visitanos" aria-label="Horario y visita">
    <div class="cob-contenedor cob-dos">
        @if ($hayHorario)
            <div id="horario">
                @if ($numero !== null)<span class="cob-numero" aria-hidden="true">{{ sprintf('%02d', ++$siguiente) }}</span>@endif
                <h2 class="cob-titulo" id="horario-titulo">Horario</h2>
                <dl class="cob-horario">
                    @foreach ($d['horarios'] as $i => $h)
                        @if ($i === $hoy)
                            <dt class="cob-horario__hoy">{{ ucfirst($h['dia']) }}<span class="cob-solo-lectores"> (hoy)</span></dt>
                            <dd class="cob-horario__hoy" aria-current="date">{{ $h['texto'] }}</dd>
                        @else
                            <dt>{{ ucfirst($h['dia']) }}</dt>
                            <dd>{{ $h['texto'] }}</dd>
                        @endif
                    @endforeach
                </dl>
            </div>
        @endif
        @if ($hayUbicacion || $hayBotones)
            <div id="ubicacion">
                @if ($numero !== null)<span class="cob-numero" aria-hidden="true">{{ sprintf('%02d', ++$siguiente) }}</span>@endif
                <h2 class="cob-titulo" id="ubicacion-titulo">{{ $hayUbicacion ? 'Visítanos' : 'Contacto' }}</h2>
                @if (filled($d['direccion']))<address class="cob-direccion">{{ $d['direccion'] }}</address>@endif
                @if (filled($d['telefono']))<p class="cob-direccion">{{ $d['telefono'] }}</p>@endif
                @if ($hayBotones)
                    <div class="cob-acciones">
                        @if (filled($d['mapa_url']))<a class="cob-boton cob-boton--claro" href="{{ $d['mapa_url'] }}" rel="noopener">Cómo llegar</a>@endif
                        @if (filled($d['whatsapp_digitos']))<a class="cob-boton cob-boton--borde" href="https://wa.me/{{ $d['whatsapp_digitos'] }}" rel="noopener">WhatsApp</a>@endif
                        @if (filled($d['telefono_enlace']))<a class="cob-boton cob-boton--borde" href="{{ $d['telefono_enlace'] }}">Llamar</a>@endif
                        @if (filled($d['instagram_url']))<a class="cob-boton cob-boton--borde" href="{{ $d['instagram_url'] }}" rel="noopener">Instagram</a>@endif
                        @if (filled($d['facebook_url']))<a class="cob-boton cob-boton--borde" href="{{ $d['facebook_url'] }}" rel="noopener">Facebook</a>@endif
                    </div>
                @endif
            </div>
        @endif
    </div>
</section>
@endif
