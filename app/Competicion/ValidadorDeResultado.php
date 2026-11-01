<?php

namespace App\Competicion;

use App\Competicion\Datos\FormatoDePartido;
use App\Competicion\Datos\Resultado;
use App\Competicion\Datos\Set;
use App\Competicion\Datos\ValidacionDeResultado;
use App\Enums\ResultadoEspecial;

/**
 * Comprueba un resultado contra el formato del partido: juegos por set,
 * diferencia, tie-break, super tie-break, partidos por tiempo y
 * resultados especiales. Devuelve el ganador cuando el resultado vale.
 */
final readonly class ValidadorDeResultado
{
    public function validar(Resultado $resultado, FormatoDePartido $formato): ValidacionDeResultado
    {
        if ($resultado->especial !== null) {
            return $this->validarEspecial($resultado);
        }

        if ($formato->porTiempoMinutos !== null) {
            return $this->validarPorTiempo($resultado);
        }

        return $this->validarPorSets($resultado, $formato);
    }

    private function validarEspecial(Resultado $resultado): ValidacionDeResultado
    {
        $especial = $resultado->especial;
        assert($especial instanceof ResultadoEspecial);

        if (! $especial->tieneGanador()) {
            return $resultado->ganaLocal === null
                ? new ValidacionDeResultado(true, [], null, 0, 0)
                : new ValidacionDeResultado(false, ['Un partido no presentado no tiene ganador.']);
        }

        if ($resultado->ganaLocal === null) {
            return new ValidacionDeResultado(false, ['Con '.$especial->etiqueta().' hay que indicar quién gana.']);
        }

        return new ValidacionDeResultado(true, [], $resultado->ganaLocal, $resultado->setsGanadosPorLocal(), $resultado->setsGanadosPorVisitante());
    }

    private function validarPorTiempo(Resultado $resultado): ValidacionDeResultado
    {
        if (count($resultado->sets) !== 1) {
            return new ValidacionDeResultado(false, ['Un partido por tiempo se anota como un solo set con los juegos de cada lado.']);
        }

        $set = $resultado->sets[0];

        if ($set->juegosLocal === $set->juegosVisitante) {
            return $resultado->ganaLocal === null
                ? new ValidacionDeResultado(false, ['Con empate a juegos hay que indicar quién gana el partido por tiempo.'])
                : new ValidacionDeResultado(true, [], $resultado->ganaLocal, $resultado->ganaLocal ? 1 : 0, $resultado->ganaLocal ? 0 : 1);
        }

        $ganaLocal = $set->ganaLocal();

        return new ValidacionDeResultado(true, [], $ganaLocal, $ganaLocal ? 1 : 0, $ganaLocal ? 0 : 1);
    }

    private function validarPorSets(Resultado $resultado, FormatoDePartido $formato): ValidacionDeResultado
    {
        $errores = [];
        $setsLocal = 0;
        $setsVisitante = 0;
        $paraGanar = $formato->setsParaGanar();

        if ($resultado->sets === []) {
            return new ValidacionDeResultado(false, ['Hace falta al menos un set.']);
        }

        foreach (array_values($resultado->sets) as $i => $set) {
            $numero = $i + 1;

            if ($setsLocal >= $paraGanar || $setsVisitante >= $paraGanar) {
                $errores[] = "El set {$numero} sobra: el partido ya estaba decidido.";

                break;
            }

            $esDecisivo = $numero === $formato->setDecisivo();

            if ($set->esSuperTieBreak && ! ($formato->superTieBreakFinal && $esDecisivo)) {
                $errores[] = "El set {$numero} no puede ser un super tie-break en este formato.";

                continue;
            }

            if ($formato->superTieBreakFinal && $esDecisivo && ! $set->esSuperTieBreak) {
                $errores[] = "El set {$numero} es el decisivo y se juega como super tie-break.";

                continue;
            }

            $error = $set->esSuperTieBreak
                ? $this->errorDeSuperTieBreak($set, $formato, $numero)
                : $this->errorDeSet($set, $formato, $numero);

            if ($error !== null) {
                $errores[] = $error;

                continue;
            }

            $set->ganaLocal() ? $setsLocal++ : $setsVisitante++;
        }

        if ($errores === [] && $setsLocal < $paraGanar && $setsVisitante < $paraGanar) {
            $errores[] = "Faltan sets: se juega a {$paraGanar} sets ganados.";
        }

        if ($errores !== []) {
            return new ValidacionDeResultado(false, $errores, null, $setsLocal, $setsVisitante);
        }

        return new ValidacionDeResultado(true, [], $setsLocal > $setsVisitante, $setsLocal, $setsVisitante);
    }

    private function errorDeSet(Set $set, FormatoDePartido $formato, int $numero): ?string
    {
        $juegos = $formato->juegosPorSet;
        $diferencia = $formato->diferenciaParaGanarSet;
        $tieBreakEn = $formato->tieBreakEn;
        $ganador = max($set->juegosLocal, $set->juegosVisitante);
        $perdedor = min($set->juegosLocal, $set->juegosVisitante);

        if ($set->juegosLocal < 0 || $set->juegosVisitante < 0) {
            return "El set {$numero} tiene juegos negativos.";
        }

        if ($ganador === $perdedor) {
            return "El set {$numero} no puede acabar en empate.";
        }

        // Set cerrado al llegar a los juegos con la diferencia exigida.
        if ($ganador === $juegos && $ganador - $perdedor >= $diferencia) {
            return $this->errorDeTieBreakSobrante($set, $numero);
        }

        // Tie-break: a TB iguales, el set acaba TB+1 a TB con sus puntos.
        if ($tieBreakEn !== null && $ganador === $tieBreakEn + 1 && $perdedor === $tieBreakEn) {
            return $this->errorDeTieBreak($set, $formato, $numero);
        }

        if ($tieBreakEn === null) {
            // Sin tie-break el set sigue hasta sacar la diferencia.
            if ($ganador > $juegos && $ganador - $perdedor === $diferencia) {
                return null;
            }
        } elseif ($diferencia === 2 && $tieBreakEn === $juegos && $ganador === $juegos + 1 && $perdedor === $juegos - 1) {
            // 7-5 en un set a 6: la diferencia se saca antes del tie-break de
            // 6-6. En un set a 9 el tie-break va a 8-8, así que 10-8 no existe.
            return $this->errorDeTieBreakSobrante($set, $numero);
        }

        return "El set {$numero} ({$set->juegosLocal}-{$set->juegosVisitante}) no es posible en un set a {$juegos}".($tieBreakEn === null ? ' sin tie-break.' : " con tie-break a {$tieBreakEn} iguales.");
    }

    private function errorDeTieBreakSobrante(Set $set, int $numero): ?string
    {
        return $set->tieBreakLocal !== null || $set->tieBreakVisitante !== null
            ? "El set {$numero} no llegó al tie-break: sobran sus puntos."
            : null;
    }

    private function errorDeTieBreak(Set $set, FormatoDePartido $formato, int $numero): ?string
    {
        if ($set->tieBreakLocal === null || $set->tieBreakVisitante === null) {
            return "El set {$numero} ({$set->juegosLocal}-{$set->juegosVisitante}) se decidió en el tie-break: faltan sus puntos.";
        }

        $puntos = $formato->tieBreakPuntos;
        $ganador = max($set->tieBreakLocal, $set->tieBreakVisitante);
        $perdedor = min($set->tieBreakLocal, $set->tieBreakVisitante);

        if ($ganador < $puntos || $ganador - $perdedor < 2) {
            return "El tie-break del set {$numero} se juega a {$puntos} puntos con 2 de diferencia.";
        }

        if (($set->tieBreakLocal > $set->tieBreakVisitante) !== $set->ganaLocal()) {
            return "En el set {$numero} el tie-break lo gana el otro lado.";
        }

        return null;
    }

    private function errorDeSuperTieBreak(Set $set, FormatoDePartido $formato, int $numero): ?string
    {
        $puntos = $formato->superTieBreakPuntos;
        $ganador = max($set->juegosLocal, $set->juegosVisitante);
        $perdedor = min($set->juegosLocal, $set->juegosVisitante);

        if ($ganador === $perdedor || $ganador < $puntos || $ganador - $perdedor < 2) {
            return "El super tie-break del set {$numero} se juega a {$puntos} puntos con 2 de diferencia.";
        }

        return null;
    }
}
