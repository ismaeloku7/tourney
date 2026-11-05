<?php

namespace App\Actions\Competicion;

use App\Avisos\Avisos;
use App\Competicion\Errores\OperacionNoPermitida;
use App\Enums\EstadoPartido;
use App\Models\Jugador;
use App\Models\Partido;
use App\Models\User;

/**
 * El marcador en vivo de un partido en juego: los sets cerrados y los
 * juegos del set en curso. Cuando el partido entra en un set que puede
 * ser el último, avisa (una vez) a la pareja que juega después en esa
 * pista. El resultado final se registra aparte, con `RegistrarResultado`.
 */
final class AnotarMarcador
{
    /**
     * @param  list<array{0: int, 1: int}>  $sets  Sets cerrados, en orden.
     * @param  array{0: int, 1: int}  $juegos  Juegos del set en curso.
     */
    public function handle(Partido $partido, array $sets, array $juegos, User|Jugador $quien): Partido
    {
        if ($partido->estado !== EstadoPartido::EnJuego) {
            throw new OperacionNoPermitida('El marcador solo se lleva con el partido en juego.');
        }

        EmpezarPartido::comprobarQuien($partido, $quien);

        $limpios = ['sets' => [], 'juegos' => [max(0, (int) ($juegos[0] ?? 0)), max(0, (int) ($juegos[1] ?? 0))]];
        foreach ($sets as $set) {
            $limpios['sets'][] = [max(0, (int) ($set[0] ?? 0)), max(0, (int) ($set[1] ?? 0))];
        }

        $partido->forceFill(['marcador_en_vivo' => $limpios])->saveQuietly();

        $torneo = $partido->fase->categoria->torneo;
        $umbral = intdiv($partido->formatoEfectivo()->sets, 2);

        if ($torneo->encadenado() && $umbral >= 1 && count($limpios['sets']) >= $umbral) {
            app(Avisos::class)->siguienteEnPista($partido, 'ultimo_set');
        }

        return $partido;
    }

    /** «6-3 4-2»: los sets cerrados y el set en curso, o nada si no ha empezado. */
    public static function texto(Partido $partido): string
    {
        $marcador = $partido->marcador_en_vivo;
        if ($marcador === null) {
            return '';
        }

        $partes = array_map(fn (array $set): string => "{$set[0]}-{$set[1]}", $marcador['sets'] ?? []);
        $juegos = $marcador['juegos'] ?? [0, 0];
        if ($partes === [] || $juegos[0] > 0 || $juegos[1] > 0) {
            $partes[] = "{$juegos[0]}-{$juegos[1]}";
        }

        return implode(' ', $partes);
    }
}
