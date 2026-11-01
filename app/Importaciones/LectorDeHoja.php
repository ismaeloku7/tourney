<?php

namespace App\Importaciones;

use DateTimeInterface;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\CSV\Options as OpcionesCsv;
use OpenSpout\Reader\CSV\Reader as LectorCsv;
use OpenSpout\Reader\XLSX\Reader as LectorXlsx;
use RuntimeException;

/**
 * Lee la primera hoja de un Excel (o un CSV) que tenga las columnas que
 * se buscan, y devuelve sus filas como clave => valor. Las claves son las
 * cabeceras normalizadas: en minúsculas, sin tildes, sin lo que va entre
 * paréntesis y con los espacios juntos («Participante 1 (capitán)» →
 * «participante 1»).
 */
final class LectorDeHoja
{
    /**
     * @param  callable(list<string>): bool  $esLaHoja  si una hoja, por sus cabeceras normalizadas, es la que se busca
     * @return list<array<string, string>>
     */
    public static function leer(string $ruta, string $extension, callable $esLaHoja): array
    {
        $lector = self::lector($ruta, $extension);
        $lector->open($ruta);

        try {
            foreach ($lector->getSheetIterator() as $hoja) {
                $cabeceras = null;
                $filas = [];

                foreach ($hoja->getRowIterator() as $fila) {
                    $valores = self::valores($fila);

                    if ($cabeceras === null) {
                        $cabeceras = array_map(fn (string $c): string => self::normalizar($c), $valores);
                        if (! $esLaHoja($cabeceras)) {
                            continue 2; // no es la hoja que se busca (un resumen, por ejemplo)
                        }

                        continue;
                    }

                    if (implode('', $valores) === '') {
                        continue;
                    }

                    $registro = [];
                    foreach ($cabeceras as $i => $cabecera) {
                        if ($cabecera !== '') {
                            $registro[$cabecera] = $valores[$i] ?? '';
                        }
                    }
                    $filas[] = $registro;
                }

                if ($cabeceras !== null) {
                    return $filas;
                }
            }
        } finally {
            $lector->close();
        }

        throw new RuntimeException('El archivo no tiene ninguna hoja con las columnas que se esperan.');
    }

    public static function normalizar(string $texto): string
    {
        $texto = (string) preg_replace('/\(.*?\)/u', '', $texto);
        $texto = strtr(mb_strtolower(trim($texto)), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n', 'à' => 'a', 'è' => 'e', 'ò' => 'o', 'ï' => 'i', 'ç' => 'c']);

        return trim((string) preg_replace('/\s+/u', ' ', $texto));
    }

    private static function lector(string $ruta, string $extension): LectorXlsx|LectorCsv
    {
        if (! is_file($ruta)) {
            throw new RuntimeException('No se encuentra el archivo subido.');
        }

        return match (strtolower($extension)) {
            'xlsx' => new LectorXlsx,
            'csv', 'txt' => new LectorCsv(self::opcionesCsv($ruta)),
            default => throw new RuntimeException('Solo se importan archivos Excel (.xlsx) o CSV.'),
        };
    }

    /** El separador del CSV: punto y coma (Excel en español) o coma. */
    private static function opcionesCsv(string $ruta): OpcionesCsv
    {
        $opciones = new OpcionesCsv;
        $archivo = fopen($ruta, 'r');
        if ($archivo === false) {
            throw new RuntimeException('No se puede leer el archivo.');
        }
        $primera = (string) fgets($archivo);
        fclose($archivo);
        $opciones->FIELD_DELIMITER = substr_count($primera, ';') > substr_count($primera, ',') ? ';' : ',';

        return $opciones;
    }

    /** @return list<string> */
    private static function valores(?Row $fila): array
    {
        if ($fila === null) {
            return [];
        }

        return array_map(function (mixed $valor): string {
            if ($valor === null) {
                return '';
            }
            if (is_float($valor) && floor($valor) === $valor && abs($valor) < 1e15) {
                return (string) (int) $valor;
            }
            if ($valor instanceof DateTimeInterface) {
                return $valor->format('Y-m-d');
            }

            return trim((string) (is_scalar($valor) ? $valor : ''));
        }, $fila->toArray());
    }
}
