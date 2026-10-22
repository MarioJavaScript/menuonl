<?php

/*
 * Textos del panel del restaurante escritos para el dueño: claros, sin jerga y con "menú" (nunca "carta").
 * Cada ajuste explica qué cambia en el sitio o en el menú de sus clientes.
 */
return [

    'comun' => [
        'guardar' => 'Guardar cambios',
        'guardado' => 'Cambios guardados',
    ],

    'grupos' => [
        'menu' => 'Mi menú',
        'apariencia' => 'Apariencia',
        'informacion' => 'Información',
        'pedidos' => 'Pedidos y reservas',
        'qr' => 'QR y mesas',
    ],

    'enlaces' => [
        'ver_sitio' => 'Ver mi sitio',
        'ver_menu' => 'Ver mi menú',
        'abre_en_otra_pestana' => '(se abre en otra pestaña)',
        'servicio' => 'Pedidos y reservas de hoy',
    ],

    'apariencia' => [
        'nav' => 'Apariencia',
        'titulo' => 'Apariencia de tu sitio y tu menú',
        'subtitulo' => 'Elige primero el diseño. Después verás solo las opciones que aplican a ese diseño.',
        'diseno' => [
            'titulo' => 'Diseño',
            'descripcion' => 'Cambia cómo se ve tu sitio y tu menú. Tus platillos, fotos y precios no se pierden al cambiar de diseño.',
            'campo' => 'Diseño de tu sitio',
            'elegido' => 'Elegido',
            'ideal_para' => 'Ideal para: :para',
        ],
        'estilo' => [
            'titulo' => 'Estilo del menú',
            'descripcion' => 'Colores y letras de tu menú. Solo aplica a este diseño; los diseños Cafetería, Bar y Cafetería pastel traen sus propios colores.',
            'campo' => 'Estilo del menú',
        ],
        'color' => [
            'titulo' => 'Color principal',
            'descripcion' => 'Se usa en botones y detalles. Si no eliges uno, se usa el color original del diseño.',
            'campo' => 'Color principal',
            'invalido' => 'Elige un color con el selector.',
            'quitar' => 'Usar el color original',
            'sin_color' => 'Sin color elegido: tu sitio y tu menú usan los colores originales del diseño.',
            'solo_menu' => 'En este diseño tu color se usa en los botones y detalles de tu menú. La página de inicio de tu sitio conserva los colores del diseño.',
            'aplica' => 'Tu color se usará en los botones y detalles de tu sitio y de tu menú.',
            'no_legible' => 'Este color no se leería bien con el texto de este diseño, así que tu sitio seguirá con el color original. Prueba con otro tono, ni muy claro ni muy oscuro.',
        ],
        'imagenes' => [
            'titulo' => 'Logotipo y portada',
            'descripcion' => 'Las imágenes con las que tus clientes reconocen tu restaurante.',
            'logo' => 'Logotipo',
            'logo_ayuda' => 'Opcional: si no subes uno, tu sitio muestra el nombre de tu restaurante con letra grande.',
            'portada' => 'Fotografía de portada',
            'portada_ayuda' => 'Es la foto grande que ven primero en tu sitio. Si no subes una, se usa un fondo de color.',
        ],
    ],

    'datos' => [
        'nav' => 'Datos del restaurante',
        'basicos' => [
            'titulo' => 'Tu restaurante',
            'descripcion' => 'Lo que tus clientes leen primero en tu sitio y en tu menú.',
            'nombre' => 'Nombre del restaurante',
            'nombre_ayuda' => 'Aparece en el encabezado de tu sitio, en tu menú y en los pedidos que recibes.',
            'frase' => 'Frase corta o especialidad',
            'frase_ejemplo' => 'Cocina italiana de leña',
            'frase_ayuda' => 'Aparece junto al nombre en el encabezado. Si la dejas vacía, no se muestra.',
            'descripcion' => 'Descripción breve',
            'descripcion_ejemplo' => 'Cuéntales a tus clientes qué hace especial a tu cocina o comparte indicaciones útiles.',
            'descripcion_ayuda' => 'Se muestra en la sección "Nosotros" de tu sitio. Si la dejas vacía, esa sección no aparece.',
        ],
        'mapa' => [
            'titulo' => 'Ubicación en el mapa',
            'descripcion' => 'Con tu ubicación, tu sitio ofrece un botón para abrir tu local en Google Maps.',
            'enlace' => 'Enlace de Google Maps',
            'enlace_ayuda' => 'Abre Google Maps, busca tu local, toca Compartir, copia el enlace y pégalo aquí. Es opcional: si lo dejas vacío, el botón usa la dirección que tengas en Contactos.',
            'guardado' => 'Ya tienes una ubicación guardada. Pega un enlace nuevo solo si quieres cambiarla.',
            'no_es_google_maps' => 'Pega un enlace de Google Maps que empiece con https://, por ejemplo https://maps.app.goo.gl/...',
            'sin_ubicacion' => 'No pudimos encontrar la ubicación en ese enlace. Abre tu local en Google Maps, toca Compartir y pega el enlace que te da.',
            'demasiados_intentos' => 'Probaste muchos enlaces seguidos. Espera un minuto y vuelve a intentarlo.',
            'quitar' => 'Quitar la ubicación guardada',
            'quitar_ayuda' => 'El botón del mapa volverá a usar tu dirección.',
        ],
        'moneda' => [
            'titulo' => 'Moneda e idioma',
            'descripcion' => 'Cómo se muestran tus precios y en qué idioma escribes tu menú.',
            'moneda' => 'Moneda',
            'moneda_ayuda' => 'Se usa en todos los precios de tu menú y de tu sitio.',
            'idioma' => 'Idioma principal de tu menú',
            'idioma_ayuda' => 'El idioma en que escribes tus platillos. Es el que ven tus clientes por defecto.',
            'zona' => 'Zona horaria',
            'zona_ayuda' => 'Define cuándo tu sitio dice "Abierto ahora", según tu horario.',
        ],
        'idiomas' => [
            'titulo' => 'Idiomas adicionales',
            'descripcion' => 'Agrega otros idiomas si recibes clientes extranjeros y quieres traducir tus categorías y platillos.',
            'activos' => 'Idiomas activos',
            'idioma' => 'Idioma',
            'orden' => 'Orden',
            'agregar' => 'Agregar idioma',
        ],
    ],

    'pedidos' => [
        'nav' => 'Pedidos y reservas',
        'titulo' => 'Pedidos y reservas',
        'subtitulo' => 'Decide cómo te hacen pedidos y reservas tus clientes desde tu sitio y tu menú.',
        'whatsapp' => [
            'titulo' => 'Pedidos por WhatsApp',
            'descripcion' => 'Tus clientes arman su pedido en el menú y te llega listo a tu WhatsApp.',
            'activar' => 'Recibir pedidos por WhatsApp',
            'activar_ayuda' => 'Si lo enciendes, el menú deja elegir platillos y enviar el pedido. Si lo apagas, el menú solo se puede leer.',
            'numero' => 'Número de WhatsApp que recibe los pedidos',
            'numero_ayuda' => 'Con código de país y solo números, sin espacios ni signos. En México: 52 y tu número, por ejemplo 5215512345678.',
            'numero_invalido' => 'Escribe de 8 a 20 números, sin espacios ni signos.',
            'numero_requerido' => 'Escribe el número de WhatsApp para poder recibir pedidos.',
        ],
        'reservas' => [
            'titulo' => 'Reservas',
            'descripcion' => 'Tus clientes reservan mesa desde tu sitio.',
            'activar' => 'Aceptar reservas desde mi sitio',
            'activar_ayuda' => 'Si lo apagas, el formulario de reservas desaparece de tu sitio.',
            'sin_horario' => 'Aún no tienes horario. Sin horario, tus clientes no saben cuándo pueden reservar. Agrégalo en Información, Horario.',
            'cupo' => 'Personas que puedes recibir al mismo tiempo',
            'cupo_ayuda' => 'Cuando se llena este número en una franja, el sitio ya no acepta más reservas en ella.',
            'duracion' => 'Duración de cada reserva',
            'duracion_ayuda' => 'Cuánto tiempo ocupa una mesa cada reserva.',
            'minutos' => ':n minutos',
            'una_hora' => '1 hora',
            'hora_y_media' => '1 hora y media',
            'dos_horas' => '2 horas',
        ],
    ],

    'menu' => [
        'agotados' => [
            'accion' => 'Platillos agotados',
            'titulo' => 'Platillos agotados en tu menú',
            'campo' => 'Mostrar los platillos agotados',
            'ayuda' => 'Encendido: el platillo agotado se ve en el menú con la marca "Agotado". Apagado: desaparece del menú y del sitio mientras no haya.',
            'boton' => 'Guardar',
            'guardado' => 'Listo, ya cambió cómo se muestran los platillos agotados.',
        ],
        'orden' => [
            'ordenar' => 'Ordenar arrastrando',
            'terminar' => 'Terminar de ordenar',
            'ayuda_platillos' => 'Para cambiar el orden, toca "Ordenar arrastrando" y mueve los platillos. Así aparecen en tu menú.',
            'ayuda_categorias' => 'Para cambiar el orden, toca "Ordenar arrastrando" y mueve las categorías. Así aparecen en tu menú.',
        ],
        'categoria' => [
            'visible_ayuda' => 'Apágala para ocultar esta categoría y sus platillos de tu menú sin borrarlos.',
        ],
        'platillo' => [
            'opciones_descripcion' => 'Promociones, etiquetas dietéticas y visibilidad en el menú.',
        ],
    ],

    'recomendados' => [
        'nav' => 'Recomendados',
        'titulo' => 'Platillos recomendados',
        'subtitulo' => 'Los recomendados salen primero en tu sitio y en una sección propia de tu menú. Marca los que quieras lucir.',
        'contador' => '{0} Aún no tienes recomendados|{1} Tienes 1 platillo recomendado|[2,*] Tienes :n platillos recomendados',
        'explicacion' => 'Tu sitio muestra hasta :max recomendados, en el orden de tu menú. Tu menú muestra todos.',
        'sin_marcados' => 'Mientras no marques ninguno, tu sitio muestra los primeros :max platillos de tu menú.',
        'de_mas' => 'Marcaste :n más de los que cabe en tu sitio (:max). Los últimos no se verán en el sitio, pero sí en el menú.',
        'principal' => 'Platillo principal',
        'sin_principal' => 'Todavía no hay uno disponible',
        'principal_regla' => 'Sale primero el que tiene la etiqueta "Nuevo"; si no hay, el primero con foto.',
        'sin_principal_en_plantilla' => 'El diseño que elegiste los muestra en una lista, en este orden, sin uno principal.',
        'se_muestran' => 'Esto es lo que se ve hoy en tu sitio:',
        'foto' => 'Foto',
        'platillo' => 'Platillo',
        'estado' => 'Estado',
        'oculto' => 'Oculto en el menú',
        'agotado' => 'Agotado',
        'recomendado' => 'Recomendado',
        'solo_recomendados' => 'Solo recomendados',
        'categoria' => 'Categoría',
        'todas' => 'Todas',
        'sin_nombre' => 'Platillo sin nombre',
        'ahora_recomendado' => 'ahora es recomendado',
        'ya_no_recomendado' => 'ya no es recomendado',
        'vacio_titulo' => 'Todavía no tienes platillos',
        'vacio_descripcion' => 'Agrega tus platillos en Mi menú y vuelve aquí para marcar los recomendados.',
    ],

    'qr' => [
        'mesas' => [
            'accion' => 'Crear QR de mesas',
            'titulo' => 'Crear QR para tus mesas',
            'descripcion' => 'Indica cuántas mesas tienes y creamos un QR por mesa (Mesa 1, Mesa 2...). Las mesas que ya tienen QR no se tocan.',
            'cantidad' => '¿Cuántas mesas tienes?',
            'cantidad_ayuda' => 'Un número del 1 al 100.',
            'boton' => 'Crear QR',
            'resultado' => '{0} No se creó ningún QR: todas esas mesas ya tenían uno.|{1} Creamos 1 QR de mesa.|[2,*] Creamos :n QR de mesas.',
            'omitidas' => ':n mesas ya tenían QR y no se tocaron.',
        ],
    ],

    'inicio' => [
        'whatsapp_faltante' => 'Activaste los pedidos, pero falta tu WhatsApp. Sin él no te llegan.',
    ],

];
