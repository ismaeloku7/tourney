<?php

namespace App\Filament\Resources\Parejas;

/**
 * Una página con un listado de parejas que tiene la vista para copiar
 * (ParaCopiar). La pone el trait ConVistaParaCopiar.
 */
interface SeCopiaParaPegar
{
    public function estaParaCopiar(): bool;

    public function alternarParaCopiar(): void;
}
