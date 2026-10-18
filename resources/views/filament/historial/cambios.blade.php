@props(['cambios'])

@if (empty($cambios))
    <p class="text-sm text-gray-500 dark:text-gray-400">Sin detalles de cambios registrados.</p>
@else
<div class="space-y-3">
    @foreach ($cambios as $campo => $diff)
        <x-filament::section compact>
            <x-slot name="heading">{{ $campo }}</x-slot>

            <dl class="space-y-2 text-sm">
                <div class="flex flex-wrap items-baseline gap-2">
                    <dt><x-filament::badge color="danger">Antes</x-filament::badge></dt>
                    <dd class="break-all font-mono text-xs">{{ is_array($diff) && array_key_exists('antes', $diff) ? json_encode($diff['antes'], JSON_UNESCAPED_UNICODE) : '-' }}</dd>
                </div>
                <div class="flex flex-wrap items-baseline gap-2">
                    <dt><x-filament::badge color="success">Después</x-filament::badge></dt>
                    <dd class="break-all font-mono text-xs">{{ is_array($diff) && array_key_exists('despues', $diff) ? json_encode($diff['despues'], JSON_UNESCAPED_UNICODE) : '-' }}</dd>
                </div>
            </dl>
        </x-filament::section>
    @endforeach
</div>
@endif
