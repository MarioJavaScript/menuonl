@if ($reservas_activas)
    @php($maxPersonas = (int) config('menuonl.reservas.max_personas'))
    <form class="reserva" id="form-reserva" novalidate
          data-url="{{ url('/api/v1/sitio/'.$r->slug.'/reservas') }}"
          data-disp="{{ url('/api/v1/sitio/'.$r->slug.'/disponibilidad') }}">
        <div class="full"><label for="rs-nombre">Nombre</label><input id="rs-nombre" name="nombre" required minlength="2" maxlength="100" autocomplete="name"></div>
        <div class="full"><label for="rs-tel">Teléfono</label><input id="rs-tel" name="telefono" type="tel" required maxlength="20" autocomplete="tel" inputmode="tel" placeholder="55 1234 5678"></div>
        <div><label for="rs-fecha">Fecha</label><input id="rs-fecha" name="fecha" type="date" required min="{{ now($r->zona_horaria)->toDateString() }}" max="{{ now($r->zona_horaria)->addDays((int) config('menuonl.reservas.max_dias'))->toDateString() }}"></div>
        <div><label for="rs-personas">Personas</label>
            <select id="rs-personas" name="personas">@for ($n = 1; $n <= $maxPersonas; $n++)<option value="{{ $n }}" @selected($n === 2)>{{ $n }}</option>@endfor</select></div>
        <div class="full"><label for="rs-hora">Hora</label><select id="rs-hora" name="hora" required disabled><option value="">Primero elige la fecha</option></select></div>
        <div class="full"><label for="rs-nota">Nota (opcional)</label><input id="rs-nota" name="nota" maxlength="255" placeholder="Aniversario, alergias, silla para bebé…"></div>
        <div class="honeypot" aria-hidden="true"><label>No llenar este campo<input name="sitio_web" tabindex="-1" autocomplete="off"></label></div>
        <input type="hidden" name="ts" value="{{ now()->timestamp }}">
        <button class="btn full" type="submit">Solicitar reserva</button>
        <div class="reserva-msg" id="reserva-msg" role="status" aria-live="polite" tabindex="-1" hidden></div>
    </form>
    <noscript><p>Activa JavaScript para reservar en línea, o usa WhatsApp o teléfono.</p></noscript>
    @if ($whatsapp_digitos || $telefono_enlace)
        <p class="reserva-alt">¿Prefieres otro medio?</p>
    @endif
@endif
@if ($whatsapp_digitos || $telefono_enlace)
    <div class="reservar-contacto">
        @if ($whatsapp_digitos)
            <a class="btn btn-alt" href="https://wa.me/{{ $whatsapp_digitos }}?text={{ rawurlencode('Hola, quiero reservar en '.$r->nombre.'.') }}" rel="noopener">WhatsApp</a>
        @endif
        @if ($telefono_enlace)
            <a class="btn btn-alt" href="{{ $telefono_enlace }}">Llamar</a>
        @endif
    </div>
@endif
