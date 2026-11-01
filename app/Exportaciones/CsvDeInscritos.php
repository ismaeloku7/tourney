<?php

namespace App\Exportaciones;

use App\Models\Inscripcion;
use App\Models\Jugador;
use App\Models\Torneo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Los inscritos de un torneo en CSV (punto y coma, UTF-8 con BOM para que
 * Excel lo abra bien): una fila por pareja con sus dos jugadores y el
 * estado de la inscripción y del pago.
 */
final class CsvDeInscritos
{
    public function generar(Torneo $torneo): string
    {
        $inscripciones = Inscripcion::query()
            ->whereHas('categoria', fn (Builder $q) => $q->where('torneo_id', $torneo->id))
            ->with(['categoria', 'participante.jugadores'])
            ->get()
            ->sortBy(fn (Inscripcion $i): string => str_pad((string) $i->categoria->orden, 4, '0', STR_PAD_LEFT).$i->participante->nombre)
            ->values();

        $filas = [['Categoría', 'Pareja', 'Jugador 1', 'Correo 1', 'Teléfono 1', 'Jugador 2', 'Correo 2', 'Teléfono 2', 'Estado', 'Puesto en espera', 'Pagado', 'Notas del pago', 'Origen', 'Inscrita el', 'Notas']];

        foreach ($inscripciones as $inscripcion) {
            [$a, $b] = [$inscripcion->participante->jugadores->get(0), $inscripcion->participante->jugadores->get(1)];
            $filas[] = [
                $inscripcion->categoria->nombre,
                $inscripcion->participante->nombre,
                ...self::jugador($a),
                ...self::jugador($b),
                $inscripcion->estado->etiqueta(),
                $inscripcion->posicion_espera !== null ? (string) $inscripcion->posicion_espera : '',
                $inscripcion->pago_marcado_en !== null ? 'Sí' : 'No',
                (string) $inscripcion->pago_notas,
                $inscripcion->origen->etiqueta(),
                $inscripcion->created_at?->format('d/m/Y H:i') ?? '',
                (string) $inscripcion->notas,
            ];
        }

        $salida = fopen('php://temp', 'r+');
        assert($salida !== false);
        fwrite($salida, "\u{FEFF}");
        foreach ($filas as $fila) {
            fputcsv($salida, $fila, ';', '"', '\\', "\r\n");
        }
        rewind($salida);
        $csv = (string) stream_get_contents($salida);
        fclose($salida);

        return $csv;
    }

    public function nombreDeArchivo(Torneo $torneo): string
    {
        return Str::slug($torneo->nombre.' inscritos').'.csv';
    }

    /** @return list<string> */
    private static function jugador(?Jugador $jugador): array
    {
        if ($jugador === null) {
            return ['', '', ''];
        }

        return [$jugador->nombre_completo, (string) $jugador->getAttribute('email'), (string) $jugador->getAttribute('telefono')];
    }
}
