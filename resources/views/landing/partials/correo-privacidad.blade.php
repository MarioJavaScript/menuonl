{{-- Correo de privacidad, con un punto de corte para que uno largo no desborde en un celular. Sin correo válido queda un contacto genérico: nunca un marcador. --}}
@if ($correo = app(\App\Services\DatosLegales::class)->correoPrivacidad())
<a href="mailto:{{ $correo }}">{{ \Illuminate\Support\Str::before($correo, '@') }}@<wbr>{{ \Illuminate\Support\Str::after($correo, '@') }}</a>@else
el contacto de soporte de {{ config('app.name') }}@endif
