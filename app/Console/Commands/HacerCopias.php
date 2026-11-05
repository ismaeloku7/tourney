<?php

namespace App\Console\Commands;

use App\Copias\CopiaDeSeguridad;
use App\Enums\EstadoOrganizador;
use App\Models\Organizador;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

/**
 * La copia de seguridad diaria: la base central y la de cada organizador
 * con sus archivos, en `storage/app/private/copias`. Se puede lanzar a
 * mano y para un solo organizador.
 */
class HacerCopias extends Command
{
    protected $signature = 'copias:hacer {--organizador= : Solo este organizador (su slug)} {--sin-central : No copiar la base central}';

    protected $description = 'Hace la copia de seguridad de la base central y de cada organizador';

    public function handle(CopiaDeSeguridad $copias): int
    {
        $fallos = 0;

        if (! $this->option('sin-central') && $this->option('organizador') === null) {
            $fallos += $this->intentar('Base central', fn (): string => $copias->deLaCentral());
        }

        /** @var Collection<int, Organizador> $organizadores */
        $organizadores = Organizador::query()
            ->when($this->option('organizador') !== null, fn (Builder $q): Builder => $q->whereKey((string) $this->option('organizador')))
            ->where('estado', '!=', EstadoOrganizador::Baja->value)
            ->orderBy('id')->get();

        foreach ($organizadores as $organizador) {
            $fallos += $this->intentar($organizador->nombre, fn (): string => $copias->deOrganizador($organizador));
        }

        $this->info('Copias hechas: '.(count($organizadores) + ($this->option('sin-central') || $this->option('organizador') !== null ? 0 : 1) - $fallos).'. Fallos: '.$fallos.'.');

        return $fallos === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** @param  callable(): string  $hacer */
    private function intentar(string $que, callable $hacer): int
    {
        try {
            $this->line("{$que}: ".$hacer());

            return 0;
        } catch (Throwable $e) {
            $this->error("{$que}: no se ha podido hacer la copia. ".$e->getMessage());
            report($e);

            return 1;
        }
    }
}
