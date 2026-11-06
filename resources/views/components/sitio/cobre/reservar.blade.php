@props(['d'])
@php
    /*
     * Reservas del sitio (plantilla O). Mismo formulario y mismas reglas que las plantillas A a D: los ids, los nombres de campo y los data-*
     * son los que usa resources/js/sitio.js, y el servidor (ReservaPublicaController) vuelve a validar todo. Solo cambian las clases (cob-).
     * Si el restaurante no tiene reservas activas, el componente no dibuja nada.
     */
    $r = $d['r'];
    $maxPersonas = (int) config('menuonl.reservas.max_personas');
@endphp
@if ($d['reservas_activas'])
<section class="cob-reservar cob-seccion" id="reservar" aria-labelledby="reservar-titulo" tabindex="-1">
    <div class="cob-contenedor">
    <div class="cob-reservar__bloque">
    <div class="cob-reservar__rejilla">
        <div>
            <h2 class="cob-titulo" id="reservar-titulo">Reserva tu mesa</h2>
        </div>
        <div>
        <form class="cob-reserva" id="form-reserva" novalidate
              data-url="{{ url('/api/v1/sitio/'.$r->slug.'/reservas') }}"
              data-disp="{{ url('/api/v1/sitio/'.$r->slug.'/disponibilidad') }}">
            <div class="cob-campo cob-campo--completo"><label class="cob-campo__etiqueta" for="rs-nombre">Nombre</label><input class="cob-campo__entrada" id="rs-nombre" name="nombre" required minlength="2" maxlength="100" autocomplete="name"></div>
            <div class="cob-campo cob-campo--completo"><label class="cob-campo__etiqueta" for="rs-tel">Teléfono</label><input class="cob-campo__entrada" id="rs-tel" name="telefono" type="tel" required maxlength="20" autocomplete="tel" inputmode="tel" placeholder="55 1234 5678"></div>
            <div class="cob-campo"><label class="cob-campo__etiqueta" for="rs-fecha">Fecha</label><input class="cob-campo__entrada" id="rs-fecha" name="fecha" type="date" required min="{{ now($r->zona_horaria)->toDateString() }}" max="{{ now($r->zona_horaria)->addDays((int) config('menuonl.reservas.max_dias'))->toDateString() }}"></div>
            <div class="cob-campo"><label class="cob-campo__etiqueta" for="rs-personas">Personas</label>
                <select class="cob-campo__entrada" id="rs-personas" name="personas">@for ($n = 1; $n <= $maxPersonas; $n++)<option value="{{ $n }}" @selected($n === 2)>{{ $n }}</option>@endfor</select></div>
            <div class="cob-campo cob-campo--completo"><label class="cob-campo__etiqueta" for="rs-hora">Hora</label><select class="cob-campo__entrada" id="rs-hora" name="hora" required disabled><option value="">Primero elige la fecha</option></select></div>
            <div class="cob-campo cob-campo--completo"><label class="cob-campo__etiqueta" for="rs-nota">Nota (opcional)</label><input class="cob-campo__entrada" id="rs-nota" name="nota" maxlength="255" placeholder="Aniversario, alergias, silla para bebé"></div>
            <div class="cob-reserva__trampa" aria-hidden="true"><label>No llenar este campo<input name="sitio_web" tabindex="-1" autocomplete="off"></label></div>
            <input type="hidden" name="ts" value="{{ now()->timestamp }}">
            <button class="cob-boton cob-campo--completo" type="submit">Solicitar reserva</button>
            @include('sitio.partes.aviso-privacidad', ['clase' => 'cob-campo--completo'])
            <div class="cob-reserva__mensaje" id="reserva-msg" role="status" aria-live="polite" tabindex="-1" hidden></div>
        </form>
        <noscript><p class="cob-reservar__alterno">Activa JavaScript para reservar en línea, o usa WhatsApp o teléfono.</p></noscript>
        @if (filled($d['whatsapp_digitos']) || filled($d['telefono_enlace']))
            <div class="cob-reservar__alterno">
                <p>¿Prefieres otro medio?</p>
                <div class="cob-acciones">
                    @if (filled($d['whatsapp_digitos']))
                        <a class="cob-boton cob-boton--borde" href="https://wa.me/{{ $d['whatsapp_digitos'] }}?text={{ rawurlencode('Hola, quiero reservar en '.$r->nombre.'.') }}" rel="noopener">WhatsApp</a>
                    @endif
                    @if (filled($d['telefono_enlace']))
                        <a class="cob-boton cob-boton--borde" href="{{ $d['telefono_enlace'] }}">Llamar</a>
                    @endif
                </div>
            </div>
        @endif
        </div>
    </div>
    </div>
    </div>
</section>
@endif
