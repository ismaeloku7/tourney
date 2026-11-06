<?php

namespace App\Filament\Resources\Parejas;

use App\Models\Inscripcion;
use App\Models\Jugador;
use Closure;

/**
 * Un listado de parejas como texto para pegarlo en WhatsApp, con lo que se
 * ve en la tabla: si se ve la categoría (o el torneo), una cabecera en
 * negrita por cada una; después, una línea numerada por pareja con las
 * columnas visibles («Los Cracks: Ana López y Eva Ruiz · Nivel 3,5 y 2 ·
 * Confirmada · Pagado»).
 */
final class TextoParaWhatsApp
{
    /**
     * @param  iterable<Inscripcion>  $parejas  Con `categoria.torneo` y `participante.jugadores` cargados.
     * @param  list<string>  $columnas  Los nombres de las columnas visibles de la tabla.
     */
    public static function componer(iterable $parejas, array $columnas): string
    {
        $ver = fn (string $columna): bool => in_array($columna, $columnas, true);

        $grupos = [];
        foreach ($parejas as $pareja) {
            $titulo = implode(' · ', array_filter([
                $ver('categoria.torneo.nombre') ? $pareja->categoria->torneo->nombre : null,
                $ver('categoria.nombre') ? $pareja->categoria->nombre : null,
            ]));
            $grupos[$titulo][] = self::linea($pareja, $ver);
        }

        $bloques = [];
        foreach ($grupos as $titulo => $lineas) {
            $bloque = $titulo !== '' ? ["*{$titulo}*"] : [];
            foreach ($lineas as $i => $linea) {
                $bloque[] = ($i + 1).'. '.$linea;
            }
            $bloques[] = implode("\n", $bloque);
        }

        return implode("\n\n", $bloques);
    }

    /** @param  Closure(string): bool  $ver */
    private static function linea(Inscripcion $pareja, Closure $ver): string
    {
        $jugadores = $pareja->participante->jugadores;

        $quien = [];
        if ($ver('participante.nombre')) {
            $quien[] = $pareja->participante->nombre;
        }
        if ($ver('jugadores') && $jugadores->isNotEmpty()) {
            $quien[] = $jugadores->map(fn (Jugador $j): string => $j->nombre_completo)->join(', ', ' y ');
        }

        $datos = [];
        if ($ver('niveles')) {
            $niveles = $jugadores->map(fn (Jugador $j): string => $j->nivel_declarado ?? '—')->join(' y ');
            $media = $pareja->participante->nivelMedioTexto();
            $datos[] = 'Nivel '.($niveles !== '' ? $niveles : '—').($media !== null && $jugadores->count() > 1 ? " ({$media})" : '');
        }
        if ($ver('estado')) {
            $datos[] = $pareja->estado->etiqueta().($jugadores->count() < 2 ? ' (falta compañero)' : '');
        }
        if ($ver('pago_marcado_en')) {
            $datos[] = $pareja->pago_marcado_en !== null ? 'Pagado' : 'Sin pagar';
        }

        // Con todo oculto, al menos el nombre de la pareja.
        $linea = implode(': ', $quien !== [] || $datos !== [] ? $quien : [$pareja->participante->nombre]);

        return implode(' · ', array_filter([$linea, ...$datos], fn (string $parte): bool => $parte !== ''));
    }
}
