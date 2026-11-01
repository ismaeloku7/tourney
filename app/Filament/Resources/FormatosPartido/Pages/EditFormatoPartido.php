<?php

namespace App\Filament\Resources\FormatosPartido\Pages;

use App\Competicion\Datos\FormatoDePartido;
use App\Competicion\Errores\ConfiguracionInvalida;
use App\Filament\Resources\FormatosPartido\FormatoPartidoResource;
use App\Models\FormatoPartido;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditFormatoPartido extends EditRecord
{
    protected static string $resource = FormatoPartidoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return self::comprobar($data);
    }

    protected function afterSave(): void
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
