<?php

namespace App\Filament\Resources\FormatosPartido\Pages;

use App\Competicion\Datos\FormatoDePartido;
use App\Competicion\Errores\ConfiguracionInvalida;
use App\Filament\Resources\FormatosPartido\FormatoPartidoResource;
use App\Models\FormatoPartido;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateFormatoPartido extends CreateRecord
{
    protected static string $resource = FormatoPartidoResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return self::comprobar($data);
    }

    protected function afterCreate(): void
    {
        $formato = $this->getRecord();
        assert($formato instanceof FormatoPartido);
        self::dejarUnSoloPredeterminado($formato);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private static function comprobar(array $data): array
    {
        try {
            FormatoDePartido::desdeArray($data);
        } catch (ConfiguracionInvalida $e) {
            throw ValidationException::withMessages(['data.sets' => $e->errores]);
        }

        return $data;
    }

    private static function dejarUnSoloPredeterminado(FormatoPartido $formato): void
    {
        if ($formato->es_predeterminado) {
            FormatoPartido::query()->whereKeyNot($formato->id)->update(['es_predeterminado' => false]);
        }
    }
}
