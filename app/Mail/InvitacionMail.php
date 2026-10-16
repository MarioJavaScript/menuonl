<?php

namespace App\Mail;

use App\Models\Invitacion;
use App\Models\Restaurante;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class InvitacionMail extends Mailable
{
    /**
     * Not queued: the plain token must never be serialized into the jobs table.
     */
    public function __construct(
        public Invitacion $invitacion,
        public Restaurante $restaurante,
        #[\SensitiveParameter] public string $token,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Te invitaron a administrar '.$this->restaurante->nombre);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.invitacion',
            with: [
                'url' => route('invitaciones.mostrar', ['token' => $this->token]),
                'restaurante' => $this->restaurante->nombre,
                'rol' => $this->invitacion->rol === 'propietario' ? 'Propietario' : 'Editor',
                'expiraEn' => $this->invitacion->expira_en,
            ],
        );
    }
}
