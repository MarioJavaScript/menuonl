@php
    $marcados = $resumen['marcados'];
    $maximo = $resumen['maximo'];
    $enSitio = $resumen['en_sitio'];
    $principal = $resumen['principal'];
@endphp

<div class="flex flex-col gap-3 rounded-xl bg-white p-4 text-sm text-gray-950 ring-1 ring-gray-950/5 dark:bg-gray-900 dark:text-white dark:ring-white/10" role="status">
    <p class="flex flex-wrap items-center gap-2">
        <x-filament::badge :color="$marcados > $maximo ? 'warning' : 'primary'" icon="heroicon-m-star">
            {{ trans_choice('panel.recomendados.contador', $marcados, ['n' => $marcados, 'max' => $maximo]) }}
        </x-filament::badge>
    </p>

    @if ($resumen['sin_marcar_usa_los_primeros'])
        <p class="text-gray-600 dark:text-gray-300">{{ __('panel.recomendados.sin_marcados', ['max' => $maximo]) }}</p>
    @else
        <p class="text-gray-600 dark:text-gray-300">{{ __('panel.recomendados.explicacion', ['max' => $maximo]) }}</p>
        @if ($marcados > $maximo)
            <p class="text-warning-700 dark:text-warning-400">{{ __('panel.recomendados.de_mas', ['n' => $marcados - $maximo, 'max' => $maximo]) }}</p>
        @endif
    @endif

    @if ($resumen['plantilla_con_principal'])
        <p>
            <span class="font-semibold">{{ __('panel.recomendados.principal') }}:</span>
            {{ $principal ? $nombreDe($principal) : __('panel.recomendados.sin_principal') }}
        </p>
        <p class="text-gray-600 dark:text-gray-300">{{ __('panel.recomendados.principal_regla') }}</p>
    @else
        <p class="text-gray-600 dark:text-gray-300">{{ __('panel.recomendados.sin_principal_en_plantilla') }}</p>
    @endif

    @if ($enSitio->isNotEmpty())
        <div>
            <p class="font-semibold">{{ __('panel.recomendados.se_muestran') }}</p>
            <ol class="mt-1 list-decimal ps-5 text-gray-600 dark:text-gray-300">
                @foreach ($enSitio as $producto)
                    <li>{{ $nombreDe($producto) }}</li>
                @endforeach
            </ol>
        </div>
    @endif
</div>
