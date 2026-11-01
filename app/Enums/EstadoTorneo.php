<?php

namespace App\Enums;

use App\Enums\Concerns\ConEtiqueta;

/**
 * El ciclo de vida de un torneo, en orden. `siguiente()` y `anterior()`
 * dan los vecinos; las comprobaciones de cada transición (no se publica
 * sin categorías, no se pasa a en juego sin horarios) las hace la acción
 * CambiarEstadoDelTorneo.
 */
enum EstadoTorneo: string
{
    use ConEtiqueta;

    case Borrador = 'borrador';
    case InscripcionAbierta = 'inscripcion_abierta';
    case InscripcionCerrada = 'inscripcion_cerrada';
    case Sorteo = 'sorteo';
    case EnJuego = 'en_juego';
    case Finalizado = 'finalizado';
    case Archivado = 'archivado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::InscripcionAbierta => 'Inscripción abierta',
            self::InscripcionCerrada => 'Inscripción cerrada',
            self::Sorteo => 'Sorteo',
            self::EnJuego => 'En juego',
            self::Finalizado => 'Finalizado',
            self::Archivado => 'Archivado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Borrador => 'gray',
            self::InscripcionAbierta => 'success',
            self::InscripcionCerrada, self::Sorteo => 'warning',
            self::EnJuego => 'info',
            self::Finalizado, self::Archivado => 'gray',
        };
    }

    public function siguiente(): ?self
    {
        $casos = self::cases();
        $indice = array_search($this, $casos, true);

        return $casos[$indice + 1] ?? null;
    }

    public function anterior(): ?self
    {
        $casos = self::cases();
        $indice = array_search($this, $casos, true);

        return $indice > 0 ? $casos[$indice - 1] : null;
    }

    /** Un torneo publicado se ve en la web del organizador. */
    public function publicado(): bool
    {
        return $this !== self::Borrador;
    }
}
