<?php

namespace Database\Seeders\Organizador;

use App\Enums\Ajuste;
use App\Models\FormatoPartido;
use App\Models\Organizador;
use App\Models\ValorDeAjuste;
use Illuminate\Database\Seeder;

/**
 * Con lo que nace la base de todo organizador, real o de pruebas: las
 * plantillas de formato de partido y el nombre público. Se ejecuta dentro
 * del organizador al darlo de alta. Repetible.
 */
class BaseDelOrganizadorSeeder extends Seeder
{
    public function run(): void
    {
        foreach (FormatoPartido::porDefecto() as $formato) {
            FormatoPartido::query()->firstOrCreate(['nombre' => $formato['nombre']], $formato);
        }

        $organizador = tenant();

        if ($organizador instanceof Organizador && ValorDeAjuste::query()->whereKey(Ajuste::NombrePublico->value)->doesntExist()) {
            ValorDeAjuste::fijar([Ajuste::NombrePublico->value => $organizador->nombre]);
        }
    }
}
