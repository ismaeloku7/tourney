<?php

namespace Database\Factories;

use App\Enums\EstadoOrganizador;
use App\Models\Organizador;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Crear un organizador crea también su base y la migra (stancl/tenancy).
 *
 * @extends Factory<Organizador>
 */
class OrganizadorFactory extends Factory
{
    public function definition(): array
    {
        $id = 'org'.Str::lower(Str::random(8));

        return [
            'id' => $id,
            'nombre' => 'Club de Pádel '.ucfirst(substr($id, 3)),
            'estado' => EstadoOrganizador::Activo,
            'plan_id' => fn () => Plan::query()->where('clave', Plan::GRATIS)->value('id') ?? Plan::factory()->create()->id,
            'responsable_nombre' => fake()->name(),
            'responsable_email' => fake()->unique()->safeEmail(),
            'verificado_en' => now(),
        ];
    }

    public function suspendido(): static
    {
        return $this->state(['estado' => EstadoOrganizador::Suspendido, 'suspendido_en' => now()]);
    }

    public function pendiente(): static
    {
        return $this->state(['estado' => EstadoOrganizador::Pendiente, 'verificado_en' => null]);
    }

    public function enPlan(Plan $plan): static
    {
        return $this->state(['plan_id' => $plan->id]);
    }
}
