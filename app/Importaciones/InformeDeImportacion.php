<?php

namespace App\Importaciones;

/** Lo que ha hecho una importación, para enseñarlo al terminar. */
final class InformeDeImportacion
{
    public int $parejas = 0;

    public int $sinCompanero = 0;

    public int $yaEstaban = 0;

    public int $jugadoresNuevos = 0;

    public int $jugadoresExistentes = 0;

    /** @var list<string> */
    public array $categoriasNuevas = [];

    /** @var list<string> */
    public array $avisos = [];

    /** @var list<string> */
    public array $errores = [];

    public function resumen(): string
    {
        $partes = [
            "{$this->parejas} parejas completas",
            "{$this->sinCompanero} a falta de compañero",
            "{$this->jugadoresNuevos} jugadores nuevos",
        ];
        if ($this->jugadoresExistentes > 0) {
            $partes[] = "{$this->jugadoresExistentes} ya estaban en la base";
        }
        if ($this->yaEstaban > 0) {
            $partes[] = "{$this->yaEstaban} parejas que ya estaban, sin tocar";
        }
        if ($this->categoriasNuevas !== []) {
            $partes[] = 'categorías creadas: '.implode(', ', $this->categoriasNuevas);
        }

        return implode(' · ', $partes).'.';
    }
}
