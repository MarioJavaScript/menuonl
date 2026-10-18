@extends('invitaciones.layout')

@section('titulo', 'Invitación no disponible')

@section('contenido')
    <div class="text-center py-4">
        <div class="w-14 h-14 mx-auto rounded-full bg-superficie-2 text-texto-medio flex items-center justify-center mb-5">
            <svg class="w-7 h-7" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>

        <h1 class="text-2xl font-bold tracking-tight text-texto-fuerte mb-3">
            Invitación no disponible
        </h1>

        <p class="text-sm text-texto-medio leading-relaxed mb-6">
            Este enlace ya no es válido, ha caducado o ya fue utilizado anteriormente. Por motivos de seguridad, las invitaciones tienen una vigencia máxima de 7 días.
        </p>

        <p class="text-xs text-texto-suave mb-8">
            Si necesitas acceder al menú de tu restaurante, solicita al propietario o administrador que te envíe una nueva invitación.
        </p>

        <div class="space-y-3">
            <a href="{{ route('filament.admin.auth.login') }}" class="boton inline-flex items-center justify-center w-full min-h-[44px] px-6 py-2.5 rounded-xl bg-accion hover:bg-accion-hover text-accion-texto font-semibold text-sm transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-foco">
                Ir al acceso del panel
            </a>
            <a href="{{ url('/') }}" class="inline-flex items-center justify-center w-full min-h-[44px] px-6 py-2.5 rounded-xl text-texto-suave hover:text-texto-fuerte font-medium text-sm transition-colors">
                Volver al inicio
            </a>
        </div>
    </div>
@endsection
