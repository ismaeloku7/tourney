<?php

namespace App\Mail;

use App\Actions\Jugadores\MandarEnlaceDeAcceso;
use App\Filament\MarcaDelOrganizador;
use App\Models\Jugador;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** El enlace para entrar en el portal del jugador sin contraseña. */
class EnlaceDeAcceso extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Jugador $jugador, public string $enlace) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Tu enlace para entrar en '.MarcaDelOrganizador::nombre());
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.enlace-de-acceso', with: [
            'organizador' => MarcaDelOrganizador::nombre(),
            'minutos' => MandarEnlaceDeAcceso::MINUTOS,
        ]);
    }
}
