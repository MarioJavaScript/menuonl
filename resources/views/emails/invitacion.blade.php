<x-mail::message>
# Te invitaron a {{ $nombreRestaurante }}

Recibiste una invitación para administrar el menú digital de **{{ $nombreRestaurante }}** con el rol de **{{ $rol }}**.

<x-mail::button :url="$url">
Aceptar invitación
</x-mail::button>

La invitación es de un solo uso y caduca el {{ $expiraEn->format('d/m/Y H:i') }}.
Si no esperabas este correo, puedes ignorarlo.

Gracias,<br>
{{ config('app.name') }}
</x-mail::message>
