@props(['actual', 'cambios', 'reservasPendientes'])

<div class="space-y-3 text-sm">
    <p class="font-medium">{{ __('panel.planes.cambiar.actual', ['plan' => $actual]) }}</p>

    @if ($cambios === null)
        <p class="text-gray-500 dark:text-gray-400">{{ __('panel.planes.cambiar.sin_cambio') }}</p>
    @else
        @if ($cambios['gana'] !== [])
            <div>
                <p class="font-medium text-success-600 dark:text-success-400">{{ __('panel.planes.cambiar.gana') }}</p>
                <ul class="list-disc space-y-1 pl-5">
                    @foreach ($cambios['gana'] as $linea)
                        <li>{{ $linea }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($cambios['pierde'] !== [])
            <div>
                <p class="font-medium text-danger-600 dark:text-danger-400">{{ __('panel.planes.cambiar.pierde') }}</p>
                <ul class="list-disc space-y-1 pl-5">
                    @foreach ($cambios['pierde'] as $linea)
                        <li>{{ $linea }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($reservasPendientes > 0)
            <p class="font-medium text-warning-600 dark:text-warning-400">{{ trans_choice('panel.planes.cambiar.reservas_pendientes', $reservasPendientes, ['count' => $reservasPendientes]) }}</p>
        @endif

        <p class="text-gray-500 dark:text-gray-400">{{ __('panel.planes.cambiar.sigue') }}</p>
        <p class="text-gray-500 dark:text-gray-400">{{ __('panel.planes.cambiar.superadmin') }}</p>
    @endif
</div>
