<?php

namespace App\Competicion;

use App\Competicion\Datos\Clasificacion;
use App\Competicion\Datos\ConfiguracionDeEliminatoria;
use App\Competicion\Datos\CruceProyectado;
use App\Competicion\Datos\Partido;
use App\Competicion\Datos\Puntuacion;
use App\Competicion\Datos\ReglaDeClasificados;
use App\Competicion\Errores\ErrorDeCompeticion;
use App\Enums\CriterioDeDesempate;
use App\Enums\ReglaDeCruces;

/**
 * Con la clasificación tal y como va, qué cruce tendría cada pareja en la
 * primera ronda del cuadro si la fase de grupos acabase así. Usa el mismo
 * selector y el mismo constructor que el cierre de fase, así que lo que se
 * enseña es lo que saldría. Con sorteo puro no hay nada que adelantar, y
 * si los cruces manuales aún no casan con los clasificados tampoco.
 */
final readonly class ProyectorDeCruces
{
    public function __construct(
        private SelectorDeClasificados $selector = new SelectorDeClasificados,
        private ConstructorDeCuadro $constructor = new ConstructorDeCuadro,
    ) {}

    /**
     * @param  list<Clasificacion>  $clasificaciones
     * @param  list<CriterioDeDesempate>  $criterios
     * @param  list<Partido>  $partidos  Todos los de la fase de grupos.
     * @param  array<int, string>  $nombresDeGrupo  Id de grupo → letra.
     * @return array<int, CruceProyectado> Id de participante → su cruce.
     */
    public function proyectar(array $clasificaciones, ReglaDeClasificados $regla, array $criterios, Puntuacion $puntuacion, array $partidos, array $nombresDeGrupo, ConfiguracionDeEliminatoria $cuadro): array
    {
        if ($cuadro->reglaDeCruces === ReglaDeCruces::Sorteo || $clasificaciones === []) {
            return [];
        }

        try {
            $clasificados = $this->selector->seleccionar($clasificaciones, $regla, $criterios, $puntuacion, $partidos, $nombresDeGrupo);
            $construido = $this->constructor->construir($clasificados, $cuadro);
        } catch (ErrorDeCompeticion) {
            return [];
        }

        $porParticipante = [];
        foreach ($clasificados as $clasificado) {
            $porParticipante[$clasificado->participante] = $clasificado;
        }

        $cruces = [];

        foreach ($construido->partidosDeRonda(1) as $partido) {
            foreach ([[$partido->local, $partido->visitante], [$partido->visitante, $partido->local]] as [$quien, $rival]) {
                if ($quien === null) {
                    continue;
                }

                $cruces[$quien] = new CruceProyectado(
                    participante: $quien,
                    origen: $porParticipante[$quien]->origen,
                    rival: $rival,
                    origenDelRival: $rival === null ? null : $porParticipante[$rival]->origen,
                );
            }
        }

        return $cruces;
    }
}
