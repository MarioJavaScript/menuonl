@extends('invitaciones.layout')

@section('titulo', 'Aceptar invitación a ' . $invitacion->restaurante->nombre)

@section('contenido')
    <div class="text-center mb-6">
        <div class="w-12 h-12 mx-auto rounded-2xl bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center mb-4">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 19v-8.93a2 2 0 01.89-1.664l7-4.666a2 2 0 012.22 0l7 4.666A2 2 0 0121 10.07V19M3 19a2 2 0 002 2h14a2 2 0 002-2M3 19l6.75-4.5M21 19l-6.75-4.5M3 10l6.75 4.5M21 10l-6.75 4.5m0 0l-1.14.76a2 2 0 01-2.22 0l-1.14-.76"/>
            </svg>
        </div>
        <h1 class="text-2xl font-bold tracking-tight text-slate-950 dark:text-white mb-2">
            Te damos la bienvenida al equipo
        </h1>
        <p class="text-sm text-slate-600 dark:text-slate-400">
            Has recibido una invitación para gestionar el menú digital de <strong class="text-slate-900 dark:text-white">{{ $invitacion->restaurante->nombre }}</strong>.
        </p>
    </div>

    <!-- Indicador de Perfil y Rol -->
    <div class="mb-6 p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700/80 text-xs text-slate-700 dark:text-slate-300 space-y-1">
        <div class="flex items-center justify-between">
            <span class="font-medium text-slate-500 dark:text-slate-400">Perfil asignado:</span>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full font-bold bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">
                {{ $rol }}
            </span>
        </div>
        <div class="flex items-center justify-between">
            <span class="font-medium text-slate-500 dark:text-slate-400">Correo invitado:</span>
            <span class="font-semibold text-slate-900 dark:text-white">{{ $invitacion->email }}</span>
        </div>
        <p class="pt-2 text-[11px] text-slate-500 dark:text-slate-400 leading-normal border-t border-slate-200/60 dark:border-slate-700/60">
            @if ($invitacion->rol === 'propietario')
                Acceso completo para administrar menú, mesas, horarios, integrantes del equipo y ajustes del restaurante.
            @else
                Podrás crear y actualizar platillos, precios, descripciones y disponibilidad de la carta digital.
            @endif
        </p>
    </div>

    @if ($cuentaExistente && ! $sesionCorrecta)
        <div class="p-4 rounded-2xl bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800 text-sm text-blue-900 dark:text-blue-200 mb-6">
            <p class="mb-3">
                Ya existe una cuenta registrada con el correo <strong>{{ $invitacion->email }}</strong>. Inicia sesión para vincular este restaurante a tu cuenta existente.
            </p>
            <a class="boton inline-flex items-center justify-center w-full min-h-[48px] px-6 py-3 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-semibold text-sm transition-all shadow-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-900 dark:focus-visible:ring-white" href="{{ route('filament.admin.auth.login') }}">
                Iniciar sesión para vincular
            </a>
        </div>
    @else
        <form method="POST" action="{{ route('invitaciones.aceptar', ['token' => $token]) }}" class="space-y-4">
            @csrf
            @unless ($cuentaExistente)
                <div>
                    <label for="name" class="block text-sm font-semibold text-slate-900 dark:text-white mb-1">
                        Nombre completo
                    </label>
                    <input
                        id="name"
                        name="name"
                        type="text"
                        value="{{ old('name') }}"
                        required
                        maxlength="255"
                        autocomplete="name"
                        placeholder="Ej. Sofía Morales"
                        class="w-full px-4 py-3 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 dark:focus:ring-white transition-all"
                    >
                    @error('name') <p class="error text-xs text-rose-600 mt-1" role="alert">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="password" class="block text-sm font-semibold text-slate-900 dark:text-white mb-1">
                        Crear contraseña
                    </label>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        required
                        autocomplete="new-password"
                        placeholder="Mínimo 10 caracteres"
                        class="w-full px-4 py-3 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 dark:focus:ring-white transition-all"
                    >
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">
                        Debe incluir al menos 10 caracteres combinando letras y números.
                    </p>
                    @error('password') <p class="error text-xs text-rose-600 mt-1" role="alert">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-semibold text-slate-900 dark:text-white mb-1">
                        Confirmar contraseña
                    </label>
                    <input
                        id="password_confirmation"
                        name="password_confirmation"
                        type="password"
                        required
                        autocomplete="new-password"
                        placeholder="Repite tu contraseña"
                        class="w-full px-4 py-3 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 dark:focus:ring-white transition-all"
                    >
                </div>
            @endunless

            <button
                type="submit"
                class="boton w-full min-h-[48px] px-6 py-3 rounded-xl bg-slate-900 hover:bg-slate-800 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-100 text-white font-semibold text-sm transition-all shadow-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-900 dark:focus-visible:ring-white cursor-pointer"
            >
                Aceptar invitación y entrar al panel
            </button>
        </form>
    @endif
@endsection
