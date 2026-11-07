@props(['d'])
@php
    /*
     * Reservas del sitio (plantilla L). Mismo formulario y mismas reglas que las plantillas A a D: los ids, los nombres de campo y los data-*
     * son los que usa resources/js/sitio.js, y el servidor (ReservaPublicaController) vuelve a validar todo. Solo cambian las clases (esm-).
     * Si el restaurante no tiene reservas activas, el componente no dibuja nada.
     */
    $r = $d['r'];
    $maxPersonas = (int) config('menuonl.reservas.max_personas');
@endphp
@if ($d['reservas_activas'])
<section class="esm-reservar esm-seccion" id="reservar" aria-labelledby="reservar-titulo" tabindex="-1">
    <div class="esm-contenedor">
    <div class="esm-reservar__rejilla">
        <div>
            <h2 class="esm-titulo" id="reservar-titulo">Reserva tu mesa</h2>
        </div>
        <div>
        <form class="esm-reserva" id="form-reserva" novalidate
              data-url="{{ url('/api/v1/sitio/'.$r->slug.'/reservas') }}"
              data-disp="{{ url('/api/v1/sitio/'.$r->slug.'/disponibilidad') }}">
            <div class="esm-campo esm-campo--completo"><label class="esm-campo__etiqueta" for="rs-nombre">Nombre</label><input class="esm-campo__entrada" id="rs-nombre" name="nombre" required minlength="2" maxlength="100" autocomplete="name"></div>
            <div class="esm-campo esm-campo--completo"><label class="esm-campo__etiqueta" for="rs-tel">Teléfono</label><input class="esm-campo__entrada" id="rs-tel" name="telefono" type="tel" required maxlength="20" autocomplete="tel" inputmode="tel" placeholder="55 1234 5678"></div>
            <div class="esm-campo"><label class="esm-campo__etiqueta" for="rs-fecha">Fecha</label><input class="esm-campo__entrada" id="rs-fecha" name="fecha" type="date" required min="{{ now($r->zona_horaria)->toDateString() }}" max="{{ now($r->zona_horaria)->addDays((int) config('menuonl.reservas.max_dias'))->toDateString() }}"></div>
            <div class="esm-campo"><label class="esm-campo__etiqueta" for="rs-personas">Personas</label>
                <select class="esm-campo__entrada" id="rs-personas" name="personas">@for ($n = 1; $n <= $maxPersonas; $n++)<option value="{{ $n }}" @selected($n === 2)>{{ $n }}</option>@endfor</select></div>
            <div class="esm-campo esm-campo--completo"><label class="esm-campo__etiqueta" for="rs-hora">Hora</label><select class="esm-campo__entrada" id="rs-hora" name="hora" required disabled><option value="">Primero elige la fecha</option></select></div>
            <div class="esm-campo esm-campo--completo"><label class="esm-campo__etiqueta" for="rs-nota">Nota (opcional)</label><input class="esm-campo__entrada" id="rs-nota" name="nota" maxlength="255" placeholder="Aniversario, mesa cerca de la ventana" aria-describedby="rs-nota-ayuda">@include('sitio.partes.ayuda-nota')</div>
            <div class="esm-reserva__trampa" aria-hidden="true"><label>No llenar este campo<input name="sitio_web" tabindex="-1" autocomplete="off"></label></div>
            <input type="hidden" name="ts" value="{{ now()->timestamp }}">
            <button class="esm-boton esm-campo--completo" type="submit">Solicitar reserva</button>
            @include('sitio.partes.aviso-privacidad', ['clase' => 'esm-campo--completo'])
            <div class="esm-reserva__mensaje" id="reserva-msg" role="status" aria-live="polite" tabindex="-1" hidden></div>
        </form>
        <noscript><p class="esm-reservar__alterno">Activa JavaScript para reservar en línea, o usa WhatsApp o teléfono.</p></noscript>
        @if (filled($d['whatsapp_digitos']) || filled($d['telefono_enlace']))
            <div class="esm-reservar__alterno">
                <p>¿Prefieres otro medio?</p>
                <div class="esm-acciones">
                    @if (filled($d['whatsapp_digitos']))
                        <a class="esm-boton esm-boton--borde" href="https://wa.me/{{ $d['whatsapp_digitos'] }}?text={{ rawurlencode('Hola, quiero reservar en '.$r->nombre.'.') }}" rel="noopener">WhatsApp</a>
                    @endif
                    @if (filled($d['telefono_enlace']))
                        <a class="esm-boton esm-boton--borde" href="{{ $d['telefono_enlace'] }}">Llamar</a>
                    @endif
                </div>
            </div>
        @endif
        </div>
    </div>
    </div>
</section>
@endif
