<?php

namespace App\Services;

use RuntimeException;

/**
 * Someone else changed the reservation after it was read: the expected state, time or date no longer matches.
 */
class ConflictoDeReserva extends RuntimeException
{
    public function __construct(string $message = 'Otra persona ya cambió esta reserva. Recarga para ver su estado actual.')
    {
        parent::__construct($message);
    }
}
