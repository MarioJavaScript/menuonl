{{-- Control segmentado de apariencia. La lógica vive en public/js/apariencia.js (cargado por x-layout.publico). --}}
@props(['nombre' => 'apariencia'])
<fieldset {{ $attributes->class(['segmentado']) }}>
    <legend>Apariencia</legend>
    <label>
        <input type="radio" name="{{ $nombre }}" value="system" data-apariencia-opcion>
        <span>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="4" width="18" height="12" rx="2"/><path stroke-linecap="round" d="M8 20h8M12 16v4"/></svg>
            Automático
        </span>
    </label>
    <label>
        <input type="radio" name="{{ $nombre }}" value="light" data-apariencia-opcion checked>
        <span>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path stroke-linecap="round" d="M12 2v2m0 16v2M4.93 4.93l1.41 1.41m11.32 11.32 1.41 1.41M2 12h2m16 0h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>
            Claro
        </span>
    </label>
    <label>
        <input type="radio" name="{{ $nombre }}" value="dark" data-apariencia-opcion>
        <span>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linejoin="round" d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/></svg>
            Oscuro
        </span>
    </label>
</fieldset>
