<?php

use App\Actions\Competicion\RecalcularClasificacion;
use App\Competicion\Errores\ErrorDeCompeticion;
use App\Enums\EstadoFase;
use App\Models\Grupo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Migrations\Migration;

/**
 * Otra vez, ahora que los grupos sin jugar se ordenan solo por nivel (el
 * cabeza de serie ya no va primero). Con algo jugado, el recálculo da lo
 * mismo que había. Repetible.
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
