<?php

namespace App\Copias;

use App\Models\Organizador;
use App\Tenancy\OrganizadorActual;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Copias de seguridad en un zip: la base (un volcado .sql portable y, si
 * es un archivo SQLite, también el archivo tal cual) y los archivos
 * subidos. Una carpeta por organizador y otra para la base central, en
 * el disco local (`copias/…`), conservando las últimas 30 de cada una.
 */
final class CopiaDeSeguridad
{
    public const CONSERVAR = 30;

    public function __construct(private readonly VolcadoDeBase $volcado = new VolcadoDeBase) {}

    /** Hace la copia de un organizador (su base y sus archivos) y devuelve su ruta en el disco. */
    public function deOrganizador(Organizador $organizador): string
    {
        return app(OrganizadorActual::class)->ejecutarComo($organizador, function (Organizador $organizador): string {
            $carpeta = self::carpeta($organizador);
            [$zip, $nombre] = $this->abrir($carpeta);
            $temporal = $this->base($zip, 'tenant');

            $disco = Storage::disk('local');
            foreach ($disco->allFiles('organizadores/'.$organizador->id) as $archivo) {
                $zip->addFile($disco->path($archivo), 'archivos/'.substr($archivo, strlen('organizadores/'.$organizador->id.'/')));
            }

            $zip->close();
            @unlink($temporal);
            $this->purgar($carpeta);

            return $carpeta.'/'.$nombre;
        });
    }

    /** La base central: organizadores, planes y usuarios de la plataforma. */
    public function deLaCentral(): string
    {
        $carpeta = self::carpeta(null);
        [$zip, $nombre] = $this->abrir($carpeta);
        $temporal = $this->base($zip, (string) config('tenancy.database.central_connection'));
        $zip->close();
        @unlink($temporal);
        $this->purgar($carpeta);

        return $carpeta.'/'.$nombre;
    }

    /**
     * Las copias de una carpeta, la más reciente primero.
     *
     * @return list<array{archivo: string, nombre: string, fecha: Carbon, tamano: int}>
     */
    public static function listar(?Organizador $organizador): array
    {
        $disco = Storage::disk('local');
        $copias = [];

        foreach ($disco->files(self::carpeta($organizador)) as $archivo) {
            if (! str_ends_with($archivo, '.zip')) {
                continue;
            }
            $copias[] = ['archivo' => $archivo, 'nombre' => basename($archivo), 'fecha' => Carbon::createFromTimestamp($disco->lastModified($archivo)), 'tamano' => $disco->size($archivo)];
        }

        usort($copias, fn (array $a, array $b): int => strcmp($b['nombre'], $a['nombre']));

        return $copias;
    }

    public static function carpeta(?Organizador $organizador): string
    {
        return 'copias/'.($organizador === null ? 'central' : $organizador->id);
    }

    /** @return array{0: ZipArchive, 1: string} El zip abierto y su nombre de archivo. */
    private function abrir(string $carpeta): array
    {
        $disco = Storage::disk('local');
        $disco->makeDirectory($carpeta);
        $nombre = 'copia-'.now()->format('Y-m-d-His').'.zip';

        $zip = new ZipArchive;
        if ($zip->open($disco->path($carpeta.'/'.$nombre), ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se ha podido crear el archivo de la copia.');
        }

        return [$zip, $nombre];
    }

    /**
     * El volcado .sql de la conexión y, si es un archivo SQLite, el archivo
     * entero. Devuelve el temporal del volcado: ZipArchive lo lee al cerrar,
     * así que se borra después.
     */
    private function base(ZipArchive $zip, string $conexion): string
    {
        $temporal = tempnam(sys_get_temp_dir(), 'copia');
        if ($temporal === false) {
            throw new RuntimeException('No hay sitio para el volcado temporal.');
        }

        $this->volcado->aArchivo($conexion, $temporal);
        $zip->addFile($temporal, 'base.sql');

        $db = DB::connection($conexion);
        $ruta = (string) $db->getDatabaseName();
        if ($db->getDriverName() === 'sqlite' && $ruta !== ':memory:' && is_file($ruta)) {
            $zip->addFile($ruta, 'base.sqlite');
        }

        return $temporal;
    }

    /** Se quedan las más recientes; las demás se borran. */
    private function purgar(string $carpeta): void
    {
        foreach (array_slice(self::listarCarpeta($carpeta), self::CONSERVAR) as $copia) {
            Storage::disk('local')->delete($copia);
        }
    }

    /** @return list<string> */
    private static function listarCarpeta(string $carpeta): array
    {
        $archivos = array_values(array_filter(Storage::disk('local')->files($carpeta), fn (string $a): bool => str_ends_with($a, '.zip')));
        rsort($archivos);

        return $archivos;
    }
}
