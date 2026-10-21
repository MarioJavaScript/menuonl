@props(['d'])
@php
    /*
     * Reservas del sitio (plantilla G). Mismo formulario y mismas reglas que las plantillas A a D: los ids, los nombres de campo y los data-*
     * son los que usa resources/js/sitio.js, y el servidor (ReservaPublicaController) vuelve a validar todo. Solo cambian las clases (pas-).
     * Si el restaurante no tiene reservas activas, el componente no dibuja nada.
     */
    $r = $d['r'];
    $maxPersonas = (int) config('menuonl.reservas.max_personas');
@endphp
@if ($d['reservas_activas'])
<section class="pas-reservar" id="reservar" aria-labelledby="reservar-titulo" tabindex="-1">
    <div class="pas-reservar__interior">
        <div class="pas-encabezado-seccion">
            <span class="pas-cursiva" aria-hidden="true">Guárdanos un lugar</span>
            <h2 class="pas-titulo" id="reservar-titulo">Reservar</h2>
        </div>
        <form class="pas-reserva" id="form-reserva" novalidate
              data-url="{{ url('/api/v1/sitio/'.$r->slug.'/reservas') }}"
              data-disp="{{ url('/api/v1/sitio/'.$r->slug.'/disponibilidad') }}">
            <div class="pas-campo pas-campo--completo"><label class="pas-campo__etiqueta" for="rs-nombre">Nombre</label><input class="pas-campo__entrada" id="rs-nombre" name="nombre" required minlength="2" maxlength="100" autocomplete="name"></div>
            <div class="pas-campo pas-campo--completo"><label class="pas-campo__etiqueta" for="rs-tel">Teléfono</label><input class="pas-campo__entrada" id="rs-tel" name="telefono" type="tel" required maxlength="20" autocomplete="tel" inputmode="tel" placeholder="55 1234 5678"></div>
            <div class="pas-campo"><label class="pas-campo__etiqueta" for="rs-fecha">Fecha</label><input class="pas-campo__entrada" id="rs-fecha" name="fecha" type="date" required min="{{ now($r->zona_horaria)->toDateString() }}" max="{{ now($r->zona_horaria)->addDays((int) config('menuonl.reservas.max_dias'))->toDateString() }}"></div>
            <div class="pas-campo"><label class="pas-campo__etiqueta" for="rs-personas">Personas</label>
                <select class="pas-campo__entrada" id="rs-personas" name="personas">@for ($n = 1; $n <= $maxPersonas; $n++)<option value="{{ $n }}" @selected($n === 2)>{{ $n }}</option>@endfor</select></div>
            <div class="pas-campo pas-campo--completo"><label class="pas-campo__etiqueta" for="rs-hora">Hora</label><select class="pas-campo__entrada" id="rs-hora" name="hora" required disabled><option value="">Primero elige la fecha</option></select></div>
            <div class="pas-campo pas-campo--completo"><label class="pas-campo__etiqueta" for="rs-nota">Nota (opcional)</label><input class="pas-campo__entrada" id="rs-nota" name="nota" maxlength="255" placeholder="Aniversario, alergias, silla para bebé"></div>
            <div class="pas-reserva__trampa" aria-hidden="true"><label>No llenar este campo<input name="sitio_web" tabindex="-1" autocomplete="off"></label></div>
            <input type="hidden" name="ts" value="{{ now()->timestamp }}">
            <button class="pas-boton pas-campo--completo" type="submit">Solicitar reserva</button>
            <div class="pas-reserva__mensaje" id="reserva-msg" role="status" aria-live="polite" tabindex="-1" hidden></div>
        </form>
        <noscript><p class="pas-reservar__alterno">Activa JavaScript para reservar en línea, o usa WhatsApp o teléfono.</p></noscript>
        @if (filled($d['whatsapp_digitos']) || filled($d['telefono_enlace']))
            <div class="pas-reservar__alterno">
                <p>¿Prefieres otro medio?</p>
                <div class="pas-acciones">
                    @if (filled($d['whatsapp_digitos']))
                        <a class="pas-boton pas-boton--contorno" href="https://wa.me/{{ $d['whatsapp_digitos'] }}?text={{ rawurlencode('Hola, quiero reservar en '.$r->nombre.'.') }}" rel="noopener">WhatsApp</a>
                    @endif
                    @if (filled($d['telefono_enlace']))
                        <a class="pas-boton pas-boton--contorno" href="{{ $d['telefono_enlace'] }}">Llamar</a>
                    @endif
                </div>
            </div>
        @endif
    </div>
</section>
@endif
