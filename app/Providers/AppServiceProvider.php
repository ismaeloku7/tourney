<?php

namespace App\Providers;

use App\Auth\GuardDeSesion;
use App\Push\Remitente;
use App\Push\RemitenteWebPush;
use App\Tenancy\OrganizadorActual;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Uno por petición o job: nunca se arrastra el organizador de la anterior.
        $this->app->scoped(OrganizadorActual::class);

        // En producción, Web Push; las pruebas lo cambian por RemitenteDePruebas.
        $this->app->bind(Remitente::class, RemitenteWebPush::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Los guards de sesión del equipo y de los jugadores: salir en un sitio no echa de los demás.
        Auth::extend('sesion', function (Application $app, string $nombre, array $config): GuardDeSesion {
            $guard = new GuardDeSesion(
                $nombre,
                Auth::createUserProvider($config['provider'] ?? null),
                $app['session.store'],
                rehashOnLogin: (bool) $app['config']->get('hashing.rehash_on_login', true),
                timeboxDuration: (int) $app['config']->get('auth.timebox_duration', 200000),
                hashKey: (string) $app['config']->get('app.key'),
            );
            $guard->setCookieJar($app['cookie']);
            $guard->setDispatcher($app['events']);
            $guard->setRequest($app->refresh('request', $guard, 'setRequest'));
            if (isset($config['remember'])) {
                $guard->setRememberDuration((int) $config['remember']);
            }

            return $guard;
        });

        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        // Las fechas no se mutan por accidente: una franja horaria que se
        // desplaza al calcular la siguiente seguiría siendo la misma franja.
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        // Doce caracteres como mínimo, igual que en CEAS, y que no sean solo espacios.
        Password::defaults(fn (): Password => Password::min(12)->rules(['not_regex:/^\s*$/']));
    }
}
