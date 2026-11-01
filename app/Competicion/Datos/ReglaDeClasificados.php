<?php

namespace App\Competicion\Datos;

use App\Competicion\Errores\ConfiguracionInvalida;

/**
 * Quién pasa de la fase de grupos: los `porGrupo` primeros de cada grupo
 * y, además, los `cuantosMejores` mejores de la posición
 * `mejoresDePosicion` (los mejores terceros, por ejemplo).
 */
final readonly class ReglaDeClasificados
{
    public function __construct(
        public int $porGrupo = 2,
        public ?int $mejoresDePosicion = null,
        public int $cuantosMejores = 0,
    ) {}

    /**
     * @param  array<string, mixed>  $datos
     *
     * @throws ConfiguracionInvalida
     */
    public static function desdeArray(array $datos): self
    {
        $errores = [];
        $porGrupo = (int) ($datos['por_grupo'] ?? 2);
        $posicion = isset($datos['mejores_de_posicion']) ? (int) $datos['mejores_de_posicion'] : null;
        $cuantos = (int) ($datos['cuantos_mejores'] ?? 0);

        if ($porGrupo < 0) {
            $errores[] = 'Los clasificados por grupo no pueden ser negativos.';
        }
        if ($cuantos < 0) {
            $errores[] = 'Los mejores de una posición no pueden ser negativos.';
        }
        if ($cuantos > 0 && $posicion === null) {
            $errores[] = 'Para añadir mejores de una posición hay que decir de cuál.';
        }
        if ($posicion !== null && $posicion <= $porGrupo) {
            $errores[] = 'La posición de los «mejores» tiene que ir después de los clasificados por grupo.';
        }
        if ($porGrupo === 0 && $cuantos === 0) {
            $errores[] = 'Alguien tiene que clasificarse.';
        }

        if ($errores !== []) {
            throw new ConfiguracionInvalida($errores);
        }

        return new self($porGrupo, $cuantos > 0 ? $posicion : null, $cuantos);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['por_grupo' => $this->porGrupo, 'mejores_de_posicion' => $this->mejoresDePosicion, 'cuantos_mejores' => $this->cuantosMejores];
    }
}
