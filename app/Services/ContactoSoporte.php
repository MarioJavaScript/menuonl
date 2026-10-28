<?php

namespace App\Services;

/**
 * Contacto de soporte: botón de ayuda para el propietario de un restaurante en "Solo menú" y botones de WhatsApp de la portada.
 * El número sale de config('menuonl.soporte_whatsapp'); nunca va escrito en el código.
 */
class ContactoSoporte
{
    public const MENSAJE_WHATSAPP = 'Hola, quiero cambiar algo de mi menú';

    /**
     * Solo dígitos (con lada), o null si no hay número configurado o no tiene entre 10 y 15 dígitos.
     */
    public function numero(): ?string
    {
        $digitos = preg_replace('/\D/', '', (string) config('menuonl.soporte_whatsapp'));

        return strlen($digitos) >= 10 && strlen($digitos) <= 15 ? $digitos : null;
    }

    /**
     * Enlace wa.me con el mensaje dado (o el de ayuda por defecto), o null si no hay un número válido (no se muestra botón).
     */
    public function enlaceWhatsapp(?string $mensaje = null): ?string
    {
        $numero = $this->numero();

        if ($numero === null) {
            return null;
        }

        return 'https://wa.me/'.$numero.'?text='.rawurlencode($mensaje ?? self::MENSAJE_WHATSAPP);
    }
}
