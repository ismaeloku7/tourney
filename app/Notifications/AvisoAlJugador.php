<?php

namespace App\Notifications;

use App\Filament\MarcaDelOrganizador;
use App\Models\Jugador;
use App\Push\CanalPush;
use App\Tenancy\OrganizadorActual;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Un aviso a un jugador: se escribe una vez y sale por correo, push y el
 * historial de su portal, según el aviso y lo que él haya elegido. Por la
 * cola y después de confirmar la transacción.
 */
abstract class AvisoAlJugador extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->afterCommit();
    }

    abstract public function titulo(): string;

    abstract public function texto(): string;

    /** Ruta del organizador («/torneos/x») o dirección completa. */
    abstract public function url(): string;

    public function etiqueta(): ?string
    {
        return null;
    }

    /**
     * Lo que lleva el correo además del texto, línea a línea.
     *
     * @return list<string>
     */
    public function lineas(): array
    {
        return [];
    }

    protected function conCorreo(): bool
    {
        return true;
    }

    protected function conPush(): bool
    {
        return true;
    }

    /** @return list<string|class-string> */
    public function via(object $notifiable): array
    {
        $canales = ['database'];

        if (! $notifiable instanceof Jugador || $notifiable->anonimizado_en !== null) {
            return [];
        }

        $avisos = $notifiable->avisos ?? [];

        if ($this->conCorreo() && filled($notifiable->getAttribute('email')) && (bool) ($avisos['correo'] ?? true)) {
            $canales[] = 'mail';
        }

        if ($this->conPush() && (bool) ($avisos['push'] ?? true)) {
            $canales[] = CanalPush::class;
        }

        return $canales;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mensaje = (new MailMessage)
            ->subject($this->titulo().' · '.MarcaDelOrganizador::nombre())
            ->greeting('Hola, '.($notifiable instanceof Jugador ? $notifiable->nombre : '').':')
            ->line($this->texto());

        foreach ($this->lineas() as $linea) {
            $mensaje->line($linea);
        }

        return $mensaje
            ->action('Ver en la web', $this->urlCompleta())
            ->salutation(MarcaDelOrganizador::nombre());
    }

    /** @return array{titulo: string, texto: string, url: string} */
    public function toArray(object $notifiable): array
    {
        return ['titulo' => $this->titulo(), 'texto' => $this->texto(), 'url' => $this->url()];
    }

    public function urlCompleta(): string
    {
        $url = $this->url();

        return str_starts_with($url, 'http') ? $url : app(OrganizadorActual::class)->organizador()->url($url);
    }
}
