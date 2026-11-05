<?php

namespace App\Copias;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Vuelca una base entera (MySQL o SQLite) a un archivo .sql con las
 * tablas y sus filas, sin depender de `mysqldump` ni de `sqlite3`. Se
 * restaura con `mysql base < archivo.sql` o `sqlite3 base < archivo.sql`.
 */
final class VolcadoDeBase
{
    /** Escribe el volcado de la conexión en `$ruta` y devuelve cuántas tablas lleva. */
    public function aArchivo(string $conexion, string $ruta): int
    {
        $db = DB::connection($conexion);
        $archivo = fopen($ruta, 'w');
        if ($archivo === false) {
            throw new RuntimeException("No se puede escribir en {$ruta}.");
        }

        $esSqlite = $db->getDriverName() === 'sqlite';
        fwrite($archivo, '-- Copia de '.$db->getDatabaseName().' · '.now()->toDateTimeString()."\n");
        fwrite($archivo, $esSqlite ? "PRAGMA foreign_keys=OFF;\nBEGIN TRANSACTION;\n" : "SET FOREIGN_KEY_CHECKS=0;\nSET NAMES utf8mb4;\nSTART TRANSACTION;\n");

        $tablas = 0;
        foreach ($this->tablas($db) as [$tabla, $creacion]) {
            fwrite($archivo, "\nDROP TABLE IF EXISTS ".$this->nombre($tabla, $esSqlite).";\n{$creacion};\n");
            $this->filas($db, $tabla, $esSqlite, $archivo);
            $tablas++;
        }

        fwrite($archivo, $esSqlite ? "COMMIT;\n" : "COMMIT;\nSET FOREIGN_KEY_CHECKS=1;\n");
        fclose($archivo);

        return $tablas;
    }

    /**
     * Cada tabla con su sentencia de creación.
     *
     * @return list<array{0: string, 1: string}>
     */
    private function tablas(Connection $db): array
    {
        if ($db->getDriverName() === 'sqlite') {
            return array_map(
                fn (object $fila): array => [(string) $fila->name, (string) $fila->sql],
                $db->select("SELECT name, sql FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name"),
            );
        }

        $tablas = [];
        foreach ($db->select('SHOW TABLES') as $fila) {
            $nombre = (string) current((array) $fila);
            $creacion = (array) $db->selectOne('SHOW CREATE TABLE `'.str_replace('`', '``', $nombre).'`');
            $tablas[] = [$nombre, (string) ($creacion['Create Table'] ?? '')];
        }

        return $tablas;
    }

    /** @param  resource  $archivo */
    private function filas(Connection $db, string $tabla, bool $esSqlite, $archivo): void
    {
        $pdo = $db->getPdo();
        $lote = [];

        foreach ($db->table($tabla)->cursor() as $fila) {
            $valores = array_map(fn (mixed $v): string => match (true) {
                $v === null => 'NULL',
                is_int($v) || is_float($v) => (string) $v,
                is_bool($v) => $v ? '1' : '0',
                default => (string) $pdo->quote((string) $v),
            }, (array) $fila);
            $lote[] = '('.implode(', ', $valores).')';

            if (count($lote) === 200) {
                $this->escribirLote($archivo, $tabla, $esSqlite, array_keys((array) $fila), $lote);
                $lote = [];
            }
        }

        if ($lote !== []) {
            $primera = $db->table($tabla)->first();
            $this->escribirLote($archivo, $tabla, $esSqlite, array_keys((array) $primera), $lote);
        }
    }

    /**
     * @param  resource  $archivo
     * @param  list<string>  $columnas
     * @param  list<string>  $lote
     */
    private function escribirLote($archivo, string $tabla, bool $esSqlite, array $columnas, array $lote): void
    {
        $lista = implode(', ', array_map(fn (string $c): string => $this->nombre($c, $esSqlite), $columnas));
        fwrite($archivo, 'INSERT INTO '.$this->nombre($tabla, $esSqlite)." ({$lista}) VALUES\n".implode(",\n", $lote).";\n");
    }

    private function nombre(string $identificador, bool $esSqlite): string
    {
        return $esSqlite ? '"'.str_replace('"', '""', $identificador).'"' : '`'.str_replace('`', '``', $identificador).'`';
    }
}
