<?php

namespace App\Providers\Filament;

use App\Filament\Avatares\AvatarDeIniciales;
use App\Filament\ColorDelPanel;
use App\Filament\Pages\Auth\EditarPerfil;
use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Panel as PanelDelOrganizador;
use App\Http\Middleware\ExigirOrganizador;
use App\Http\Middleware\ResolverOrganizadorPorHost;
use App\Tenancy\OrganizadorActual;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * El panel del equipo del organizador, en `/panel`.
 *
 * No hay selector de organizador: lo elige el host antes que nada, igual
 * que en el resto de la aplicación. La raíz `/` sigue siendo pública.
 */
class PanelDelOrganizadorProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $panel = $panel
            ->default()
            ->id('panel')
            ->path('panel')
            ->authGuard('web')
            ->login(Login::class)
            ->passwordReset()
            ->profile(EditarPerfil::class, isSimple: false)
            ->brandName(fn (): string => self::nombreDelOrganizador())
            // Sin llamadas a ui-avatars.com con el nombre de cada persona.
            ->defaultAvatarProvider(AvatarDeIniciales::class)
            ->colors(fn (): array => [
                'primary' => Color::hex(ColorDelPanel::hex()),
            ])
            ->font('Plus Jakarta Sans')
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth(Width::Full)
            ->navigationGroups([
                'Competición',
                'Instalaciones',
                'Organización',
            ])
            // El acento lo elige el organizador en sus ajustes; el tema solo lee la variable.
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): string => view('filament.panel.color')->render())
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                PanelDelOrganizador::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                // Primero el organizador: un host desconocido no llega a abrir sesión.
                ResolverOrganizadorPorHost::class,
                ExigirOrganizador::class,
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);

        // El tema (resources/css/filament/panel/theme.css) sale de Vite: sin
        // los assets compilados, como en las pruebas sin `npm run build`, el
        // panel se queda con el de Filament en vez de fallar.
        if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot'))) {
            $panel->viteTheme('resources/css/filament/panel/theme.css');
        }

        return $panel;
    }

    private static function nombreDelOrganizador(): string
    {
        $actual = app(OrganizadorActual::class);

        return $actual->hay() ? $actual->organizador()->nombre : config()->string('app.name');
    }
}
