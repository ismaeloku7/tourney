<?php

namespace App\Competicion\Datos;

use DateTimeImmutable;

/**
 * Un plan de horarios calculado y todavía sin guardar: el plan, los
 * partidos que se iban a (re)colocar y una huella del torneo en ese
 * momento, para no aplicarlo si algo ha cambiado entre medias. Se guarda
 * como array en la página mientras se mira.
 */
final readonly class PropuestaDeHorarios
{
    /** @param  list<int>  $partidos */
    public function __construct(
        public PlanDePistas $plan,
        public array $partidos,
        public string $huella,
        public bool $rehacer = false,
    ) {}

    /** @return array{colocados: list<array{0: int, 1: int, 2: int, 3: int}>, conflictos: list<array{0: int, 1: string, 2: string}>, fin: int|null, muertos: int, ocupacion: int, partidos: list<int>, huella: string, rehacer: bool} */
    public function toArray(): array
    {
        return [
            'colocados' => array_map(fn (PartidoColocado $c): array => [$c->partido, $c->pista, $c->inicio->getTimestamp(), $c->fin->getTimestamp()], $this->plan->colocados),
            'conflictos' => array_map(fn (Conflicto $c): array => [$c->partido, $c->tipo, $c->detalle], $this->plan->conflictos),
            'fin' => $this->plan->fin?->getTimestamp(),
            'muertos' => $this->plan->minutosMuertos,
            'ocupacion' => $this->plan->ocupacion,
            'partidos' => $this->partidos,
            'huella' => $this->huella,
            'rehacer' => $this->rehacer,
        ];
    }

    /** @param  array<string, mixed>  $datos */
    public static function desdeArray(array $datos): self
    {
        $momento = fn (mixed $ts): DateTimeImmutable => (new DateTimeImmutable)->setTimestamp((int) $ts);

        $colocados = [];
        foreach ((array) ($datos['colocados'] ?? []) as $c) {
            $c = (array) $c;
            $colocados[] = new PartidoColocado((int) ($c[0] ?? 0), (int) ($c[1] ?? 0), $momento($c[2] ?? 0), $momento($c[3] ?? 0));
        }

        $conflictos = [];
        foreach ((array) ($datos['conflictos'] ?? []) as $c) {
            $c = (array) $c;
            $conflictos[] = new Conflicto((int) ($c[0] ?? 0), (string) ($c[1] ?? ''), (string) ($c[2] ?? ''));
        }

        return new self(
            new PlanDePistas($colocados, $conflictos, isset($datos['fin']) ? $momento($datos['fin']) : null, (int) ($datos['muertos'] ?? 0), (int) ($datos['ocupacion'] ?? 0)),
            array_values(array_map(fn (mixed $id): int => (int) $id, (array) ($datos['partidos'] ?? []))),
            (string) ($datos['huella'] ?? ''),
            (bool) ($datos['rehacer'] ?? false),
        );
    }
}
