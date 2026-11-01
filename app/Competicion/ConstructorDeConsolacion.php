<?php

namespace App\Competicion;

use App\Competicion\Datos\Clasificado;
use App\Competicion\Datos\ConfiguracionDeEliminatoria;
use App\Competicion\Datos\Cuadro;
use App\Competicion\Datos\Origen;
use App\Competicion\Errores\ConfiguracionInvalida;

/**
 * El cuadro de consolación: con los no clasificados de la fase de grupos
 * (mismas reglas que el principal) o con los perdedores de la primera
 * ronda del cuadro principal (un hueco por partido, en el mismo orden;
 * un partido con bye no tiene perdedor y deja un bye).
 */
final readonly class ConstructorDeConsolacion
{
    public function __construct(private ConstructorDeCuadro $constructor = new ConstructorDeCuadro) {}

    /**
     * @param  list<Clasificado>  $noClasificados
     */
    public function construir(array $noClasificados, ConfiguracionDeEliminatoria $configuracion): Cuadro
    {
        return $this->constructor->construir($noClasificados, $configuracion);
    }

    public function desdePerdedores(Cuadro $principal, ConfiguracionDeEliminatoria $configuracion): Cuadro
    {
        $primeraRonda = $principal->partidosDeRonda(1);
        usort($primeraRonda, fn ($a, $b): int => $a->posicion <=> $b->posicion);

        if (count($primeraRonda) < 2) {
            throw new ConfiguracionInvalida(['Un cuadro de 2 no tiene consolación de perdedores de primera ronda.']);
        }

        $huecos = [];
        foreach ($primeraRonda as $partido) {
            $dosLados = ($partido->local !== null || $partido->origenLocal !== null) && ($partido->visitante !== null || $partido->origenVisitante !== null);
            $huecos[] = $dosLados ? Origen::perdedorDe((int) $partido->id) : null;
        }

        return $this->constructor->desdeHuecos($huecos, $configuracion, ['La consolación se rellena con los perdedores de la primera ronda del cuadro principal.']);
    }
}
