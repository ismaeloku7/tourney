<?php

namespace Database\Factories;

use App\Enums\Rol;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Crear un usuario exige organizador actual: use
 * `OrganizadorActual::ejecutarComo()` o, en pruebas, `comoOrganizador()`.
 *
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'nombre' => fake()->firstName(),
            'apellidos' => fake()->lastName().' '.fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'rol' => Rol::Administrador,
            'activo' => true,
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function rol(Rol $rol): static
    {
        return $this->state(['rol' => $rol]);
    }

    public function inactivo(): static
    {
        return $this->state(['activo' => false]);
    }
}
