<?php

namespace App\Http\Middleware;

use App\Models\Organizador;
use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Database\Models\Domain;
use Symfony\Component\HttpFoundation\Response;

/**
 * Elige el organizador por el host y activa su base (stancl/tenancy).
 *
 * - `demo.<dominio-saas>`: el subdominio.
 * - `tourney.clubceas.es`: el dominio propio, con o sin `www.`.
 * - El dominio del SaaS: sin organizador (landing, alta, plataforma).
 * - Cualquier otro host, o un organizador que no esté activo: 404, antes
 *   de abrir sesión.
 *
 * El organizador no entra nunca por la query, el formulario ni una
 * cabecera. Al terminar la petición se deja el que hubiera antes.
 */
class ResolverOrganizadorPorHost
{
    private const ANTERIOR = 'organizador_anterior';

    public const HOST = 'organizador_host';

    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set(self::ANTERIOR, tenant());

        $host = Organizador::normalizarDominio($request->getHost());

        if (self::esCentral($host)) {
            tenancy()->end();

            return $next($request);
        }

        $organizador = $this->buscar($host);

        abort_if($organizador === null, 404);

        tenancy()->initialize($organizador);
        // Por dónde ha entrado: los enlaces que salgan de esta petición
        // (correos a jugadores) llevan a esta misma dirección (Organizador::url).
        $request->attributes->set(self::HOST, $request->getSchemeAndHttpHost());

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $anterior = $request->attributes->get(self::ANTERIOR);

        $anterior instanceof Organizador ? tenancy()->initialize($anterior) : tenancy()->end();
    }

    public static function esCentral(string $host): bool
    {
        return in_array($host, config()->array('tenancy.central_domains'), true);
    }

    private function buscar(string $host): ?Organizador
    {
        $clave = $host;

        foreach (config()->array('tenancy.central_domains') as $central) {
            if (str_ends_with($host, '.'.$central)) {
                $clave = substr($host, 0, -strlen('.'.$central));

                break;
            }
        }

        $organizador = Domain::query()->where('domain', $clave)->first()?->tenant;

        if ($organizador === null && app()->environment('local', 'testing')) {
            // En local, `localhost` o `127.0.0.1` no son de ningún organizador.
            // Se declaran en ORGANIZADORES_HOSTS_DESARROLLO. En producción no se mira.
            $slug = config()->array('organizadores.hosts_desarrollo')[$host] ?? null;
            $organizador = $slug === null ? null : Organizador::query()->find($slug);
        }

        return $organizador instanceof Organizador && $organizador->estaActivo() ? $organizador : null;
    }
}
