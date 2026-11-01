<?php

use App\Enums\EstadoFase;
use App\Enums\EstadoPartido;
use App\Models\Fase;
use App\Models\Organizador;
use App\Models\Partido;
use App\Models\Torneo;

test('el seeder deja un torneo en cada estado y se puede repetir', function () {
    $this->seed();
    $this->seed();

    comoOrganizador(Organizador::query()->findOrFail('demo'), function (): void {
        expect(Torneo::query()->pluck('slug')->all())->toEqualCanonicalizing(['open-demo-sorteo', 'open-demo-en-juego', 'open-demo-finalizado']);

        $sorteo = Torneo::query()->where('slug', 'open-demo-sorteo')->sole();
        expect($sorteo->categorias)->toHaveCount(2);
        foreach ($sorteo->categorias as $categoria) {
            $grupos = $categoria->fases()->where('tipo', 'grupos')->sole();
            expect($grupos->grupos()->count())->toBe(4)
                ->and($grupos->partidos()->count())->toBe(16)
                ->and($grupos->partidos()->where('estado', EstadoPartido::Programado->value)->count())->toBe(16);
        }

        $enJuego = Torneo::query()->where('slug', 'open-demo-en-juego')->sole();
        $faseEnJuego = $enJuego->categorias->first()->fases()->where('tipo', 'grupos')->sole();
        expect($faseEnJuego->estado)->toBe(EstadoFase::EnJuego)
            ->and($faseEnJuego->partidos()->where('estado', EstadoPartido::Jugado->value)->count())->toBeGreaterThan(6);

        $finalizado = Torneo::query()->where('slug', 'open-demo-finalizado')->sole();
        $fases = $finalizado->categorias->first()->fases;
        expect($fases)->toHaveCount(3)
            ->and($fases->every(fn (Fase $f): bool => $f->estado === EstadoFase::Cerrada))->toBeTrue();

        $cuadro = $fases->firstWhere('tipo', 'eliminatoria');
        expect($cuadro->partidos()->count())->toBe(8)
            ->and($cuadro->partidos()->where('es_tercer_puesto', false)->where('ronda', 3)->sole()->ganador_id)->not->toBeNull()
            ->and(Partido::query()->where('fase_id', $fases->firstWhere('tipo', 'consolacion')->id)->count())->toBe(3);
    });
});
