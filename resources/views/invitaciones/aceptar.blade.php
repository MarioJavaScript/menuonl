@extends('invitaciones.layout')

@section('titulo', 'Aceptar invitación a ' . $invitacion->restaurante->nombre)

@section('contenido')
    <div class="text-center mb-6">
        <div class="w-12 h-12 mx-auto rounded-2xl bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center mb-4">
            <svg class="w-6 h-6" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 19v-8.93a2 2 0 01.89-1.664l7-4.666a2 2 0 012.22 0l7 4.666A2 2 0 0121 10.07V19M3 19a2 2 0 002 2h14a2 2 0 002-2M3 19l6.75-4.5M21 19l-6.75-4.5M3 10l6.75 4.5M21 10l-6.75 4.5m0 0l-1.14.76a2 2 0 01-2.22 0l-1.14-.76"/>
            </svg>
        </div>
        <h1 class="text-2xl font-bold tracking-tight text-texto-fuerte mb-2">
            Te damos la bienvenida al equipo
        </h1>
        <p class="text-sm text-texto-suave">
            Has recibido una invitación para gestionar el menú digital de <strong class="text-texto-fuerte">{{ $invitacion->restaurante->nombre }}</strong>.
        </p>
    </div>

    <!-- Indicador de Perfil y Rol -->
    <div class="mb-6 p-4 rounded-2xl bg-superficie-2/60 border border-borde text-xs text-texto-medio space-y-1">
        <div class="flex items-center justify-between">
            <span class="font-medium text-texto-suave">Perfil asignado:</span>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full font-bold bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">
                {{ $rol }}
            </span>
        </div>
        <div class="flex items-center justify-between">
            <span class="font-medium text-texto-suave">Correo invitado:</span>
            <span class="font-semibold text-texto-fuerte">{{ $invitacion->email }}</span>
        </div>
        <p class="pt-2 text-xs text-texto-suave leading-normal border-t border-borde">
            @if ($invitacion->rol === 'propietario')
                Acceso completo para administrar menú, mesas, horarios, integrantes del equipo y ajustes del restaurante.
            @else
                Podrás crear y actualizar platillos, precios, descripciones y disponibilidad del menú digital.
            @endif
        </p>
    </div>

    @if ($cuentaExistente && ! $sesionCorrecta)
        <div class="p-4 rounded-2xl bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800 text-sm text-blue-900 dark:text-blue-200 mb-6">
            <p class="mb-3">
                Ya existe una cuenta registrada con el correo <strong>{{ $invitacion->email }}</strong>. Inicia sesión para vincular este restaurante a tu cuenta existente.
            </p>
            <a class="boton inline-flex items-center justify-center w-full min-h-[48px] px-6 py-3 rounded-xl bg-accion hover:bg-accion-hover text-accion-texto font-semibold text-sm transition-all shadow-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-foco" href="{{ route('filament.admin.auth.login') }}">
                Iniciar sesión para vincular
            </a>
        </div>
    @else
        <form method="POST" action="{{ route('invitaciones.aceptar', ['token' => $token]) }}" class="space-y-4">
            @csrf
            @unless ($cuentaExistente)
                <div>
                    <label for="name" class="block text-sm font-semibold text-texto-fuerte mb-1">
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
                        @if ($errors->has('name')) aria-invalid="true" aria-describedby="name-error" @endif
                        class="w-full min-h-[44px] px-4 py-3 rounded-xl border border-borde-control bg-fondo text-texto-fuerte text-sm focus:outline-none focus:ring-2 focus:ring-foco focus-visible:ring-2 focus-visible:ring-foco transition-all"
                    >
                    @error('name') <p id="name-error" class="error text-xs text-peligro-texto mt-1" role="alert">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="password" class="block text-sm font-semibold text-texto-fuerte mb-1">
                        Crear contraseña
                    </label>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        required
                        autocomplete="new-password"
                        placeholder="Mínimo 10 caracteres"
                        aria-describedby="{{ $errors->has('password') ? 'password-help password-error' : 'password-help' }}"
                        @if ($errors->has('password')) aria-invalid="true" @endif
                        class="w-full min-h-[44px] px-4 py-3 rounded-xl border border-borde-control bg-fondo text-texto-fuerte text-sm focus:outline-none focus:ring-2 focus:ring-foco focus-visible:ring-2 focus-visible:ring-foco transition-all"
                    >
                    <p id="password-help" class="text-xs text-texto-suave mt-1">
                        Debe incluir al menos 10 caracteres combinando letras y números.
                    </p>
                    @error('password') <p id="password-error" class="error text-xs text-peligro-texto mt-1" role="alert">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-semibold text-texto-fuerte mb-1">
                        Confirmar contraseña
                    </label>
                    <input
                        id="password_confirmation"
                        name="password_confirmation"
                        type="password"
                        required
                        autocomplete="new-password"
                        placeholder="Repite tu contraseña"
                        class="w-full min-h-[44px] px-4 py-3 rounded-xl border border-borde-control bg-fondo text-texto-fuerte text-sm focus:outline-none focus:ring-2 focus:ring-foco focus-visible:ring-2 focus-visible:ring-foco transition-all"
                    >
                </div>
            @endunless

            <button
                type="submit"
                class="boton w-full min-h-[48px] px-6 py-3 rounded-xl bg-accion hover:bg-accion-hover text-accion-texto font-semibold text-sm transition-all shadow-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-foco cursor-pointer"
            >
                Aceptar invitación y entrar al panel
            </button>
        </form>
    @endif
@endsection
