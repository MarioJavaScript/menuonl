@props(['d'])
@php
    /*
     * Horario y visita de la plantilla P: sección con el horario (lunes primero, hoy marcado según la zona del restaurante)
     * y la dirección con sus botones. Cada pieza se omite si falta su dato; sin nada que mostrar, la banda no se dibuja.
     */
    $hayHorario = collect($d['horarios'])->contains('cerrado', false);
    $hayUbicacion = filled($d['direccion']) || filled($d['mapa_url']);
    $hayBotones = filled($d['mapa_url']) || filled($d['telefono_enlace']) || filled($d['whatsapp_digitos']) || filled($d['instagram_url']) || filled($d['facebook_url']);
    $hoy = \Illuminate\Support\Carbon::now($d['r']->zona_horaria ?: config('app.timezone'))->dayOfWeekIso - 1;
@endphp
@if ($hayHorario || $hayUbicacion || $hayBotones)
<section class="caf-visita caf-seccion" id="visitanos" aria-label="Horario y visita">
    <div class="caf-contenedor caf-dos">
        @if ($hayHorario)
            <div id="horario">
                <h2 class="caf-titulo" id="horario-titulo">Horario</h2>
                <dl class="caf-horario">
                    @foreach ($d['horarios'] as $i => $h)
                        @if ($i === $hoy)
                            <dt class="caf-horario__hoy">{{ ucfirst($h['dia']) }}<span class="caf-solo-lectores"> (hoy)</span></dt>
                            <dd class="caf-horario__hoy" aria-current="date">{{ $h['texto'] }}</dd>
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
                <h2 class="caf-titulo" id="ubicacion-titulo">{{ $hayUbicacion ? 'Visítanos' : 'Contacto' }}</h2>
                @if (filled($d['direccion']))<address class="caf-direccion">{{ $d['direccion'] }}</address>@endif
                @if (filled($d['telefono']))<p class="caf-direccion">{{ $d['telefono'] }}</p>@endif
                @if ($hayBotones)
                    <div class="caf-acciones">
                        @if (filled($d['mapa_url']))<a class="caf-boton" href="{{ $d['mapa_url'] }}" rel="noopener">Cómo llegar</a>@endif
                        @if (filled($d['whatsapp_digitos']))<a class="caf-boton caf-boton--borde" href="https://wa.me/{{ $d['whatsapp_digitos'] }}" rel="noopener">WhatsApp</a>@endif
                        @if (filled($d['telefono_enlace']))<a class="caf-boton caf-boton--borde" href="{{ $d['telefono_enlace'] }}">Llamar</a>@endif
                        @if (filled($d['instagram_url']))<a class="caf-boton caf-boton--borde" href="{{ $d['instagram_url'] }}" rel="noopener">Instagram</a>@endif
                        @if (filled($d['facebook_url']))<a class="caf-boton caf-boton--borde" href="{{ $d['facebook_url'] }}" rel="noopener">Facebook</a>@endif
                    </div>
                @endif
            </div>
        @endif
    </div>
</section>
@endif
