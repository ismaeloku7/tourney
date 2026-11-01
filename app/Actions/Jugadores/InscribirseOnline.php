<?php

namespace App\Actions\Jugadores;

use App\Actions\Inscripciones\InscribirPareja;
use App\Competicion\Errores\OperacionNoPermitida;
use App\Enums\EstadoInscripcion;
use App\Enums\OrigenInscripcion;
use App\Mail\InvitacionDePareja;
use App\Models\Categoria;
use App\Models\Inscripcion;
use App\Models\Jugador;
use App\Models\Torneo;
use App\Tenancy\OrganizadorActual;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * La inscripción que hace un jugador desde la web: él con su compañero.
 * El compañero es un jugador de la base (por su correo) o uno nuevo, y
 * recibe una invitación de 48 horas. Hasta que confirma, la inscripción
 * queda pendiente y no ocupa plaza.
 */
final class InscribirseOnline
{
    public const HORAS_PARA_CONFIRMAR = 48;

    public function __construct(private readonly InscribirPareja $inscribir) {}

    /**
     * @param  array{email: string, nombre?: string|null, apellidos?: string|null, telefono?: string|null}  $companero
     *
     * @throws OperacionNoPermitida
     */
    public function handle(Jugador $yo, Categoria $categoria, array $companero, ?string $notas = null): Inscripcion
    {
        $torneo = $categoria->torneo;
        self::comprobarQueEstaAbierta($torneo);
        self::comprobarLimiteDelPlan($torneo);

        $email = Str::lower(trim($companero['email']));
        if ($email === Str::lower((string) $yo->getAttribute('email'))) {
            throw new OperacionNoPermitida('Tu compañero no puedes ser tú: escribe su correo.');
        }

        $token = Str::random(64);

        $inscripcion = DB::transaction(function () use ($yo, $categoria, $companero, $email, $token, $notas): Inscripcion {
            $otro = Jugador::query()->where('email', $email)->first();

            if ($otro !== null && $otro->anonimizado_en !== null) {
                throw new OperacionNoPermitida('Ese correo no se puede usar. Pide a tu compañero que se registre de nuevo.');
            }

            if ($otro === null) {
                $nombre = trim((string) ($companero['nombre'] ?? ''));
                $apellidos = trim((string) ($companero['apellidos'] ?? ''));

                if ($nombre === '' || $apellidos === '') {
                    throw new OperacionNoPermitida('Tu compañero aún no está registrado: escribe su nombre y sus apellidos.');
                }

                $otro = Jugador::query()->create([
                    'nombre' => $nombre,
                    'apellidos' => $apellidos,
                    'email' => $email,
                    'telefono' => filled($companero['telefono'] ?? null) ? trim((string) $companero['telefono']) : null,
                ]);
            }

            $inscripcion = $this->inscribir->handle($categoria, $yo, $otro, OrigenInscripcion::Online, null, confirmada: false, notas: $notas);

            $inscripcion->participante->participanteJugadores()->where('jugador_id', $otro->id)->update([
                'confirmado_en' => null,
                'invitacion_token' => hash('sha256', $token),
                'invitacion_expira_en' => now()->addHours(self::HORAS_PARA_CONFIRMAR),
            ]);

            return $inscripcion;
        });

        $invitado = Jugador::query()->where('email', $email)->firstOrFail();
        $enlace = app(OrganizadorActual::class)->organizador()->url('invitacion/'.$token);
        Mail::to($email)->send(new InvitacionDePareja($invitado, $yo, $categoria, $enlace));

        return $inscripcion;
    }

    /** @throws OperacionNoPermitida */
    public static function comprobarQueEstaAbierta(Torneo $torneo): void
    {
        $abierta = $torneo->inscripcionAbierta()
            && ($torneo->inscripcion_desde === null || $torneo->inscripcion_desde->isPast())
            && ($torneo->inscripcion_hasta === null || $torneo->inscripcion_hasta->isFuture());

        if (! $abierta) {
            throw new OperacionNoPermitida('La inscripción de este torneo no está abierta.');
        }
    }

    /**
     * El límite de parejas por torneo del plan del organizador: cuenta las
     * que ocupan o esperan plaza.
     *
     * @throws OperacionNoPermitida
     */
    public static function comprobarLimiteDelPlan(Torneo $torneo): void
    {
        $limite = app(OrganizadorActual::class)->organizador()->plan()->value('parejas_por_torneo');

        if ($limite === null) {
            return;
        }

        $ocupadas = Inscripcion::query()
            ->whereHas('categoria', fn ($q) => $q->where('torneo_id', $torneo->id))
            ->whereIn('estado', [EstadoInscripcion::Confirmada->value, EstadoInscripcion::Pendiente->value])
            ->count();

        if ($ocupadas >= (int) $limite) {
            throw new OperacionNoPermitida('El torneo ha llegado al máximo de parejas.');
        }
    }
}
