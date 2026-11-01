<?php

namespace App\Competicion\Datos;

use App\Competicion\Errores\ConfiguracionInvalida;
use App\Enums\OrigenDeConsolacion;
use App\Enums\ReglaDeCruces;

/**
 * Lo que decide el organizador para un cuadro: tamaño (o el mínimo que
 * cabe), regla de cruces, reencuentros, tercer puesto, consolación y
 * formato propio por ronda.
 */
final readonly class ConfiguracionDeEliminatoria
{
    /**
     * @param  array<int, int>  $formatosPorRonda  Ronda → id de formato de partido.
     * @param  list<array{0: int|null, 1: int|null}>|null  $crucesManuales  Pares de ids (o nulo = bye) de la primera ronda, en orden.
     */
    public function __construct(
        public ?int $tamanoDeCuadro = null,
        public ReglaDeCruces $reglaDeCruces = ReglaDeCruces::EntreGrupos,
        public bool $evitarReencuentros = true,
        public bool $tercerYCuartoPuesto = false,
        public OrigenDeConsolacion $consolacion = OrigenDeConsolacion::Ninguna,
        public array $formatosPorRonda = [],
        public ?array $crucesManuales = null,
        public ?int $semilla = null,
    ) {}

    /**
     * @param  array<string, mixed>  $datos
     *
     * @throws ConfiguracionInvalida
     */
    public static function desdeArray(array $datos): self
    {
        $errores = [];
        $tamano = isset($datos['tamano_de_cuadro']) ? (int) $datos['tamano_de_cuadro'] : null;
        $regla = ReglaDeCruces::tryFrom((string) ($datos['regla_de_cruces'] ?? 'entre_grupos'));
        $consolacion = OrigenDeConsolacion::tryFrom((string) ($datos['consolacion'] ?? 'ninguna'));
        $cruces = $datos['cruces_manuales'] ?? null;
        $formatos = [];

        if ($tamano !== null && ($tamano < 2 || ($tamano & ($tamano - 1)) !== 0)) {
            $errores[] = 'El tamaño del cuadro tiene que ser una potencia de 2: 2, 4, 8, 16, 32…';
        }
        if ($regla === null) {
            $errores[] = 'La regla de cruces no existe.';
        }
        if ($consolacion === null) {
            $errores[] = 'El origen de la consolación no existe.';
        }
        if ($regla === ReglaDeCruces::Manual && ! is_array($cruces)) {
            $errores[] = 'Con cruces manuales hay que indicar los cruces de la primera ronda.';
        }
        foreach ((array) ($datos['formatos_por_ronda'] ?? []) as $ronda => $formato) {
            if (! is_numeric($ronda) || (int) $ronda < 1 || ! is_numeric($formato)) {
                $errores[] = 'Los formatos por ronda se indican como ronda → formato.';
            } else {
                $formatos[(int) $ronda] = (int) $formato;
            }
        }

        if ($errores !== []) {
            throw new ConfiguracionInvalida($errores);
        }

        $crucesManuales = ! is_array($cruces) ? null : array_values(array_map(
            fn (mixed $par): array => [isset($par[0]) ? (int) $par[0] : null, isset($par[1]) ? (int) $par[1] : null],
            $cruces,
        ));

        return new self(
            $tamano,
            $regla ?? ReglaDeCruces::EntreGrupos,
            (bool) ($datos['evitar_reencuentros'] ?? true),
            (bool) ($datos['tercer_y_cuarto_puesto'] ?? false),
            $consolacion ?? OrigenDeConsolacion::Ninguna,
            $formatos,
            $crucesManuales,
            isset($datos['semilla']) ? (int) $datos['semilla'] : null,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'tamano_de_cuadro' => $this->tamanoDeCuadro,
            'regla_de_cruces' => $this->reglaDeCruces->value,
            'evitar_reencuentros' => $this->evitarReencuentros,
            'tercer_y_cuarto_puesto' => $this->tercerYCuartoPuesto,
            'consolacion' => $this->consolacion->value,
            'formatos_por_ronda' => $this->formatosPorRonda,
            'cruces_manuales' => $this->crucesManuales,
            'semilla' => $this->semilla,
        ];
    }

    public function conSemilla(int $semilla): self
    {
        return new self($this->tamanoDeCuadro, $this->reglaDeCruces, $this->evitarReencuentros, $this->tercerYCuartoPuesto, $this->consolacion, $this->formatosPorRonda, $this->crucesManuales, $semilla);
    }
}
