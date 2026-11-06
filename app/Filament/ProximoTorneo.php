<?php

namespace App\Filament;

use App\Enums\EstadoTorneo;
use App\Filament\Resources\Torneos\TorneoResource;
use App\Models\Torneo;
use Filament\Navigation\NavigationItem;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

/**
 * El acceso directo al próximo torneo, el primero del menú de la izquierda:
 * el que está en juego o, si no hay, el siguiente que empieza (o el que
 * sigue en marcha) sin finalizar ni archivar. Abre su ficha. Un
 * administrador de torneo solo ve los suyos; sin ninguno, no sale.
 */
final class ProximoTorneo
{
    /** El torneo se busca una vez por petición: el menú lo pide varias veces. */
    private const CLAVE = 'proximo_torneo';

    public static function torneo(): ?Torneo
    {
        $atributos = request()->attributes;
        if (! $atributos->has(self::CLAVE)) {
            $atributos->set(self::CLAVE, self::buscar());
        }

        $torneo = $atributos->get(self::CLAVE);

        return $torneo instanceof Torneo ? $torneo : null;
    }

    private static function buscar(): ?Torneo
    {
        if (! TorneoResource::canViewAny()) {
            return null;
        }

        /** @var Builder<Torneo> $consulta */
        $consulta = TorneoResource::getEloquentQuery();

        return $consulta
            ->whereNotIn('estado', [EstadoTorneo::Finalizado->value, EstadoTorneo::Archivado->value])
            ->where(fn (Builder $q) => $q
                ->where('estado', EstadoTorneo::EnJuego->value)
                ->orWhereDate('fecha_fin', '>=', today()))
            ->orderByRaw('case when estado = ? then 0 else 1 end', [EstadoTorneo::EnJuego->value])
            ->orderBy('fecha_inicio')
            ->orderBy('id')
            ->first();
    }

    public static function enElMenu(): NavigationItem
    {
        return NavigationItem::make('proximo-torneo')
            ->label(fn (): string => self::torneo()->nombre ?? 'Próximo torneo')
            ->icon(Heroicon::OutlinedStar)
            ->badge(fn (): ?string => self::torneo()?->estado->etiqueta(), fn (): string => self::torneo()?->estado->color() ?? 'gray')
            ->url(fn (): ?string => ($torneo = self::torneo()) !== null ? TorneoResource::getUrl('view', ['record' => $torneo]) : null)
            ->isActiveWhen(fn (): bool => ($torneo = self::torneo()) !== null
                && request()->routeIs('filament.panel.resources.torneos.*')
                && (string) request()->route('record') === (string) $torneo->getKey())
            ->visible(fn (): bool => self::torneo() !== null)
            // Por delante del escritorio (-2) y de los grupos.
            ->sort(-10);
    }
}
