<?php

namespace App\Livewire\Concerns;

use App\Competicion\Datos\Resultado;
use App\Filament\Competicion\AccionesDePartido;

/**
 * El formulario de resultado del portal y de la mesa: un partido abierto a
 * la vez, tres filas de sets (juegos y tie-break) y, si se permite, un
 * resultado especial con su ganador.
 */
trait FormularioDeResultado
{
    public ?int $editando = null;

    /** @var list<array{juegos_local: string|int|null, juegos_visitante: string|int|null, tie_break_local: string|int|null, tie_break_visitante: string|int|null}> */
    public array $sets = [];

    public string $especial = '';

    public string $ganaLocal = '';

    public function abrirResultado(int $partido): void
    {
        $this->editando = $partido;
        $vacio = ['juegos_local' => null, 'juegos_visitante' => null, 'tie_break_local' => null, 'tie_break_visitante' => null];
        $this->sets = [$vacio, $vacio, $vacio];
        $this->especial = '';
        $this->ganaLocal = '';
        $this->resetErrorBag();
    }

    public function cerrarResultado(): void
    {
        $this->editando = null;
        $this->resetErrorBag();
    }

    protected function resultadoDelFormulario(): Resultado
    {
        // El último set de tres, si lleva puntos de más de 7 juegos, es un súper tie-break.
        $sets = array_map(function (array $set, int $i): array {
            $local = is_numeric($set['juegos_local'] ?? null) ? (int) $set['juegos_local'] : null;
            $visitante = is_numeric($set['juegos_visitante'] ?? null) ? (int) $set['juegos_visitante'] : null;

            return $set + ['es_super_tie_break' => $i === 2 && $local !== null && $visitante !== null && max($local, $visitante) >= 10];
        }, $this->sets, array_keys($this->sets));

        return AccionesDePartido::resultadoDesde([
            'especial' => $this->especial !== '' ? $this->especial : null,
            'gana_local' => $this->ganaLocal,
            'sets' => $sets,
        ]);
    }
}
