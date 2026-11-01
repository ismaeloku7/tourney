<?php

namespace App\Mail;

use App\Actions\Jugadores\InscribirseOnline;
use App\Filament\MarcaDelOrganizador;
use App\Models\Categoria;
use App\Models\Jugador;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** La invitación al compañero: confirmar que juega con quien le ha inscrito. */
class InvitacionDePareja extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Jugador $invitado, public Jugador $quienInvita, public Categoria $categoria, public string $enlace) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->quienInvita->nombre.' te ha inscrito en '.$this->categoria->torneo->nombre);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.invitacion-de-pareja', with: [
            'organizador' => MarcaDelOrganizador::nombre(),
            'horas' => InscribirseOnline::HORAS_PARA_CONFIRMAR,
        ]);
    }
}
