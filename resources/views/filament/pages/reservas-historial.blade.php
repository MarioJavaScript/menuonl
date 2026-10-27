{{-- Historial de una reserva: quién hizo qué y cuándo. No guarda datos del comensal. --}}
@if ($eventos->isEmpty())
    <p class="mb-reservas__historial-vacio">{{ __('panel.reservas.historial.vacio') }}</p>
@else
    <ol class="mb-reservas__historial">
        @foreach ($eventos as $evento)
            <li>
                <strong>{{ __('panel.reservas.historial.linea', ['accion' => __("panel.reservas.historial.acciones.{$evento->accion}"), 'quien' => $evento->usuario?->name ?? __('panel.reservas.historial.sistema')]) }}</strong>
                <span>{{ \App\Services\FormatoFecha::fechaHora($evento->created_at, $zona) }}</span>
                @if ($evento->accion === 'movida')
                    <span>{{ __('panel.reservas.historial.movida', [
                        'antes' => trim(($evento->fecha_antes?->toDateString() ?? '').' '.($evento->hora_antes ?? '')),
                        'despues' => trim(($evento->fecha_despues?->toDateString() ?? '').' '.($evento->hora_despues ?? '')),
                    ]) }}</span>
                @endif
            </li>
        @endforeach
    </ol>
@endif
