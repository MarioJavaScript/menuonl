<?php

namespace App\Services;

/**
 * Contacto de soporte que se ofrece al propietario de un restaurante en "Solo menú".
 * El número sale de config('menuonl.soporte_whatsapp'); nunca va escrito en el código.
 */
class ContactoSoporte
{
    public const MENSAJE_WHATSAPP = 'Hola, quiero cambiar algo de mi menú';

    /**
     * Enlace wa.me con el texto de ayuda, o null si no hay número configurado (no se muestra botón).
     */
    public function enlaceWhatsapp(): ?string
    {
        $digitos = preg_replace('/\D/', '', (string) config('menuonl.soporte_whatsapp'));

        if ($digitos === '') {
            return null;
        }

        return 'https://wa.me/'.$digitos.'?text='.rawurlencode(self::MENSAJE_WHATSAPP);
    }
}
