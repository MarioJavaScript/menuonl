{{-- Imagen de muestra fija de una plantilla: un dibujo esquemático con sus colores, no el sitio real. --}}
<svg viewBox="0 0 160 200" class="block w-full h-auto" role="img" aria-hidden="true" focusable="false">
    <rect width="160" height="200" fill="{{ $ficha['fondo'] }}"/>
    <rect x="12" y="12" width="40" height="6" rx="3" fill="{{ $ficha['texto'] }}"/>
    <rect x="96" y="12" width="14" height="6" rx="3" fill="{{ $ficha['texto'] }}" opacity=".5"/>
    <rect x="116" y="12" width="14" height="6" rx="3" fill="{{ $ficha['texto'] }}" opacity=".5"/>
    <rect x="12" y="30" width="136" height="62" rx="4" fill="{{ $ficha['bloque'] }}"/>
    <rect x="22" y="48" width="70" height="9" rx="3" fill="{{ $ficha['texto'] }}"/>
    <rect x="22" y="62" width="48" height="5" rx="2.5" fill="{{ $ficha['texto'] }}" opacity=".55"/>
    <rect x="22" y="75" width="34" height="10" rx="5" fill="{{ $ficha['acento'] }}"/>
    <rect x="12" y="106" width="30" height="30" rx="3" fill="{{ $ficha['bloque'] }}"/>
    <rect x="50" y="110" width="64" height="6" rx="3" fill="{{ $ficha['texto'] }}"/>
    <rect x="50" y="122" width="44" height="5" rx="2.5" fill="{{ $ficha['texto'] }}" opacity=".5"/>
    <rect x="124" y="112" width="24" height="6" rx="3" fill="{{ $ficha['acento'] }}"/>
    <rect x="12" y="146" width="30" height="30" rx="3" fill="{{ $ficha['bloque'] }}"/>
    <rect x="50" y="150" width="58" height="6" rx="3" fill="{{ $ficha['texto'] }}"/>
    <rect x="50" y="162" width="40" height="5" rx="2.5" fill="{{ $ficha['texto'] }}" opacity=".5"/>
    <rect x="124" y="152" width="24" height="6" rx="3" fill="{{ $ficha['acento'] }}"/>
    <rect x="12" y="188" width="136" height="2" rx="1" fill="{{ $ficha['acento'] }}" opacity=".6"/>
</svg>
