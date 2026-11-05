<?php

namespace App\Notifications;

use App\Actions\Competicion\AnotarMarcador;
use App\Filament\Competicion\Marcador;
use App\Models\Partido;

/**
 * A la pareja que juega después en una pista: que el partido de delante
 * acaba de empezar, que va por el último set, o que ya les toca.
 */
class SiguienteEnPista extends AvisoAlJugador
{
    public function __construct(public Partido $partido, public Partido $anterior, public string $motivo)
    {
        parent::__construct();
    }

    public function titulo(): string
    {
        $pista = $this->partido->pista?->nombre ?? 'vuestra pista';

        return match ($this->motivo) {
            'empieza' => "Sois los siguientes en {$pista}",
            'ultimo_set' => "Id preparándoos: {$pista} se libera pronto",
            default => "Os toca: {$pista}",
        };
    }

    public function texto(): string
    {
        $contrario = Marcador::lado($this->anterior, true).' – '.Marcador::lado($this->anterior, false);
        $marcador = AnotarMarcador::texto($this->anterior);

        return match ($this->motivo) {
            'empieza' => "Acaba de empezar {$contrario}. Vuestro partido: ".$this->rivales().'.',
            'ultimo_set' => "{$contrario} va ".($marcador !== '' ? $marcador : 'por el último set').'. Calentad.',
            default => 'Ha acabado '.$contrario.'. A la pista: '.$this->rivales().'.',
        };
    }

    public function url(): string
    {
        return '/jugador/partidos';
    }

    public function lineas(): array
    {
        return $this->partido->inicio_estimado !== null ? ['Hora estimada: '.$this->partido->inicio_estimado->format('H:i').'.'] : [];
    }

    public function etiqueta(): string
    {
        return 'siguiente-'.$this->partido->id.'-'.$this->motivo;
    }

    private function rivales(): string
    {
        return Marcador::lado($this->partido, true).' – '.Marcador::lado($this->partido, false);
    }
}
