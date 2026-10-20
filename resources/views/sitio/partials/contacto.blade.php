{{-- Contacto, horarios reales y ubicación como bloque estático (sin iframes ni mapas externos). --}}
@php
    $dias = \App\Services\Catalogos::diasSemana();
    $tipos = \App\Services\Catalogos::tiposContacto();
    $hoy = (int) now($restaurante->zona_horaria)->dayOfWeek;
@endphp
<section id="contacto" class="mx-auto max-w-6xl px-5 py-20 md:py-28" aria-labelledby="titulo-contacto">
    <div class="revelar mb-10 max-w-2xl">
        <p class="mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-terracota">Visítanos</p>
        <h2 id="titulo-contacto" class="font-serif text-4xl font-semibold leading-tight tracking-tight md:text-5xl">Horarios y ubicación</h2>
    </div>

    <div class="grid gap-10 md:grid-cols-3">
        <div class="revelar">
            <h3 class="mb-4 flex items-center gap-2 font-serif text-2xl font-semibold"><x-sitio.icono nombre="reloj" class="size-6 text-terracota" />Horarios</h3>
            <table class="w-full text-left">
                <caption class="sr-only">Horario semanal</caption>
                <tbody>
                    @foreach ($dias as $numero => $nombreDia)
                        @php $turnos = $horarios->where('dia_semana', $numero)->where('cerrado', false); @endphp
                        <tr class="border-b border-linea {{ $numero === $hoy ? 'font-semibold text-terracota' : '' }}">
                            <th scope="row" class="py-2.5 pr-4 font-[inherit]">{{ $nombreDia }}</th>
                            <td class="py-2.5 text-right">
                                @if ($turnos->isEmpty())
                                    Cerrado
                                @else
                                    @foreach ($turnos as $turno)
                                        {{ substr($turno->abre, 0, 5) }} - {{ substr($turno->cierra, 0, 5) }}@if (! $loop->last), @endif
                                    @endforeach
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="revelar">
            <h3 class="mb-4 flex items-center gap-2 font-serif text-2xl font-semibold"><x-sitio.icono nombre="telefono" class="size-6 text-terracota" />Contacto</h3>
            <ul class="space-y-2">
                @foreach ($contactos as $contacto)
                    @continue($contacto->tipo === 'direccion')
                    @php
                        $href = match ($contacto->tipo) {
                            'telefono' => str_starts_with($contacto->valor, 'tel:') ? $contacto->valor : 'tel:'.preg_replace('/[^0-9+]/', '', $contacto->valor),
                            'whatsapp' => 'https://wa.me/'.preg_replace('/[^0-9]/', '', $contacto->valor),
                            'email' => str_starts_with($contacto->valor, 'mailto:') ? $contacto->valor : 'mailto:'.$contacto->valor,
                            default => (str_starts_with($contacto->valor, 'http://') || str_starts_with($contacto->valor, 'https://')) ? $contacto->valor : 'https://'.$contacto->valor,
                        };
                    @endphp
                    <li>
                        <a href="{{ $href }}" rel="noopener noreferrer" class="flex min-h-11 flex-col justify-center rounded-xl border border-linea bg-papel px-4 py-2 hover:border-terracota">
                            <span class="text-xs font-semibold uppercase tracking-wider text-suave">{{ $contacto->etiqueta ?: ($tipos[$contacto->tipo] ?? $contacto->tipo) }}</span>
                            <span class="break-all font-medium">{{ $contacto->valor }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="revelar">
            <h3 class="mb-4 flex items-center gap-2 font-serif text-2xl font-semibold"><x-sitio.icono nombre="pin" class="size-6 text-terracota" />Ubicación</h3>
            {{-- Bloque estático decorativo en lugar de un mapa incrustado. --}}
            <div class="overflow-hidden rounded-2xl bg-papel ring-1 ring-linea">
                <svg viewBox="0 0 320 180" class="h-44 w-full" role="img" aria-label="Ilustración esquemática de calles" preserveAspectRatio="xMidYMid slice">
                    <rect width="320" height="180" fill="#e6dccb"/>
                    <g stroke="#fbf8f2" stroke-width="10" fill="none"><path d="M-10 60H330"/><path d="M-10 130H330"/><path d="M90-10V190"/><path d="M230-10V190"/></g>
                    <circle cx="160" cy="95" r="9" fill="#a8431f"/><circle cx="160" cy="95" r="3.5" fill="#fff"/>
                </svg>
                <div class="p-5">
                    @if (filled($direccion))
                        <p class="text-lg font-medium">{{ $direccion }}</p>
                        <p class="mt-4">
                            <a href="{{ $mapaUrl }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-11 items-center gap-2 font-semibold text-terracota underline underline-offset-4">Ver en el mapa<x-sitio.icono nombre="flecha" class="size-4" /></a>
                        </p>
                    @else
                        <p class="text-suave">La dirección aún no está registrada.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>
