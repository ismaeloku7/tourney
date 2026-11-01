<?php

namespace App\Competicion\Datos;

use App\Competicion\Errores\ConfiguracionInvalida;
use App\Enums\TipoDeOrigen;

/**
 * De dónde sale quien ocupa un hueco del cuadro: el ganador o el perdedor
 * de un partido, una posición de un grupo o un clasificado por orden.
 */
final readonly class Origen
{
    public function __construct(
        public TipoDeOrigen $tipo,
        public int $referencia,
        public ?int $posicion = null,
    ) {}

    public static function ganadorDe(int $partido): self
    {
        return new self(TipoDeOrigen::GanadorDe, $partido);
    }

    public static function perdedorDe(int $partido): self
    {
        return new self(TipoDeOrigen::PerdedorDe, $partido);
    }

    public static function posicionDeGrupo(int $grupo, int $posicion): self
    {
        return new self(TipoDeOrigen::PosicionDeGrupo, $grupo, $posicion);
    }

    public static function clasificado(int $orden): self
    {
        return new self(TipoDeOrigen::Clasificado, $orden);
    }

    /** «Ganador P3», «Perdedor P3», «1º grupo A» (con el nombre que se le pase) o «Clasificado 5». */
    public function etiqueta(?string $nombreDeGrupo = null): string
    {
        return match ($this->tipo) {
            TipoDeOrigen::GanadorDe => 'Ganador P'.$this->referencia,
            TipoDeOrigen::PerdedorDe => 'Perdedor P'.$this->referencia,
            TipoDeOrigen::PosicionDeGrupo => $this->posicion.'º grupo '.($nombreDeGrupo ?? $this->referencia),
            TipoDeOrigen::Clasificado => 'Clasificado '.$this->referencia,
        };
    }

    public function apuntaA(int $partido): bool
    {
        return ($this->tipo === TipoDeOrigen::GanadorDe || $this->tipo === TipoDeOrigen::PerdedorDe) && $this->referencia === $partido;
    }

    /**
     * @param  array<string, mixed>  $datos
     *
     * @throws ConfiguracionInvalida
     */
    public static function desdeArray(array $datos): self
    {
        $tipo = TipoDeOrigen::tryFrom((string) ($datos['tipo'] ?? ''));

        if ($tipo === null || ! isset($datos['referencia'])) {
            throw new ConfiguracionInvalida(['Un origen tiene tipo y referencia.']);
        }

        return new self($tipo, (int) $datos['referencia'], isset($datos['posicion']) ? (int) $datos['posicion'] : null);
    }

    /** @return array{tipo: string, referencia: int, posicion: int|null} */
    public function toArray(): array
    {
        return ['tipo' => $this->tipo->value, 'referencia' => $this->referencia, 'posicion' => $this->posicion];
    }
}
