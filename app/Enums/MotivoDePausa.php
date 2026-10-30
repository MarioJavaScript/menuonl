<?php

namespace App\Enums;

/**
 * Por qué un restaurante está pausado para su equipo. Hoy solo existe "fuera de servicio" (estado "suspendido").
 * Un motivo nuevo se agrega aquí y en Restaurante::motivoDePausa(); el acceso al panel y el aviso no cambian.
 */
enum MotivoDePausa: string
{
    case FueraDeServicio = 'fuera_de_servicio';
}
