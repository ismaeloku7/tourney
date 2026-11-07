<?php

namespace App\Filament\Resources\Jugadores\Pages;

use App\Filament\Resources\Jugadores\JugadorResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditJugador extends EditRecord
{
    protected static string $resource = JugadorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * El modelo oculta correo y teléfono al convertirse en array para que
     * nunca se cuelen en público, así que Filament no los rellena solo. En
     * la ficha del panel sí hacen falta: se vuelven a poner a mano.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $jugador = $this->getRecord();

        $data['email'] = $jugador->getAttribute('email');
        $data['telefono'] = $jugador->getAttribute('telefono');

        return $data;
    }
}
