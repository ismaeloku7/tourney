<?php

namespace App\Console\Commands;

use App\Actions\Plataforma\CrearOrganizador as Accion;
use App\Actions\Plataforma\DatosDeOrganizadorNuevo;
use App\Models\Plan;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/**
 * Da de alta un organizador desde la consola: fila central, base,
 * subdominio, dominio propio si se indica y propietario. Sustituye al
 * alta self-service mientras no exista (fase 2) y sirve para el
 * organizador real en producción.
 */
class CrearOrganizador extends Command
{
    protected $signature = 'organizadores:crear
        {slug : Subdominio y nombre de la base, p. ej. demo}
        {nombre : Nombre de la organización}
        {email : Correo del propietario}
        {--responsable= : Nombre del propietario (si falta, el de la organización)}
        {--dominio= : Dominio propio, p. ej. tourney.clubceas.es}
        {--plan=gratis : Clave del plan: gratis, club u organizador}
        {--clave= : Contraseña del propietario (si falta, se genera y se enseña una vez)}';

    protected $description = 'Da de alta un organizador con su base, su subdominio y su propietario';

    public function handle(Accion $accion): int
    {
        $plan = (string) $this->option('plan');

        if (Plan::query()->where('clave', $plan)->doesntExist()) {
            $this->components->error("No hay plan «{$plan}». Siembre los planes (php artisan db:seed --class=PlanesSeeder).");

            return self::FAILURE;
        }

        $datos = new DatosDeOrganizadorNuevo(
            slug: (string) $this->argument('slug'),
            nombre: (string) $this->argument('nombre'),
            responsableNombre: (string) ($this->option('responsable') ?: $this->argument('nombre')),
            responsableEmail: (string) $this->argument('email'),
            responsablePassword: $this->option('clave') ? (string) $this->option('clave') : null,
            dominio: $this->option('dominio') ? (string) $this->option('dominio') : null,
            plan: $plan,
        );

        try {
            $organizador = $accion->handle($datos);
        } catch (ValidationException $e) {
            foreach ($e->validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $this->components->info("Organizador «{$organizador->nombre}» creado con la base {$organizador->nombreDeBase()}.");
        $this->components->bulletList(array_filter([
            'Dirección: '.$organizador->url(),
            $organizador->dominio ? 'Dominio propio: '.$organizador->dominio : null,
            'Propietario: '.$organizador->responsable_email,
            $accion->claveGenerada ? 'Contraseña del propietario (no se vuelve a mostrar): '.$accion->claveGenerada : null,
        ]));

        return self::SUCCESS;
    }
}
