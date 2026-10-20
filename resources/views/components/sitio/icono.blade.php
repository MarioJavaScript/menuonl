@props(['nombre'])
{{-- Íconos propios del prototipo de sitio (trazo, 24x24). Siempre decorativos: el texto vecino dice lo mismo. --}}
<svg {{ $attributes->class(['shrink-0']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
    @switch($nombre)
        @case('reloj')
            <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>
            @break
        @case('telefono')
            <path d="M5 4h4l2 5-2.5 1.5a11 11 0 005 5L15 13l5 2v4a2 2 0 01-2 2A16 16 0 013 6a2 2 0 012-2z"/>
            @break
        @case('pin')
            <path d="M12 21s-6.5-5.6-6.5-11a6.5 6.5 0 0113 0c0 5.4-6.5 11-6.5 11z"/><circle cx="12" cy="10" r="2.4"/>
            @break
        @case('whatsapp')
            <path d="M4 20l1.3-4.1A8 8 0 1112 20a8 8 0 01-3.8-1L4 20z"/><path d="M9 9.2c0 3 2.8 5.8 5.8 5.8l1.2-1.4-1.9-1-.9.7a4 4 0 01-1.9-1.9l.7-.9-1-1.9L9 9.2z"/>
            @break
        @case('flecha')
            <path d="M5 12h14M13 6l6 6-6 6"/>
            @break
        @case('menu')
            <path d="M4 7h16M4 12h16M4 17h16"/>
            @break
        @case('cerrar')
            <path d="M6 6l12 12M18 6L6 18"/>
            @break
        @case('check')
            <path d="M5 12.5l4.5 4.5L19 7.5"/>
            @break
        @case('calendario')
            <rect x="4" y="5" width="16" height="15" rx="2"/><path d="M4 10h16M9 3v4M15 3v4"/>
            @break
    @endswitch
</svg>
