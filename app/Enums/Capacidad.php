<?php

namespace App\Enums;

/**
 * Lista cerrada de lo que un plan puede incluir (docs/planes.md, sección 2.2).
 * La única tabla plan → capacidades vive en PlanRestaurante::incluye().
 */
enum Capacidad: string
{
    case Sitio = 'sitio';
    case Reservas = 'reservas';
    case Panel = 'panel';
    case EditarDiseno = 'editar_diseno';
    case EditarMenu = 'editar_menu';
    case EditarDatos = 'editar_datos';
    case EditarTelefonos = 'editar_telefonos';
    case EditarHorario = 'editar_horario';
    case Qr = 'qr';
    case Equipo = 'equipo';
    case Servicio = 'servicio';
    case Metricas = 'metricas';
}
