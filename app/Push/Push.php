<?php

namespace App\Push;

use App\Jobs\EnviarPush;
use App\Models\Jugador;
use App\Models\SuscripcionPush;
use App\Models\Torneo;
use App\Tenancy\OrganizadorActual;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Las notificaciones push del organizador: guardar el dispositivo que las
 * acepta y mandarle avisos. Salen por la cola y después de confirmar la
 * transacción. En la pantalla bloqueada solo se ve el título y una frase;
 * nunca datos personales de nadie más que del propio partido.
 */
class Push
{
    public function __construct(private OrganizadorActual $organizadorActual) {}

    /** Con las claves VAPID puestas: si no, no se ofrece ni se manda nada. */
    public static function configurado(): bool
    {
        return filled(config('webpush.clave_publica')) && filled(config('webpush.clave_privada'));
    }

    public static function clavePublica(): ?string
    {
        return self::configurado() ? config()->string('webpush.clave_publica') : null;
    }

    /**
     * Guarda (o pasa a este dueño) el dispositivo. Sin jugador, es de alguien
     * que sigue torneos sin cuenta.
     *
     * @param  array<string, mixed>  $suscripcion  la de `PushSubscription.toJSON()`
     */
    public function guardar(array $suscripcion, ?Jugador $jugador, ?string $dispositivo = null): SuscripcionPush
    {
        $endpoint = $suscripcion['endpoint'] ?? null;
        $claves = is_array($suscripcion['keys'] ?? null) ? $suscripcion['keys'] : [];

        if (! is_string($endpoint) || ! str_starts_with($endpoint, 'https://') || strlen($endpoint) > 2000
            || ! is_string($claves['p256dh'] ?? null) || ! is_string($claves['auth'] ?? null)
            || strlen($claves['p256dh']) > 255 || strlen($claves['auth']) > 255) {
            throw new InvalidArgumentException('Suscripción push no válida.');
        }

        $existente = SuscripcionPush::query()->where('endpoint_hash', hash('sha256', $endpoint))->first();

        return SuscripcionPush::query()->updateOrCreate(
            ['endpoint_hash' => hash('sha256', $endpoint)],
            [
                'endpoint' => $endpoint,
                'clave_p256dh' => $claves['p256dh'],
                'clave_auth' => $claves['auth'],
                // Seguir un torneo sin cuenta no le quita el dueño a un dispositivo que ya lo tiene.
                'jugador_id' => $jugador !== null ? $jugador->id : $existente?->jugador_id,
                'dispositivo' => $dispositivo !== null ? Str::limit($dispositivo, 117) : $existente?->dispositivo,
            ],
        );
    }

    public function quitar(string $endpoint): void
    {
        SuscripcionPush::query()->where('endpoint_hash', hash('sha256', $endpoint))->delete();
    }

    /**
     * A los dispositivos de unos jugadores que no han apagado el push.
     *
     * @param  iterable<Jugador>  $jugadores
     * @param  string  $url  absoluta, o una ruta del organizador («/jugador»)
     */
    public function aJugadores(iterable $jugadores, string $titulo, string $texto, string $url, ?string $etiqueta = null): void
    {
        $ids = [];
        foreach ($jugadores as $jugador) {
            if ((bool) ($jugador->avisos['push'] ?? true)) {
                $ids[] = $jugador->id;
            }
        }

        $this->mandar($ids === [] ? [] : array_values(SuscripcionPush::query()->whereIn('jugador_id', $ids)->pluck('id')->map(fn (mixed $id): int => (int) $id)->all()), $titulo, $texto, $url, $etiqueta);
    }

    /** A quien sigue el torneo desde un dispositivo, tenga cuenta o no. */
    public function aSeguidores(Torneo $torneo, string $titulo, string $texto, string $url, ?string $etiqueta = null): void
    {
        $ids = SuscripcionPush::query()->whereHas('torneos', fn ($q) => $q->whereKey($torneo->id))->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();

        $this->mandar(array_values($ids), $titulo, $texto, $url, $etiqueta);
    }

    /** @param  list<int>  $ids */
    private function mandar(array $ids, string $titulo, string $texto, string $url, ?string $etiqueta): void
    {
        if ($ids === [] || ! self::configurado()) {
            return;
        }

        $carga = [
            'titulo' => Str::limit($titulo, 100),
            'texto' => Str::limit($texto, 180),
            'url' => str_starts_with($url, 'http') ? $url : $this->organizadorActual->organizador()->url($url),
            'etiqueta' => $etiqueta,
        ];

        EnviarPush::dispatch($this->organizadorActual->slug(), array_values(array_unique($ids)), $carga)->afterCommit();
    }
}
