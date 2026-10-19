@if (collect($horarios)->contains('cerrado', false))
    <table class="horarios">
        <caption class="sr-only">Horario de atención</caption>
        @foreach ($horarios as $h)
            <tr @class(['cerrado' => $h['cerrado']])><th scope="row">{{ $h['dia'] }}</th><td>{{ $h['texto'] }}</td></tr>
        @endforeach
    </table>
@endif
