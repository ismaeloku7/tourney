<?php

namespace App\Actions\Competicion;

use App\Competicion\ConstructorDeConsolacion;
use App\Competicion\ConstructorDeCuadro;
use App\Competicion\Errores\OperacionNoPermitida;
use App\Enums\EstadoFase;
use App\Enums\EstadoParticipante;
use App\Enums\EstadoPartido;
use App\Enums\OrigenDeConsolacion;
use App\Enums\TipoDeFase;
use App\Models\Fase;
use App\Models\Franja;
use App\Models\Participante;
use App\Models\Partido;
use App\Models\RegistroDeCambio;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Cambia a mano las parejas de los cruces de la primera ronda de un cuadro
 * ya hecho, cuando los criterios no han dejado a cada una donde toca: se
 * puede mover una pareja de cruce, intercambiar dos, dejar un hueco libre
 * (un bye) o meter a otra pareja de la categoría. El cuadro se rehace
 * con esos huecos (los byes pasan solos) y cada partido conserva su hora,
 * su pista y su formato. Solo mientras no se ha jugado ningún partido del
 * cuadro. Si su consolación sale de los perdedores de la primera ronda,
 * también se rehace.
 */
final class CambiarCruces
{
    public function __construct(
        private readonly ConstructorDeCuadro $constructor = new ConstructorDeCuadro,
        private readonly ConstructorDeConsolacion $consolacion = new ConstructorDeConsolacion,
        private readonly Cuadros $cuadros = new Cuadros,
    ) {}

    /** Si el cuadro ya tiene algún partido jugado o en juego (los byes no cuentan). */
    public static function yaSeJuega(Fase $fase): bool
    {
        return $fase->partidos()
            ->where(fn ($q) => $q
                ->whereIn('estado', [EstadoPartido::Jugado->value, EstadoPartido::PendienteValidacion->value, EstadoPartido::EnJuego->value])
                ->orWhere(fn ($w) => $w->where('estado', EstadoPartido::Walkover->value)->whereNotNull('local_id')->whereNotNull('visitante_id')))
            ->exists();
    }

    /**
     * Los huecos de la primera ronda, en orden: local y visitante del
     * primer cruce, del segundo… Nulo es un bye.
     *
     * @return list<int|null>
     */
    public static function huecos(Fase $fase): array
    {
        $huecos = [];
        foreach ($fase->partidos()->where('ronda', 1)->where('es_tercer_puesto', false)->orderBy('posicion')->get() as $partido) {
            $huecos[] = $partido->local_id;
            $huecos[] = $partido->visitante_id;
        }

        return $huecos;
    }

    /**
     * @param  list<int|null>  $huecos  Como los da huecos(), con las parejas nuevas.
     * @return bool Si ha cambiado algo.
     *
     * @throws OperacionNoPermitida
     */
    public function handle(Fase $fase, array $huecos, User $usuario): bool
    {
        if ($fase->tipo !== TipoDeFase::Eliminatoria) {
            throw new OperacionNoPermitida('Solo se cambian los cruces de un cuadro eliminatorio.');
        }
        if ($fase->estado === EstadoFase::Cerrada) {
            throw new OperacionNoPermitida('El cuadro está cerrado: sus cruces ya no se cambian.');
        }
        if (self::yaSeJuega($fase)) {
            throw new OperacionNoPermitida('Ya se ha jugado algún partido del cuadro: los cruces no se cambian.');
        }

        $antes = self::huecos($fase);
        if ($antes === []) {
            throw new OperacionNoPermitida('El cuadro todavía no tiene cruces: se hacen al cerrar la fase de grupos.');
        }
        if (count($huecos) !== count($antes)) {
            throw new OperacionNoPermitida('Faltan huecos del cuadro: vuelve a abrir el diálogo.');
        }
        $huecos = array_values($huecos);
        if ($huecos === $antes) {
            return false;
        }

        $nombres = Participante::query()->where('categoria_id', $fase->categoria_id)->where('estado', EstadoParticipante::Activo->value)->pluck('nombre', 'id');
        $vistos = [];
        foreach ($huecos as $i => $id) {
            if ($id === null) {
                if ($i % 2 === 1 && $huecos[$i - 1] === null) {
                    throw new OperacionNoPermitida('El cruce '.(intdiv($i, 2) + 1).' se queda sin parejas.');
                }

                continue;
            }
            if (! $nombres->has($id)) {
                throw new OperacionNoPermitida('Una de las parejas no es de esta categoría o ya no juega.');
            }
            if (isset($vistos[$id])) {
                throw new OperacionNoPermitida('«'.$nombres->get($id).'» está en dos cruces.');
            }
            $vistos[$id] = true;
        }

        DB::transaction(function () use ($fase, $huecos, $antes, $usuario): void {
            $configuracion = $fase->configuracionDeEliminatoria();
            $cuadro = $this->constructor->desdeHuecos($huecos, $configuracion);

            // Lo que cada partido ya tenía (hora, pista, formato) se queda en el mismo sitio del cuadro.
            $clave = fn (Partido $p): string => $p->ronda.'-'.($p->es_tercer_puesto ? 't' : 'c').'-'.$p->posicion;
            $tenian = $fase->partidos()->get()->keyBy($clave);
            $fase->partidos()->forceDelete();
            $this->cuadros->crear($fase, $cuadro, $configuracion->formatosPorRonda);

            $usadas = [];
            foreach ($fase->partidos()->get() as $partido) {
                $antiguo = $tenian->get($clave($partido));
                if ($antiguo === null) {
                    continue;
                }

                $cambios = ['notas' => $antiguo->notas];
                if ($antiguo->formato_partido_id !== null) {
                    $cambios['formato_partido_id'] = $antiguo->formato_partido_id;
                }
                // Un bye no ocupa pista: su hora se suelta.
                if ($antiguo->franja_id !== null && $partido->estado === EstadoPartido::Pendiente) {
                    $cambios += ['franja_id' => $antiguo->franja_id, 'pista_id' => $antiguo->pista_id, 'estado' => EstadoPartido::Programado];
                    $usadas[] = $antiguo->franja_id;
                }
                $partido->update($cambios);
            }

            $sueltas = array_diff($tenian->pluck('franja_id')->filter()->all(), $usadas);
            if ($sueltas !== []) {
                Franja::query()->whereIn('id', $sueltas)->where('bloqueada', false)->delete();
            }

            $this->rehacerConsolacion($fase, $configuracion->consolacion);

            RegistroDeCambio::anotar('cambiar_cruces', $fase, $usuario, ['huecos' => $antes], ['huecos' => $huecos]);
        });

        return true;
    }

    /** La consolación que sale de los perdedores de la primera ronda apunta a los partidos de antes. */
    private function rehacerConsolacion(Fase $fase, OrigenDeConsolacion $origen): void
    {
        if ($origen !== OrigenDeConsolacion::PerdedoresPrimeraRonda) {
            return;
        }

        $consolacion = $fase->fasesDerivadas()->where('tipo', TipoDeFase::Consolacion->value)->first();
        if ($consolacion === null) {
            return;
        }

        Partido::borrarConSusHoras($consolacion->partidos());
        $this->cuadros->crear($consolacion, $this->consolacion->desdePerdedores($this->cuadros->cargar($fase), $consolacion->configuracionDeEliminatoria()));
    }
}
