<?php

namespace App\Filament\Resources\Categorias\Pages;

use App\Exportaciones\AccionesDeExportacion;
use App\Filament\Resources\Categorias\CategoriaResource;
use App\Filament\Resources\Torneos\TorneoResource;
use App\Models\Categoria;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * La ficha de la categoría: datos arriba, y debajo sus fases y sus
 * inscripciones. Se borra desde el torneo.
 */
class EditCategoria extends EditRecord
{
    protected static string $resource = CategoriaResource::class;

    public function getTitle(): string|Htmlable
    {
        return $this->categoria()->nombre.' · '.$this->categoria()->torneo->nombre;
    }

    /** @return array<string, string> */
    public function getBreadcrumbs(): array
    {
        $torneo = $this->categoria()->torneo;

        return [
            TorneoResource::getUrl('index') => 'Torneos',
            TorneoResource::getUrl('edit', ['record' => $torneo]) => $torneo->nombre,
            '#' => $this->categoria()->nombre,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            AccionesDeExportacion::pdfDeCategoria(),
            Action::make('torneo')
                ->label('Volver al torneo')
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->color('gray')
                ->url(fn (): string => TorneoResource::getUrl('edit', ['record' => $this->categoria()->torneo])),
        ];
    }

    private function categoria(): Categoria
    {
        $categoria = $this->getRecord();
        assert($categoria instanceof Categoria);

        return $categoria;
    }
}
