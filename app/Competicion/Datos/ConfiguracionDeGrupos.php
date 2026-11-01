<?php

namespace App\Competicion\Datos;

use App\Competicion\Errores\ConfiguracionInvalida;
use App\Enums\CriterioDeDesempate;
use App\Enums\ModoDeSorteo;

/**
 * Lo que decide el organizador para una fase de grupos. Es lo que se
 * guarda en `fases.configuracion` y lo que rellenará el formulario de
 * Filament.
 */
final readonly class ConfiguracionDeGrupos
{
    /**
     * @param  list<CriterioDeDesempate>  $criteriosDeDesempate
     * @param  list<list<int>>|null  $gruposManuales  Ids de participante por grupo, en modo manual.
     */
    public function __construct(
        public ?int $numeroDeGrupos = null,
        public ?int $tamanoDeGrupo = 4,
        public ModoDeSorteo $modoDeSorteo = ModoDeSorteo::Aleatorio,
        public ?int $semilla = null,
        public ?int $partidosPorParticipante = null,
        public bool $idaYVuelta = false,
        public Puntuacion $puntuacion = new Puntuacion,
        public array $criteriosDeDesempate = [CriterioDeDesempate::Puntos, CriterioDeDesempate::EnfrentamientoDirecto, CriterioDeDesempate::DiferenciaDeSets, CriterioDeDesempate::SetsGanados, CriterioDeDesempate::DiferenciaDeJuegos, CriterioDeDesempate::JuegosGanados, CriterioDeDesempate::Sorteo],
        public ReglaDeClasificados $clasificados = new ReglaDeClasificados,
        public ?array $gruposManuales = null,
    ) {}

    /**
     * @param  array<string, mixed>  $datos
     *
     * @throws ConfiguracionInvalida
     */
    public static function desdeArray(array $datos): self
    {
        $errores = [];
        $numero = isset($datos['numero_de_grupos']) ? (int) $datos['numero_de_grupos'] : null;
        $tamano = isset($datos['tamano_de_grupo']) ? (int) $datos['tamano_de_grupo'] : null;
        $modo = ModoDeSorteo::tryFrom((string) ($datos['modo_de_sorteo'] ?? 'aleatorio'));
        $semilla = isset($datos['semilla']) ? (int) $datos['semilla'] : null;
        $partidos = isset($datos['partidos_por_participante']) ? (int) $datos['partidos_por_participante'] : null;
        $idaYVuelta = (bool) ($datos['ida_y_vuelta'] ?? false);
        $manuales = $datos['grupos_manuales'] ?? null;

        if ($modo === null) {
            $errores[] = 'El modo de sorteo no existe.';
        }
        if ($modo !== ModoDeSorteo::Manual && $numero === null && $tamano === null) {
            $errores[] = 'Hay que fijar el número de grupos o el tamaño de grupo.';
        }
        if ($numero !== null && $tamano !== null) {
            $errores[] = 'Número de grupos y tamaño de grupo son alternativos: fije solo uno.';
        }
        if ($numero !== null && $numero < 1) {
            $errores[] = 'Hace falta al menos un grupo.';
        }
        if ($tamano !== null && $tamano < 2) {
            $errores[] = 'Un grupo tiene al menos 2 participantes.';
        }
        if ($partidos !== null && $partidos < 1) {
            $errores[] = 'Los partidos por participante tienen que ser al menos 1.';
        }
        if ($modo === ModoDeSorteo::Manual && ! is_array($manuales)) {
            $errores[] = 'En modo manual hay que indicar los grupos.';
        }
        if ($manuales !== null && ! is_array($manuales)) {
            $errores[] = 'Los grupos manuales tienen que ser una lista de listas de participantes.';
            $manuales = null;
        }

        $criterios = [];
        foreach ((array) ($datos['criterios_de_desempate'] ?? array_map(fn (CriterioDeDesempate $c): string => $c->value, CriterioDeDesempate::porDefecto())) as $valor) {
            $criterio = CriterioDeDesempate::tryFrom((string) $valor);
            if ($criterio === null) {
                $errores[] = "El criterio de desempate «{$valor}» no existe.";
            } elseif (in_array($criterio, $criterios, true)) {
                $errores[] = 'Un criterio de desempate no se repite.';
            } else {
                $criterios[] = $criterio;
            }
        }
        if ($criterios === []) {
            $errores[] = 'Hace falta al menos un criterio de desempate.';
        }

        try {
            $puntuacion = Puntuacion::desdeArray((array) ($datos['puntuacion'] ?? []));
        } catch (ConfiguracionInvalida $e) {
            $puntuacion = new Puntuacion;
            $errores = [...$errores, ...$e->errores];
        }

        try {
            $clasificados = ReglaDeClasificados::desdeArray((array) ($datos['clasificados'] ?? []));
        } catch (ConfiguracionInvalida $e) {
            $clasificados = new ReglaDeClasificados;
            $errores = [...$errores, ...$e->errores];
        }

        if ($errores !== []) {
            throw new ConfiguracionInvalida($errores);
        }

        $gruposManuales = $manuales === null ? null : array_values(array_map(
            fn (mixed $grupo): array => array_values(array_map(fn (mixed $id): int => (int) $id, (array) $grupo)),
            $manuales,
        ));

        return new self($numero, $tamano, $modo ?? ModoDeSorteo::Aleatorio, $semilla, $partidos, $idaYVuelta, $puntuacion, $criterios, $clasificados, $gruposManuales);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'numero_de_grupos' => $this->numeroDeGrupos,
            'tamano_de_grupo' => $this->tamanoDeGrupo,
            'modo_de_sorteo' => $this->modoDeSorteo->value,
            'semilla' => $this->semilla,
            'partidos_por_participante' => $this->partidosPorParticipante,
            'ida_y_vuelta' => $this->idaYVuelta,
            'puntuacion' => $this->puntuacion->toArray(),
            'criterios_de_desempate' => array_map(fn (CriterioDeDesempate $c): string => $c->value, $this->criteriosDeDesempate),
            'clasificados' => $this->clasificados->toArray(),
            'grupos_manuales' => $this->gruposManuales,
        ];
    }

    /** Una copia con la semilla fijada (para persistir el sorteo). */
    public function conSemilla(int $semilla): self
    {
        return new self($this->numeroDeGrupos, $this->tamanoDeGrupo, $this->modoDeSorteo, $semilla, $this->partidosPorParticipante, $this->idaYVuelta, $this->puntuacion, $this->criteriosDeDesempate, $this->clasificados, $this->gruposManuales);
    }
}
