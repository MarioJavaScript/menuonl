{{-- Reservaciones: sin base de datos; el formulario compone un mensaje y abre WhatsApp (lógica en resources/js/sitio.js). --}}
<section id="reservar" class="bg-noche py-20 text-papel md:py-28" aria-labelledby="titulo-reservar">
    <div class="mx-auto grid max-w-6xl gap-12 px-5 md:grid-cols-5 md:gap-16">
        <div class="revelar md:col-span-2">
            <p class="mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-brasa">Reservaciones</p>
            <h2 id="titulo-reservar" class="font-serif text-4xl font-semibold leading-tight tracking-tight text-balance md:text-5xl">Resérvanos una mesa</h2>
            <p class="mt-5 text-lg text-papel/85">
                @if ($whatsapp)
                    Llena los datos y se abrirá WhatsApp con tu mensaje listo para enviar. La reservación queda confirmada cuando el restaurante te responde.
                @else
                    Para reservar, llámanos y con gusto te atendemos.
                @endif
            </p>
            @if ($telefono && $telefonoHref)
                <p class="mt-6">
                    <a href="{{ $telefonoHref }}" class="inline-flex min-h-12 items-center gap-2 rounded-full px-6 font-semibold ring-2 ring-papel/70 hover:bg-papel/15">
                        <x-sitio.icono nombre="telefono" class="size-5" />{{ $telefono }}
                    </a>
                </p>
            @endif
        </div>

        <div class="revelar md:col-span-3">
            @if ($whatsapp)
                <form id="form-reserva" class="grid gap-5 rounded-3xl bg-papel p-6 text-tinta shadow-xl sm:grid-cols-2 md:p-8" novalidate data-reserva data-whatsapp="{{ $whatsapp }}" data-restaurante="{{ $restaurante->nombre }}" data-horarios="{{ json_encode($turnosReserva) }}">
                    <div class="sm:col-span-2">
                        <label for="reserva-nombre" class="mb-1.5 block text-sm font-semibold">Nombre</label>
                        <input id="reserva-nombre" name="nombre" type="text" autocomplete="name" maxlength="80" required aria-describedby="error-nombre" class="campo">
                        <p id="error-nombre" class="error-campo" role="alert" hidden></p>
                    </div>
                    <div class="sm:col-span-2">
                        <label for="reserva-telefono" class="mb-1.5 block text-sm font-semibold">Teléfono</label>
                        <input id="reserva-telefono" name="telefono" type="tel" autocomplete="tel" inputmode="tel" maxlength="20" required aria-describedby="error-telefono" class="campo">
                        <p id="error-telefono" class="error-campo" role="alert" hidden></p>
                    </div>
                    <div>
                        <label for="reserva-fecha" class="mb-1.5 block text-sm font-semibold">Fecha</label>
                        <input id="reserva-fecha" name="fecha" type="date" required aria-describedby="error-fecha" class="campo">
                        <p id="error-fecha" class="error-campo" role="alert" hidden></p>
                    </div>
                    <div>
                        <label for="reserva-hora" class="mb-1.5 block text-sm font-semibold">Hora</label>
                        <input id="reserva-hora" name="hora" type="time" required aria-describedby="error-hora" class="campo">
                        <p id="error-hora" class="error-campo" role="alert" hidden></p>
                    </div>
                    <div class="sm:col-span-2">
                        <label for="reserva-personas" class="mb-1.5 block text-sm font-semibold">Personas</label>
                        <select id="reserva-personas" name="personas" required aria-describedby="error-personas" class="campo">
                            <option value="">Elige cuántas personas</option>
                            @for ($i = 1; $i <= 12; $i++)
                                <option value="{{ $i }}">{{ $i }} {{ $i === 1 ? 'persona' : 'personas' }}</option>
                            @endfor
                        </select>
                        <p id="error-personas" class="error-campo" role="alert" hidden></p>
                    </div>
                    <div class="sm:col-span-2">
                        <label for="reserva-notas" class="mb-1.5 block text-sm font-semibold">Notas <span class="font-normal text-suave">(opcional)</span></label>
                        <textarea id="reserva-notas" name="notas" rows="3" maxlength="300" aria-describedby="error-notas" class="campo"></textarea>
                        <p id="error-notas" class="error-campo" role="alert" hidden></p>
                    </div>
                    <div class="sm:col-span-2">
                        <button type="submit" class="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-full bg-terracota px-7 font-semibold text-papel hover:bg-terracota-osc">
                            <x-sitio.icono nombre="whatsapp" class="size-5" />Enviar por WhatsApp
                        </button>
                        <p id="estado-reserva" class="mt-3 text-sm font-semibold text-emerald-800" role="status" hidden></p>
                        <noscript><p class="mt-3 text-sm">Este formulario necesita JavaScript. @if ($telefono)Puedes llamarnos al {{ $telefono }}.@endif</p></noscript>
                    </div>
                </form>
            @else
                <div class="rounded-3xl bg-papel p-8 text-tinta shadow-xl">
                    <p class="font-serif text-2xl font-semibold">Para reservar, llámanos</p>
                    <p class="mt-3 text-suave">Este restaurante aún no tiene WhatsApp configurado para reservaciones.</p>
                </div>
            @endif
        </div>
    </div>
</section>
