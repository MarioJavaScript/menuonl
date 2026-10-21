@props(['d'])
@php
    $hayHorario = collect($d['horarios'])->contains('cerrado', false);
    $hayDireccion = filled($d['direccion']);
    $hayEnlaces = filled($d['telefono_enlace']) || filled($d['whatsapp_digitos']) || filled($d['instagram_url']) || filled($d['facebook_url']) || filled($d['mapa_url']);
    $iconos = [
        'telefono' => '<path d="M5 4h3.5l1.8 4.4-2.2 1.4a11 11 0 0 0 5.1 5.1l1.4-2.2L19 14.5V18a2 2 0 0 1-2 2A13 13 0 0 1 3 6a2 2 0 0 1 2-2z"/>',
        'whatsapp' => '<path d="M4 20l1.3-4.2A8 8 0 1 1 8.4 19z"/><path d="M9.2 8.8c.2 2.4 2.6 4.8 5.6 5.6l1.2-1.4-2-1-.8.8c-.9-.4-1.8-1.3-2.2-2.2l.8-.8-1-2z"/>',
        'instagram' => '<rect x="4" y="4" width="16" height="16" rx="4.5"/><circle cx="12" cy="12" r="3.6"/><path d="M16.8 7.2h.01"/>',
        'facebook' => '<path d="M14 8.5V7a1.5 1.5 0 0 1 1.5-1.5H17V2.5h-2.5A3.5 3.5 0 0 0 11 6v2.5H8.5v3H11V21h3v-9.5h2.6l.4-3z"/>',
        'mapa' => '<path d="M12 21s-6.5-5.6-6.5-11a6.5 6.5 0 0 1 13 0c0 5.4-6.5 11-6.5 11z"/><circle cx="12" cy="10" r="2.4"/>',
    ];
@endphp
@if ($hayHorario || $hayDireccion || $hayEnlaces)
<section class="cal-contacto" id="contacto" aria-labelledby="contacto-titulo">
    <div class="cal-contacto__interior">
        <div class="cal-contacto__titulos">
            <h2 class="cal-titulo" id="contacto-titulo">Contacto</h2>
            <p class="cal-acento" aria-hidden="true">visítanos</p>
        </div>
        @if ($hayDireccion || $hayHorario)
            <div class="cal-contacto__rejilla">
                @if ($hayDireccion)
                    <div class="cal-contacto__tarjeta">
                        <h3 class="cal-contacto__subtitulo">Dirección</h3>
                        <address class="cal-contacto__direccion">{{ $d['direccion'] }}</address>
                    </div>
                @endif
                @if ($hayHorario)
                    <div class="cal-contacto__tarjeta">
                        <h3 class="cal-contacto__subtitulo">Horario</h3>
                        <dl class="cal-contacto__horario">
                            @foreach ($d['horarios'] as $h)
                                <div><dt>{{ $h['dia'] }}</dt><dd>{{ $h['texto'] }}</dd></div>
                            @endforeach
                        </dl>
                    </div>
                @endif
            </div>
        @endif
        @if ($hayEnlaces)
            <div class="cal-contacto__botones">
                @if (filled($d['telefono_enlace']))
                    <a class="cal-boton cal-boton--arcilla" href="{{ $d['telefono_enlace'] }}"><svg class="cal-boton__icono" viewBox="0 0 24 24" aria-hidden="true" focusable="false">{!! $iconos['telefono'] !!}</svg><span>Llamar {{ $d['telefono'] }}</span></a>
                @endif
                @if (filled($d['whatsapp_digitos']))
                    <a class="cal-boton cal-boton--arcilla" href="https://wa.me/{{ $d['whatsapp_digitos'] }}" rel="noopener"><svg class="cal-boton__icono" viewBox="0 0 24 24" aria-hidden="true" focusable="false">{!! $iconos['whatsapp'] !!}</svg><span>WhatsApp</span></a>
                @endif
                @if (filled($d['instagram_url']))
                    <a class="cal-boton cal-boton--arcilla" href="{{ $d['instagram_url'] }}" rel="noopener"><svg class="cal-boton__icono" viewBox="0 0 24 24" aria-hidden="true" focusable="false">{!! $iconos['instagram'] !!}</svg><span>Instagram</span></a>
                @endif
                @if (filled($d['facebook_url']))
                    <a class="cal-boton cal-boton--arcilla" href="{{ $d['facebook_url'] }}" rel="noopener"><svg class="cal-boton__icono" viewBox="0 0 24 24" aria-hidden="true" focusable="false">{!! $iconos['facebook'] !!}</svg><span>Facebook</span></a>
                @endif
                @if (filled($d['mapa_url']))
                    <a class="cal-boton cal-boton--arcilla" href="{{ $d['mapa_url'] }}" rel="noopener"><svg class="cal-boton__icono" viewBox="0 0 24 24" aria-hidden="true" focusable="false">{!! $iconos['mapa'] !!}</svg><span>Cómo llegar</span></a>
                @endif
            </div>
        @endif
    </div>
</section>
@endif
