@extends('invitaciones.layout')

@section('titulo', 'Invitación no disponible')

@section('contenido')
    <div class="text-center py-4">
        <div class="w-14 h-14 mx-auto rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 flex items-center justify-center mb-5">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>

        <h1 class="text-2xl font-bold tracking-tight text-slate-950 dark:text-white mb-3">
            Invitación no disponible
        </h1>

        <p class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed mb-6">
            Este enlace ya no es válido, ha caducado o ya fue utilizado anteriormente. Por motivos de seguridad, las invitaciones tienen una vigencia máxima de 7 días.
        </p>

        <p class="text-xs text-slate-500 dark:text-slate-400 mb-8">
            Si necesitas acceder al menú de tu restaurante, solicita al propietario o administrador que te envíe una nueva invitación.
        </p>

        <div class="space-y-3">
            <a href="{{ route('filament.admin.auth.login') }}" class="boton inline-flex items-center justify-center w-full min-h-[44px] px-6 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 dark:bg-white dark:text-slate-950 text-white font-semibold text-sm transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-900">
                Ir al acceso del panel
            </a>
            <a href="{{ url('/') }}" class="inline-flex items-center justify-center w-full min-h-[44px] px-6 py-2.5 rounded-xl text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white font-medium text-sm transition-colors">
                Volver al inicio
            </a>
        </div>
    </div>
@endsection
