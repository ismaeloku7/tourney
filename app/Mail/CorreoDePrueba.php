<?php

namespace App\Mail;

use App\Filament\MarcaDelOrganizador;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** El correo que manda Ajustes para comprobar que el servidor del organizador funciona. */
class CorreoDePrueba extends Mailable
{
    use Queueable;

    public function __construct(public string $servidor) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Correo de prueba de '.MarcaDelOrganizador::nombre());
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.correo-de-prueba', with: [
            'organizador' => MarcaDelOrganizador::nombre(),
        ]);
    }
}
