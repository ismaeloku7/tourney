<?php

namespace App\Actions\Plataforma;

use App\Enums\Rol;
use App\Models\AuditoriaGlobal;
use App\Models\Organizador;
use App\Models\Plan;
use App\Models\SuscripcionPlan;
use App\Models\User;
use App\Models\UsuarioPlataforma;
use Database\Seeders\Organizador\BaseDelOrganizadorSeeder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator as ValidadorConcreto;
use Stancl\Tenancy\Database\Models\Domain;

/**
 * Da de alta un organizador: su fila en la central, su base (stancl la
 * crea y la migra), su subdominio, su dominio propio si lo trae, su
 * suscripción al plan y el usuario propietario en su base.
 *
 * La contraseña del propietario, si no viene decidida, se genera al azar y
 * queda en `claveGenerada` para enseñarla una sola vez; no se guarda en
 * claro en ningún sitio.
 */
class CrearOrganizador
{
    public ?string $claveGenerada = null;

    /**
     * @throws ValidationException Si el slug o el dominio no valen.
     */
    public function handle(DatosDeOrganizadorNuevo $datos, ?UsuarioPlataforma $quien = null): Organizador
    {
        $slug = Str::lower(trim($datos->slug));
        $dominio = $datos->dominio === null || trim($datos->dominio) === '' ? null : Organizador::normalizarDominio($datos->dominio);

        $this->validar($slug, $dominio, $datos->responsableEmail);

        $plan = Plan::porClave($datos->plan);
        $clave = $datos->responsablePassword ?? ($this->claveGenerada = Str::password(16));

        $organizador = Organizador::query()->create(array_filter([
            'id' => $slug,
            'nombre' => trim($datos->nombre),
            'estado' => $datos->estado,
            'plan_id' => $plan->id,
            'responsable_nombre' => trim($datos->responsableNombre),
            'responsable_email' => Str::lower(trim($datos->responsableEmail)),
            'verificado_en' => now(),
            'dominio' => $dominio,
        ], fn (mixed $valor): bool => $valor !== null && $valor !== ''));

        SuscripcionPlan::query()->create([
            'organizador_id' => $organizador->id,
            'plan_id' => $plan->id,
            'desde' => now()->toDateString(),
            'cambiado_por' => $quien?->id,
        ]);

        $organizador->run(function () use ($organizador, $clave): void {
            User::query()->create([
                'nombre' => $organizador->responsable_nombre,
                'email' => $organizador->responsable_email,
                'rol' => Rol::Propietario,
                'activo' => true,
                'email_verified_at' => now(),
                'password' => $clave,
            ]);

            // Plantillas de formato de partido y nombre público.
            (new BaseDelOrganizadorSeeder)->run();
        });

        AuditoriaGlobal::anotar('crear', $organizador, $quien, ['plan' => $plan->clave, 'dominio' => $dominio]);

        return $organizador;
    }

    /**
     * @throws ValidationException
     */
    private function validar(string $slug, ?string $dominio, string $email): void
    {
        $centrales = config()->array('tenancy.central_domains');

        $validador = Validator::make(
            ['slug' => $slug, 'dominio' => $dominio, 'email' => $email],
            [
                'slug' => [
                    'required', 'string', 'min:3', 'max:40', 'regex:/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/',
                    'not_in:'.implode(',', config()->array('organizadores.slugs_reservados')),
                    'unique:'.Organizador::class.',id',
                    'unique:'.Domain::class.',domain',
                ],
                'dominio' => [
                    'nullable', 'string', 'max:190', 'regex:/^[a-z0-9.-]+\.[a-z]{2,}$/',
                    'not_in:'.implode(',', $centrales),
                    'unique:'.Domain::class.',domain',
                ],
                'email' => ['required', 'email', 'max:190'],
            ],
            [
                'slug.regex' => 'El slug solo admite minúsculas, números y guiones, sin empezar ni acabar en guión.',
                'slug.not_in' => 'Ese slug está reservado.',
                'slug.unique' => 'Ya hay un organizador con ese slug.',
                'dominio.regex' => 'El dominio propio no tiene forma de dominio.',
                'dominio.not_in' => 'El dominio propio no puede ser el de la plataforma.',
                'dominio.unique' => 'Ese dominio ya es de otro organizador.',
            ],
        );

        // Un dominio propio tampoco puede ser un subdominio de la plataforma.
        $validador->after(function (ValidadorConcreto $validador) use ($dominio, $centrales): void {
            foreach ($centrales as $central) {
                if ($dominio !== null && str_ends_with($dominio, '.'.$central)) {
                    $validador->errors()->add('dominio', 'Un subdominio de la plataforma no se da de alta como dominio propio: es el slug.');
                }
            }
        });

        $validador->validate();
    }
}
