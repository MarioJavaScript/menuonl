@extends('landing.layout')

{{--
    Borrador para revisión legal. Lo que dice sobre los datos sale de docs/legal/datos-personales.md: si el sistema cambia lo que guarda, hay que
    cambiar los dos. Los datos del responsable salen de config('menuonl.legal') (App\Services\DatosLegales) y los plazos, de config.
    Sin estilos propios: reutiliza las clases de landing.css (section__head agrupa cada apartado y plan__list da aire a las listas).
--}}
@php
    $legal = app(\App\Services\DatosLegales::class);
    $marca = config('app.name');
    $responsable = $legal->responsable() ?? $marca;
    $mesesEventos = (int) config('menuonl.reservas.eventos_retencion_meses');
    $diasRespaldos = (int) config('menuonl.respaldos.retencion_dias');
    $descripcion = 'Qué datos personales recaba '.$marca.', para qué los usa y cómo ejercer tus derechos ARCO, si tienes un restaurante o si reservas en uno.';
@endphp

@section('titulo', 'Aviso de privacidad | '.config('app.name'))

@section('sin_revelado', '1')

@section('meta')
<meta name="description" content="{{ $descripcion }}">
<link rel="canonical" href="{{ route('privacidad') }}">
@endsection

@section('contenido')
<main id="contenido" class="legal">
  <div class="wrap">
    <h1>Aviso de privacidad</h1>
    @if ($fecha = $legal->fechaActualizacion())
      <p>Última actualización: {{ $fecha }}.</p>
    @endif
    <p class="lead">Aquí explicamos qué datos personales se recaban en {{ $marca }}, para qué se usan y qué puedes hacer con ellos. Lo escribimos para dos personas: quien tiene un restaurante y quien reserva o pide en uno.</p>

    <section class="section__head" aria-labelledby="responsable">
      <h2 id="responsable">Quién responde por tus datos</h2>
      <p>La Ley Federal de Protección de Datos Personales en Posesión de los Particulares y su Reglamento distinguen al responsable, quien decide sobre los datos, del encargado, quien los trata por cuenta de otro. Aquí hay dos casos:</p>
      <ul class="plan__list">
        <li><p><strong>Si tienes un restaurante o eres parte de su equipo,</strong> el responsable de tus datos es {{ $responsable }}, que opera {{ $marca }}.</p></li>
        <li><p><strong>Si reservas en un restaurante,</strong> el responsable de tus datos es ese restaurante, y {{ $marca }} actúa como encargado: guarda la reserva por cuenta del restaurante y solo para dar el servicio.</p></li>
      </ul>
      @if ($domicilio = $legal->domicilio())
        <p>{{ $responsable }} tiene su domicilio en {{ $domicilio }}.</p>
      @endif
      <p>Para cualquier tema de este aviso, escribe a @include('landing.partials.correo-privacidad').</p>
    </section>

    <section class="section__head" aria-labelledby="duenos">
      <h2 id="duenos">Si tienes un restaurante o eres parte de su equipo</h2>
      <h3>Datos que recabamos</h3>
      <ul class="plan__list">
        <li><p><strong>De tu cuenta:</strong> nombre, correo electrónico, contraseña (guardamos una huella cifrada, no la contraseña), el secreto de la verificación en dos pasos y sus códigos de recuperación (cifrados), tu rol en el restaurante y tu último acceso.</p></li>
        <li><p><strong>De tu uso del panel:</strong> dirección IP y navegador mientras dura la sesión, los intentos de entrada y un historial de los cambios que hace cada usuario.</p></li>
        <li><p><strong>De tus invitaciones:</strong> el correo de la persona que invitas, su rol y quién la invitó.</p></li>
        <li><p><strong>De tu restaurante:</strong> los contactos que publiques (teléfono, WhatsApp, correo, dirección y redes) y las fotos que subas. Pueden incluir datos de una persona; son públicos porque se muestran en tu menú y en tu sitio. Quitamos los metadatos de las fotos al procesarlas.</p></li>
      </ul>
      <p>{{ $marca }} no recaba datos de tarjetas ni datos fiscales dentro de la plataforma.</p>
      <h3>Finalidades primarias</h3>
      <p>Son necesarias para darte el servicio:</p>
      <ul class="plan__list">
        <li><p>Crear y proteger tu cuenta, y darte acceso al panel según tu rol.</p></li>
        <li><p>Publicar tu menú y tu sitio, y recibir las reservas de tu restaurante según tu plan.</p></li>
        <li><p>Enviarte invitaciones, enlaces para recuperar tu contraseña y avisos necesarios del servicio.</p></li>
        <li><p>Atenderte en soporte, llevar el historial de cambios, prevenir abusos y hacer respaldos.</p></li>
      </ul>
      <h3>Finalidades secundarias</h3>
      <p>Hoy no usamos tus datos para finalidades secundarias: no enviamos publicidad ni boletines y no hacemos perfiles. Si esto cambia, actualizaremos este aviso y podrás oponerte antes de que suceda.</p>
    </section>

    <section class="section__head" aria-labelledby="clientes">
      <h2 id="clientes">Si reservas en un restaurante</h2>
      <p>Cuando llenas el formulario de reservas en el sitio de un restaurante, se guarda tu nombre, tu teléfono, el número de personas, la fecha y la hora, y la nota si escribes una. Tu dirección IP se usa unos minutos para frenar envíos abusivos, y no se guarda con la reserva.</p>
      <p>Te pedimos no escribir en la nota datos sobre tu salud u otros datos sensibles, salvo lo que el restaurante necesite saber para atenderte.</p>
      <p><strong>Finalidades primarias:</strong> registrar tu solicitud, confirmártela por teléfono o WhatsApp y que el restaurante organice su servicio. Las decide el restaurante. {{ $marca }} no usa estos datos para fines propios ni te envía publicidad.</p>
      <p><strong>Finalidades secundarias:</strong> {{ $marca }} no tiene ninguna sobre estos datos. Si el restaurante quiere usar tu teléfono para algo más, debe avisártelo.</p>
      <p>Para ejercer tus derechos sobre una reserva, dirígete primero al restaurante. Si nos escribes a nosotros, le pasaremos tu solicitud.</p>
    </section>

    <section class="section__head" aria-labelledby="whatsapp">
      <h2 id="whatsapp">Pedidos por WhatsApp</h2>
      <p>{{ $marca }} no recibe ni guarda tu pedido. Mientras lo armas, el carrito vive en tu propio teléfono. Al terminar, se abre WhatsApp con un mensaje ya escrito hacia el número del restaurante, y <strong>eres tú quien lo envía desde tu teléfono</strong>: puedes cambiarlo o no mandarlo.</p>
      <p>Ese mensaje lleva el nombre del restaurante, si es a domicilio o para recoger, los platillos con sus opciones e importes, las notas que escribas, el total y, si el restaurante lo configuró, un mensaje suyo. No te pedimos nombre, teléfono ni dirección. El restaurante verá tu número de WhatsApp y lo que le compartas en el chat.</p>
      <p>Después de reservar, el sitio puede ofrecerte un botón parecido, con tu nombre, personas, fecha y hora. También lo envías tú, si quieres.</p>
      <p>Lo que mandas por WhatsApp queda entre tú, el restaurante y esa aplicación, que tiene sus propios términos y su propio aviso de privacidad.</p>
    </section>

    <section class="section__head" aria-labelledby="visitantes">
      <h2 id="visitantes">Si solo visitas una página</h2>
      <p>Para mostrarte una página, nuestros servidores y nuestra red de distribución reciben tu dirección IP, tu navegador y la dirección que visitas. Los usamos para entregar la página, protegerla de abusos y atender errores. No pedimos registro para ver un menú o un sitio.</p>
      <p>Cuando escaneas un QR o tocas un NFC, solo sumamos uno a un contador por código y por día, sin datos sobre ti.</p>
    </section>

    <section class="section__head" aria-labelledby="terceros">
      <h2 id="terceros">Con quién se comparten</h2>
      <p>No vendemos datos personales. Para operar nos apoyamos en proveedores que los tratan por nuestra cuenta, solo para dar el servicio. Esto es lo que la ley llama remisión, y no requiere tu consentimiento:</p>
      <ul class="plan__list">
        <li><p><strong>Servidores (hosting):</strong> guardan la base de datos, las imágenes y los registros técnicos.</p></li>
        <li><p><strong>Cloudflare:</strong> red de distribución y seguridad por la que pasan las visitas.</p></li>
        <li><p><strong>Proveedor de correo:</strong> entrega invitaciones, enlaces para recuperar contraseña y avisos.</p></li>
        <li><p><strong>Almacén de respaldos:</strong> guarda una copia diaria cifrada, fuera de los servidores principales.</p></li>
      </ul>
      <p>Algunos de estos proveedores tienen servidores fuera de México. No transferimos tus datos a otros terceros, salvo que lo exija la ley o una autoridad competente. Cada restaurante ve las reservas que le hacen a él, y nadie más que su equipo y el equipo de {{ $marca }} que da soporte.</p>
    </section>

    <section class="section__head" aria-labelledby="plazos">
      <h2 id="plazos">Cuánto tiempo se guardan</h2>
      <ul class="plan__list">
        <li><p><strong>Cuenta del restaurante y del equipo:</strong> mientras la cuenta exista.</p></li>
        <li><p><strong>Reservas:</strong> no tienen un plazo de borrado automático. Se conservan mientras el restaurante use el servicio o hasta que se atienda una solicitud de cancelación.</p></li>
        <li><p><strong>Historial de quién confirmó, canceló o movió una reserva:</strong> {{ $mesesEventos }} {{ $mesesEventos === 1 ? 'mes' : 'meses' }}.</p></li>
        <li><p><strong>Respaldos:</strong> {{ $diasRespaldos }} {{ $diasRespaldos === 1 ? 'día' : 'días' }}. Un dato que se borra puede seguir en una copia cifrada hasta que esa copia venza.</p></li>
        <li><p><strong>Registros técnicos:</strong> por un tiempo limitado, para atender errores y seguridad.</p></li>
      </ul>
    </section>

    <section class="section__head" aria-labelledby="arco">
      <h2 id="arco">Tus derechos ARCO</h2>
      <p>Tienes derecho a <strong>acceder</strong> a tus datos, <strong>rectificarlos</strong> si están mal, <strong>cancelarlos</strong> cuando ya no sean necesarios y <strong>oponerte</strong> a su uso para un fin concreto.</p>
      <p>Para ejercerlos, escribe a @include('landing.partials.correo-privacidad') e incluye:</p>
      <ul class="plan__list">
        <li><p>Tu nombre y un medio para responderte.</p></li>
        <li><p>Un documento que acredite tu identidad o, en su caso, la de tu representante.</p></li>
        <li><p>Qué derecho quieres ejercer y sobre qué datos, con lo que ayude a ubicarlos. Si es una reserva, el restaurante y la fecha.</p></li>
      </ul>
      <p>Te responderemos por el mismo medio, dentro de los plazos que marca la ley. Si no quedas conforme con la respuesta, puedes acudir a la Secretaría Anticorrupción y Buen Gobierno, la autoridad en esta materia.</p>
    </section>

    <section class="section__head" aria-labelledby="revocar">
      <h2 id="revocar">Revocar tu consentimiento</h2>
      <p>Puedes retirar tu consentimiento siguiendo los mismos pasos de los derechos ARCO. Si revocas sobre un dato necesario para el servicio, como el correo de tu cuenta, tal vez ya no podamos dártelo. Una revocación tampoco alcanza lo que la ley nos obligue a conservar.</p>
    </section>

    <section class="section__head" aria-labelledby="limitar">
      <h2 id="limitar">Limitar el uso o la divulgación</h2>
      <p>Para pedir que limitemos el uso o la divulgación de tus datos, escribe a @include('landing.partials.correo-privacidad'). También puedes borrar del menú o del sitio los contactos y las fotos que hayas publicado desde tu panel, y dejarán de mostrarse.</p>
    </section>

    <section class="section__head" aria-labelledby="cookies">
      <h2 id="cookies">Cookies y tecnologías similares</h2>
      <p>No usamos cookies de publicidad ni herramientas de analítica o de rastreo. Las páginas públicas (esta, la portada, los menús y los sitios) no colocan cookies propias. Esto es lo que sí existe:</p>
      <ul class="plan__list">
        <li><p><strong>Cookies del panel y de las invitaciones:</strong> una de sesión y una de seguridad para los formularios. Si marcas "recordarme" al entrar, también una para recordar tu sesión. Sin ellas no se puede entrar al panel.</p></li>
        <li><p><strong>Almacenamiento local de tu navegador:</strong> el carrito del pedido y la preferencia de modo claro u oscuro. Se queda en tu dispositivo y lo puedes borrar desde tu navegador.</p></li>
        <li><p><strong>Cloudflare:</strong> puede colocar una cookie técnica de seguridad para distinguir personas de programas automáticos.</p></li>
      </ul>
    </section>

    <section class="section__head" aria-labelledby="restaurantes">
      <h2 id="restaurantes">Qué deben avisar los restaurantes a sus clientes</h2>
      <p>Si recibes reservas con {{ $marca }}, eres el responsable de los datos de tus clientes. Esto implica que debes:</p>
      <ul class="plan__list">
        <li><p>Tener tu propio aviso de privacidad, con tu identidad y tu domicilio, y ponerlo a disposición de quien reserve. Este aviso no sustituye al tuyo: el formulario enlaza aquí como información general.</p></li>
        <li><p>Decirle a tus clientes para qué usas su nombre, su teléfono y su nota, y que {{ $marca }} los guarda como tu proveedor.</p></li>
        <li><p>Avisarles que los pedidos por WhatsApp se envían por esa aplicación y llegan a tu número.</p></li>
        <li><p>Atender sus solicitudes de acceso, rectificación, cancelación y oposición. Te apoyaremos con lo que esté guardado en la plataforma.</p></li>
        <li><p>Usar esos datos solo para lo que avisaste, cuidar quién de tu equipo tiene acceso y pedir permiso antes de mandar publicidad.</p></li>
        <li><p>Tener autorización para publicar los datos o las fotos de otras personas.</p></li>
      </ul>
    </section>

    <section class="section__head" aria-labelledby="cambios">
      <h2 id="cambios">Cambios a este aviso</h2>
      <p>Si cambia lo que hacemos con tus datos, actualizaremos este aviso y su fecha en esta misma página.</p>
    </section>

    <p>Consulta también los <a href="{{ route('terminos') }}">Términos y condiciones</a>.</p>
    <p><a class="btn btn--secondary" href="{{ route('portada') }}">Volver al inicio</a></p>
  </div>
</main>
@endsection
