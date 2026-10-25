@props(['d'])
<footer class="bar-pie">
    <div class="bar-pie__interior">
        <p>{{ $d['r']->nombre }}@if (filled($d['direccion'])) · {{ $d['direccion'] }}@endif</p>
        @if (filled($d['instagram_url']) || filled($d['facebook_url']))
            <ul class="bar-pie__redes">
                @if (filled($d['instagram_url']))<li><a href="{{ $d['instagram_url'] }}" rel="noopener" aria-label="Instagram de {{ $d['r']->nombre }}"><svg class="bar-icono" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="4" y="4" width="16" height="16" rx="4.5"/><circle cx="12" cy="12" r="3.6"/><path d="M16.8 7.2h.01"/></svg></a></li>@endif
                @if (filled($d['facebook_url']))<li><a href="{{ $d['facebook_url'] }}" rel="noopener" aria-label="Facebook de {{ $d['r']->nombre }}"><svg class="bar-icono" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M14 8.5V7a1.5 1.5 0 0 1 1.5-1.5H17V2.5h-2.5A3.5 3.5 0 0 0 11 6v2.5H8.5v3H11V21h3v-9.5h2.6l.4-3z"/></svg></a></li>@endif
            </ul>
        @endif
        <p class="bar-pie__nota">Sitio creado con {{ config('app.name') }}</p>
    </div>
</footer>
