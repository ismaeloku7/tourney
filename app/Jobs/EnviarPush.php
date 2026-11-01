<?php

namespace App\Jobs;

use App\Models\SuscripcionPush;
use App\Push\Remitente;
use App\Tenancy\OrganizadorActual;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Entrega una notificación push a unos dispositivos del organizador. Un
 * solo intento: un aviso que llega tarde ya no avisa. El dispositivo que
 * ya no existe se olvida. El organizador va en el trabajo (bootstrapper de
 * colas de stancl).
 */
class EnviarPush implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    /**
     * @param  list<int>  $suscripciones
     * @param  array{titulo: string, texto: string, url: string, etiqueta: string|null}  $carga
     */
    public function __construct(
        public string $organizadorId,
        public array $suscripciones,
        public array $carga,
    ) {}

    public function handle(OrganizadorActual $organizadorActual, Remitente $remitente): void
    {
        if ($organizadorActual->slug() !== $this->organizadorId) {
            throw new RuntimeException('La notificación se encoló en otro organizador.');
        }

        $carga = (string) json_encode($this->carga, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        foreach (SuscripcionPush::query()->whereKey($this->suscripciones)->get() as $suscripcion) {
            try {
                $resultado = $remitente->enviar($suscripcion, $carga);
            } catch (Throwable $e) {
                Log::warning('No ha salido una notificación push.', ['organizador' => $this->organizadorId, 'error' => $e::class]);

                continue;
            }

            if ($resultado === null) {
                $suscripcion->delete();
            } elseif ($resultado) {
                $suscripcion->forceFill(['ultimo_envio' => now()])->save();
            }
        }
    }
}
