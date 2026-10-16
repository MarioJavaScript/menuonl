@extends('invitaciones.layout')

@section('titulo', 'Aceptar invitación')

@section('contenido')
    <h1>Te invitaron a {{ $invitacion->restaurante->nombre }}</h1>
    <p>Rol: <strong>{{ $rol }}</strong> · Correo: <strong>{{ $invitacion->email }}</strong></p>

    @if ($cuentaExistente && ! $sesionCorrecta)
        <p>Ya existe una cuenta con este correo. Inicia sesión con ella para aceptar la invitación.</p>
        <a class="boton" href="{{ route('filament.admin.auth.login') }}">Iniciar sesión</a>
    @else
        <form method="POST" action="{{ route('invitaciones.aceptar', ['token' => $token]) }}">
            @csrf
            @unless ($cuentaExistente)
                <label for="name">Nombre</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}" required maxlength="255" autocomplete="name">
                @error('name') <p class="error">{{ $message }}</p> @enderror

                <label for="password">Contraseña (mínimo 10 caracteres, con letras y números)</label>
                <input id="password" name="password" type="password" required autocomplete="new-password">
                @error('password') <p class="error">{{ $message }}</p> @enderror

                <label for="password_confirmation">Repite la contraseña</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
            @endunless

            <button type="submit">Aceptar invitación</button>
        </form>
    @endif
@endsection
