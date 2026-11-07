<?php

namespace App\Actions\Jugadores;

use App\Enums\EstadoInscripcion;
use App\Models\AccesoDirecto;
use App\Models\Inscripcion;
use App\Models\Jugador;
use App\Models\RegistroDeCambio;
use App\Models\User;
use App\Tenancy\OrganizadorActual;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Los enlaces de acceso directo de las parejas: con el suyo, uno de sus
 * jugadores (el primero que tenga; da igual cuál) entra en su portal sin
 * correo ni contraseña. No caducan: valen hasta que la organización los
 * borra. Una pareja de baja o borrada ya no entra con el suyo.
 */
final class AccesosDirectos
{
    /** El de la pareja; si no tiene, se crea. Nulo si la pareja no tiene jugadores. */
    public function crear(Inscripcion $inscripcion, ?User $usuario): ?AccesoDirecto
    {
        $existente = $inscripcion->accesoDirecto()->first();
        if ($existente !== null) {
            return $existente;
        }

        $jugador = $inscripcion->participante->jugadores()->whereNull('anonimizado_en')->orderBy('jugadores.id')->first();
        if (! $jugador instanceof Jugador) {
            return null;
        }

        $token = Str::random(48);
        $acceso = AccesoDirecto::query()->create([
            'inscripcion_id' => $inscripcion->id,
            'jugador_id' => $jugador->id,
            'token' => $token,
            'token_hash' => hash('sha256', $token),
            'creado_por' => $usuario?->id,
        ]);

        RegistroDeCambio::anotar('crear_acceso_directo', $inscripcion, $usuario, null, ['jugador' => $jugador->id]);

        return $acceso;
    }

    /**
     * Crea el de cada pareja que no lo tenga (las de baja no). Devuelve
     * cuántos se han creado.
     *
     * @param  Builder<Inscripcion>  $inscripciones
     */
    public function crearTodos(Builder $inscripciones, ?User $usuario): int
    {
        $creados = 0;
        foreach ((clone $inscripciones)->where('inscripciones.estado', '!=', EstadoInscripcion::Baja->value)->whereDoesntHave('accesoDirecto')->with('participante')->get() as $inscripcion) {
            if ($this->crear($inscripcion, $usuario) !== null) {
                $creados++;
            }
        }

        return $creados;
    }

    /**
     * Borra los de esas parejas: sus enlaces dejan de valer. Devuelve
     * cuántos se han borrado.
     *
     * @param  Builder<Inscripcion>  $inscripciones
     */
    public function borrarTodos(Builder $inscripciones): int
    {
        $ids = (clone $inscripciones)->pluck('inscripciones.id')->all();

        return AccesoDirecto::query()->whereIn('inscripcion_id', $ids)->delete();
    }

    /** La dirección completa del enlace, en la web del organizador. */
    public static function url(AccesoDirecto $acceso): string
    {
        return app(OrganizadorActual::class)->organizador()->url('acceso/'.$acceso->token);
    }

    /** El acceso de ese token, si vale: la pareja sigue apuntada y el jugador existe. */
    public function usar(string $token): ?AccesoDirecto
    {
        $acceso = AccesoDirecto::query()->where('token_hash', hash('sha256', $token))->first();
        // Una pareja borrada (a la papelera) no se carga: su enlace ya no vale.
        $inscripcion = $acceso?->inscripcion()->first();
        $jugador = $acceso?->jugador()->first();

        if ($acceso === null || $inscripcion === null || $inscripcion->estado === EstadoInscripcion::Baja
            || $jugador === null || $jugador->anonimizado_en !== null) {
            return null;
        }

        $acceso->forceFill(['ultimo_uso_en' => now()])->save();

        return $acceso;
    }
}
