@props(['restaurante', 'prefijo', 'claseCampo', 'claseEtiqueta'])
@php
    /*
     * Elección de entrega del pedido por WhatsApp (Domicilio o Para recoger), compartida por menu.publico y las plantillas
     * E, F, G y H. Solo ofrece las modalidades que el restaurante tiene encendidas; si es una sola, ya va marcada. No hay
     * pedidos de mesa: ?mesa= del QR no llega aquí. pedido/controlador.js lee la opción marcada (data-{prefijo}-modalidad).
     */
    $modalidades = $restaurante->modalidadesDePedido();
@endphp
<fieldset class="{{ $claseCampo }} {{ $prefijo }}-entrega" data-{{ $prefijo }}-entrega>
    <legend class="{{ $claseEtiqueta }}">Entrega</legend>
    <div class="{{ $prefijo }}-entrega__opciones">
        @foreach ($modalidades as $modalidad)
            <label class="{{ $prefijo }}-entrega__opcion">
                <input type="radio" name="{{ $prefijo }}-pedido-entrega" value="{{ $modalidad }}" data-{{ $prefijo }}-modalidad @checked(count($modalidades) === 1)>
                <span>{{ \App\Models\Restaurante::MODALIDADES_DE_PEDIDO[$modalidad] }}</span>
            </label>
        @endforeach
    </div>
</fieldset>
