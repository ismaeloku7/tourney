<?php

namespace App\Enums;

/**
 * Los criterios, en el orden que fije la fase. Enfrentamiento directo
 * solo decide entre dos; con tres o más se calcula la mini-liga entre
 * las empatadas antes de pasar al siguiente criterio.
 */
enum CriterioDeDesempate: string
{
    case Puntos = 'puntos';
    case EnfrentamientoDirecto = 'enfrentamiento_directo';
    case DiferenciaDeSets = 'diferencia_de_sets';
    case SetsGanados = 'sets_ganados';
    case DiferenciaDeJuegos = 'diferencia_de_juegos';
    case JuegosGanados = 'juegos_ganados';
    case Sorteo = 'sorteo';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Puntos => 'Puntos',
            self::EnfrentamientoDirecto => 'Enfrentamiento directo',
            self::DiferenciaDeSets => 'Diferencia de sets',
            self::SetsGanados => 'Sets ganados',
            self::DiferenciaDeJuegos => 'Diferencia de juegos',
            self::JuegosGanados => 'Juegos ganados',
            self::Sorteo => 'Sorteo',
        };
    }

    /**
     * El orden por defecto del briefing.
     *
     * @return list<self>
     */
    public static function porDefecto(): array
    {
        return [self::Puntos, self::EnfrentamientoDirecto, self::DiferenciaDeSets, self::SetsGanados, self::DiferenciaDeJuegos, self::JuegosGanados, self::Sorteo];
    }
}
