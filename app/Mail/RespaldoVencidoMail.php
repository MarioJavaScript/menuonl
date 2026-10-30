<?php

namespace App\Mail;

use App\Services\TextoMarkdown;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Aviso de que el respaldo diario falta o está vencido. Solo lleva el motivo general y las horas: nunca rutas, nombres de
 * archivo, ni el mensaje de una excepción. No va en cola: la cola puede ser justo lo que está caído.
 */
class RespaldoVencidoMail extends Mailable
{
    /**
     * @param  string  $problema  'sin_respaldos', 'vencido' o 'disco_ilegible' (RespaldoService::diagnosticarAntiguedad())
     */
    public function __construct(
        public string $problema,
        public ?int $horas,
        public int $maximo,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('panel.respaldo_alerta.asunto', ['app' => (string) config('app.name')]));
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.respaldo-vencido',
            with: [
                'nombreApp' => TextoMarkdown::escapar((string) config('app.name')),
                'detalle' => __('panel.respaldo_alerta.problemas.'.$this->problema, ['horas' => $this->horas, 'maximo' => $this->maximo]),
            ],
        );
    }
}
