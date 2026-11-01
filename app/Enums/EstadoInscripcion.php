<?php

namespace App\Enums;

enum EstadoInscripcion: string
{
    case Pendiente = 'pendiente';
    case Confirmada = 'confirmada';
    case ListaDeEspera = 'lista_de_espera';
    case Baja = 'baja';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Confirmada => 'Confirmada',
            self::ListaDeEspera => 'En lista de espera',
            self::Baja => 'Baja',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pendiente => 'warning',
            self::Confirmada => 'success',
            self::ListaDeEspera => 'info',
            self::Baja => 'gray',
        };
    }
}
