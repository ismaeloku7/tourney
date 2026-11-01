<?php

namespace App\Mail;

use App\Actions\Plataforma\SolicitarAltaDeOrganizador;
use App\Models\Organizador;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** El correo del alta: un enlace para confirmar la dirección y activar la organización. */
class VerificarCorreoDelOrganizador extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Organizador $organizador, public string $token) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Confirma tu correo para activar '.$this->organizador->nombre);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.verificar-organizador', with: [
            'enlace' => route('alta.verificar', ['organizador' => $this->organizador->id, 'token' => $this->token]),
            'horas' => SolicitarAltaDeOrganizador::HORAS_PARA_VERIFICAR,
        ]);
    }
}
