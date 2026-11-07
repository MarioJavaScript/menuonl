@extends('landing.layout')

{{--
    Borrador para revisión legal. Nombres, precios y nota salen de config('menuonl.precios'); datos del responsable, de config('menuonl.legal').
    Los puntos que debe completar el responsable van como comentario de Blade (no llegan al visitante) y también están en
    docs/legal/datos-personales.md, sección 5. Busca "PENDIENTE DEL RESPONSABLE" en este archivo.
--}}
@php
    $legal = app(\App\Services\DatosLegales::class);
    $marca = config('app.name');
    $responsable = $legal->responsable() ?? $marca;
    $lite = config('menuonl.precios.nombres.lite');
    $aura = config('menuonl.precios.nombres.aura');
    $diasRespaldos = (int) config('menuonl.respaldos.retencion_dias');
    $descripcion = 'Condiciones de uso de '.$marca.': menú digital, sitio, reservas y pedidos por WhatsApp, planes, cuentas, responsabilidades y baja.';
@endphp

@section('titulo', 'Términos y condiciones | '.config('app.name'))

@section('sin_revelado', '1')

@section('meta')
<meta name="description" content="{{ $descripcion }}">
<link rel="canonical" href="{{ route('terminos') }}">
@endsection

@section('contenido')
<main id="contenido" class="legal">
  <div class="wrap">
    <h1>Términos y condiciones</h1>
    @if ($fecha = $legal->fechaActualizacion())
      <p>Última actualización: {{ $fecha }}.</p>
    @endif
    <p class="lead">Estas son las reglas para usar {{ $marca }}, que opera {{ $responsable }}. Al usar el servicio con tu restaurante, las aceptas. Si no estás de acuerdo, no lo uses.</p>

    <section class="section__head" aria-labelledby="servicio">
      <h2 id="servicio">El servicio</h2>
      <p>{{ $marca }} es una plataforma para que un restaurante publique su información y atienda a sus clientes. Según el plan, incluye:</p>
      <ul class="plan__list">
        <li><p><strong>Menú digital:</strong> tus platillos, precios y fotos, que tus clientes abren con un código QR o NFC sin descargar nada.</p></li>
        <li><p><strong>Sitio con plantilla:</strong> una página de tu restaurante con el diseño que elijas.</p></li>
        <li><p><strong>Reservas:</strong> un formulario en tu sitio y una pantalla en tu panel para gestionarlas.</p></li>
        <li><p><strong>Pedidos por WhatsApp:</strong> tus clientes arman el pedido en el menú y lo envían desde su teléfono a tu número, para domicilio o para recoger.</p></li>
      </ul>
      <p>{{ $marca }} no cobra pedidos en línea, no hace entregas, no es parte de la venta entre tú y tu cliente, y no guarda los pedidos. Lo que se acuerda por WhatsApp es entre ustedes.</p>
    </section>

    <section class="section__head" aria-labelledby="planes">
      <h2 id="planes">Planes</h2>
      <ul class="plan__list">
        <li><p><strong>{{ $lite }}, ${{ number_format((int) config('menuonl.precios.lite')) }} al mes:</strong> panel, menú digital, códigos QR, métricas de escaneos y pedidos por WhatsApp. No incluye sitio, reservas ni galería de fotos.</p></li>
        <li><p><strong>{{ $aura }}, ${{ number_format((int) config('menuonl.precios.aura')) }} al mes:</strong> todo lo de {{ $lite }}, más sitio con plantilla, galería de fotos y reservas.</p></li>
      </ul>
      @if (filled(config('menuonl.precios.nota')))
        <p>{{ config('menuonl.precios.nota') }}</p>
      @endif
      <p>Si cambias de plan a uno que incluye menos, las funciones que no entren en él dejarán de estar disponibles. Los precios vigentes son los publicados en esta página y en la <a href="{{ route('portada') }}#planes">portada</a>.</p>
    </section>

    <section class="section__head" aria-labelledby="cuentas">
      <h2 id="cuentas">Cuentas y verificación en dos pasos</h2>
      <p>Las cuentas se crean por invitación, con un enlace personal que vence. Quien la acepta debe dar datos verdaderos y es responsable de lo que se haga con ella.</p>
      <p><strong>La verificación en dos pasos es obligatoria para las cuentas de propietario.</strong> Sin ella no se puede usar el panel. Otros miembros del equipo pueden activarla, y la recomendamos. Guarda tus códigos de recuperación en un lugar seguro.</p>
      <p>No compartas tu contraseña ni tus códigos. El propietario decide quién entra a su equipo y debe retirar el acceso a quien ya no deba tenerlo. Si crees que alguien entró a tu cuenta, avísanos de inmediato.</p>
    </section>

    <section class="section__head" aria-labelledby="restaurante">
      <h2 id="restaurante">Tus responsabilidades como restaurante</h2>
      <p>Tú decides lo que publicas y respondes por ello. En particular:</p>
      <ul class="plan__list">
        <li><p><strong>Contenido:</strong> que tu información sea verdadera, esté al día y cumpla la ley, incluida la publicidad.</p></li>
        <li><p><strong>Precios:</strong> mantenerlos correctos y respetarlos ante tus clientes, así como informar impuestos y cargos, como el envío a domicilio.</p></li>
        <li><p><strong>Alergenos:</strong> informar con exactitud ingredientes y alergenos. Las etiquetas del menú, como "sin gluten" o "sin lactosa", las eliges tú; {{ $marca }} no las revisa ni las garantiza.</p></li>
        <li><p><strong>Imágenes:</strong> tener los derechos de las fotos y los logotipos que subes, y la autorización de quien aparezca en ellas.</p></li>
        <li><p><strong>Tus clientes:</strong> atender las reservas y los pedidos que recibes, y cumplir tus obligaciones de privacidad, como explica el <a href="{{ route('privacidad') }}#restaurantes">aviso de privacidad</a>.</p></li>
        <li><p><strong>Tus permisos:</strong> contar con las licencias y los registros que tu negocio necesite.</p></li>
      </ul>
    </section>

    <section class="section__head" aria-labelledby="uso">
      <h2 id="uso">Uso aceptable</h2>
      <p>No puedes usar {{ $marca }} para:</p>
      <ul class="plan__list">
        <li><p>Publicar contenido falso, ilegal, ofensivo o que infrinja derechos de otros.</p></li>
        <li><p>Ofrecer productos o servicios prohibidos por la ley.</p></li>
        <li><p>Mandar mensajes no solicitados a quienes reservan con tu restaurante.</p></li>
        <li><p>Intentar entrar a cuentas o datos de otros, saltarte los límites del sistema, subir archivos dañinos o saturar el servicio.</p></li>
        <li><p>Copiar o revender el servicio sin autorización por escrito.</p></li>
      </ul>
    </section>

    {{--
        PENDIENTE DEL RESPONSABLE: causas de suspensión (por ejemplo, impago) con su aviso previo, y qué pasa con los datos tras la baja: plazo para
        exportarlos y plazo de borrado. El código solo permite suspender, que conserva todo; no existe función de baja ni de exportación.
    --}}
    <section class="section__head" aria-labelledby="baja">
      <h2 id="baja">Suspensión y baja</h2>
      <p>Podemos suspender un restaurante si incumple estos términos, pone en riesgo el servicio o a otras personas, o lo exige una autoridad. Mientras esté suspendido, su menú, su sitio, sus códigos QR y su formulario de reservas dejan de responder, y su equipo no puede usar el panel. Sus datos se conservan y el servicio se puede reactivar al resolver la causa.</p>
      <p>Puedes pedir la baja cuando quieras, sin plazo forzoso, por medio del contacto de soporte. No hay un botón de baja en el panel: nuestro equipo la atiende. Tus datos personales se tratan como indica el <a href="{{ route('privacidad') }}">aviso de privacidad</a>.</p>
    </section>

    {{--
        PENDIENTE DEL RESPONSABLE: forma de pago, fecha de cobro, si el precio incluye IVA (se publica en menuonl.precios.nota), facturación (CFDI),
        reembolsos, qué pasa con el impago y cómo se avisa de un cambio de precio. Nada de esto existe en el código.
    --}}
    <section class="section__head" aria-labelledby="pagos">
      <h2 id="pagos">Pagos y facturación</h2>
      <p>La plataforma no procesa pagos en línea ni guarda datos de tarjetas, y tampoco emite facturas. La forma de pago, las fechas de cobro y la factura se acuerdan directamente con el equipo de {{ $marca }}.</p>
    </section>

    <section class="section__head" aria-labelledby="disponibilidad">
      <h2 id="disponibilidad">Disponibilidad y respaldos</h2>
      <p>Trabajamos para que el servicio esté disponible y hacemos una copia de respaldo cifrada cada día, que conservamos {{ $diasRespaldos }} {{ $diasRespaldos === 1 ? 'día' : 'días' }}. Aun así, no garantizamos que el servicio funcione siempre, sin errores ni interrupciones, ni que toda la información se pueda recuperar: entre un respaldo y otro se pueden perder cambios.</p>
      <p>También dependemos de terceros, como proveedores de servidores, redes, WhatsApp y el internet de cada persona. Podemos hacer mantenimientos y cambiar funciones para mejorar el servicio.</p>
    </section>

    <section class="section__head" aria-labelledby="limitacion">
      <h2 id="limitacion">Limitación de responsabilidad</h2>
      <p>En la medida que lo permita la ley, {{ $marca }} se ofrece tal como está, y {{ $responsable }} no responde por:</p>
      <ul class="plan__list">
        <li><p>El contenido que publica cada restaurante, incluidos precios, alergenos e imágenes.</p></li>
        <li><p>Los pedidos, reservas, entregas, pagos o desacuerdos entre un restaurante y sus clientes.</p></li>
        <li><p>Ventas o ganancias que se dejen de obtener, ni daños indirectos, por una interrupción del servicio.</p></li>
        <li><p>Fallas de terceros o causas fuera de nuestro control razonable.</p></li>
      </ul>
      <p>Nada de esto limita los derechos que la ley te reconozca y que no se puedan renunciar.</p>
    </section>

    <section class="section__head" aria-labelledby="propiedad">
      <h2 id="propiedad">Propiedad intelectual</h2>
      <p>La marca {{ $marca }}, el software, las plantillas y los diseños son de {{ $responsable }} o de quienes se los licencian. Puedes usarlos solo para operar tu restaurante mientras tengas el servicio.</p>
      <p>Tu contenido sigue siendo tuyo. Nos das permiso de guardarlo, adaptarlo (por ejemplo, reducir una foto) y mostrarlo, solo para dar el servicio y mientras lo uses, además del tiempo que duren los respaldos.</p>
    </section>

    <section class="section__head" aria-labelledby="modificaciones">
      <h2 id="modificaciones">Cambios a estos términos</h2>
      <p>Podemos cambiar estos términos. Publicaremos la versión nueva en esta página con su fecha. Si sigues usando el servicio después del cambio, se entiende que lo aceptas; si no estás de acuerdo, puedes pedir tu baja.</p>
    </section>

    {{-- PENDIENTE DEL RESPONSABLE: confirmar el estado en LEGAL_JURISDICCION. Sin ese valor el texto no nombra un estado y menuonl:verificar-produccion falla. --}}
    <section class="section__head" aria-labelledby="ley">
      <h2 id="ley">Ley aplicable y jurisdicción</h2>
      <p>Estos términos se rigen por las leyes de México. Para cualquier controversia, las partes se someten a los tribunales competentes de {{ $legal->jurisdiccion() ?? 'México' }}, sin perjuicio de los derechos que la ley reconozca a los consumidores.</p>
      <p>Para dudas sobre estos términos o sobre tus datos, escribe a @include('landing.partials.correo-privacidad').</p>
    </section>

    <p>Consulta también el <a href="{{ route('privacidad') }}">Aviso de privacidad</a>.</p>
    <p><a class="btn btn--secondary" href="{{ route('portada') }}">Volver al inicio</a></p>
  </div>
</main>
@endsection
