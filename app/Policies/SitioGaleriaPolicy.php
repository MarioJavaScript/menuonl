<?php

namespace App\Policies;

/**
 * Galería del sitio: mismos permisos que la configuración (propietario y superadmin, nunca el editor).
 */
class SitioGaleriaPolicy extends SitioConfiguracionPolicy {}
