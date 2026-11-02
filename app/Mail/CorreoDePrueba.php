<?php

namespace App\Mail;

use App\Filament\MarcaDelOrganizador;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** El correo que manda Ajustes para comprobar que el servidor del organizador funciona. */
class CorreoDePrueba extends Mailable
{
    use Queueable;

    public function __construct(public string $servidor, public string $remitente, public string $nombreDelRemitente) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address($this->remitente, $this->nombreDelRemitente),
            subject: 'Correo de prueba de '.MarcaDelOrganizador::nombre(),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.correo-de-prueba', with: [
            'organizador' => MarcaDelOrganizador::nombre(),
        ]);
    }
}
