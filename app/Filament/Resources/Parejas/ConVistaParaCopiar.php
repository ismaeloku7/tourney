<?php

namespace App\Filament\Resources\Parejas;

use Livewire\Attributes\Session;

/** Guarda en la sesión si el listado está en la vista para copiar. */
trait ConVistaParaCopiar
{
    #[Session]
    public bool $paraCopiar = false;

    public function estaParaCopiar(): bool
    {
        return $this->paraCopiar;
    }

    public function alternarParaCopiar(): void
    {
        $this->paraCopiar = ! $this->paraCopiar;
    }
}
