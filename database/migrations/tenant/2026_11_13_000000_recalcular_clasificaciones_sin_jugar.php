<?php

use App\Actions\Competicion\RecalcularClasificacion;
use App\Competicion\Errores\ErrorDeCompeticion;
use App\Enums\EstadoFase;
use App\Models\Grupo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Migrations\Migration;

/**
 * Los grupos ya sorteados y aún sin jugar pasan a ordenarse como se
 * ordenan ahora mientras no hay resultados: el cabeza de serie primero y
 * luego por nivel. Con algo jugado, el recálculo da lo mismo que había.
 * Repetible.
 */
return new class extends Migration
{
    public function up(): void
    {
        $grupos = Grupo::query()->whereHas('fase', fn (Builder $q) => $q->where('estado', '!=', EstadoFase::Cerrada->value))->get();

        foreach ($grupos as $grupo) {
            try {
                app(RecalcularClasificacion::class)->handle($grupo);
            } catch (ErrorDeCompeticion) {
                // Una fase con la configuración rota se queda como estaba.
            }
        }
    }

    public function down(): void {}
};
