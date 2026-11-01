<?php

namespace App\Competicion\Datos;

/** Un partido que el planificador no ha podido colocar, y el motivo más cercano. */
final readonly class Conflicto
{
    public const SIN_FRANJA = 'sin_franja';

    public const JUGADOR_SOLAPADO = 'jugador_solapado';

    public const SIN_DESCANSO = 'sin_descanso';

    public const JUGADOR_NO_DISPONIBLE = 'jugador_no_disponible';

    public const ORDEN_DE_RONDAS = 'orden_de_rondas';

    public function __construct(
        public int $partido,
        public string $tipo,
        public string $detalle,
    ) {}
}
