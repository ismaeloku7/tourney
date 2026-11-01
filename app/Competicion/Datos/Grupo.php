<?php

namespace App\Competicion\Datos;

/** Un grupo con sus participantes en orden de sorteo. `id` es el de la base, si ya existe. */
final readonly class Grupo
{
    /** @param  list<int>  $participantes */
    public function __construct(
        public string $nombre,
        public int $orden,
        public array $participantes,
        public ?int $id = null,
    ) {}

    public function tamano(): int
    {
        return count($this->participantes);
    }

    /** La referencia que llevan sus partidos: el id de la base o, si aún no hay, el orden. */
    public function referencia(): int
    {
        return $this->id ?? $this->orden;
    }

    /** La letra del grupo a partir de su orden: 1 → A, 26 → Z, 27 → AA. */
    public static function nombrePorOrden(int $orden): string
    {
        $nombre = '';
        $n = $orden;

        while ($n > 0) {
            $n--;
            $nombre = chr(65 + ($n % 26)).$nombre;
            $n = intdiv($n, 26);
        }

        return $nombre;
    }
}
