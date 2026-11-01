<?php

namespace App\Http\Controllers;

use App\Enums\Ajuste;
use App\Models\Organizador;
use App\Models\ValorDeAjuste;
use Illuminate\Contracts\View\View;

/**
 * Aviso legal y política de privacidad. En el dominio de la plataforma,
 * los del titular del SaaS (config/saas.php); en el host de un
 * organizador, los textos que haya escrito en sus ajustes. Si faltan, la
 * página lo dice en vez de enseñar datos inventados.
 */
class PaginasLegales
{
    public function aviso(): View
    {
        return $this->pagina('Aviso legal', Ajuste::TextoLegalAviso);
    }

    public function privacidad(): View
    {
        return $this->pagina('Política de privacidad', Ajuste::TextoLegalPrivacidad);
    }

    private function pagina(string $titulo, Ajuste $ajuste): View
    {
        $organizador = tenant();
        $texto = $organizador instanceof Organizador ? ValorDeAjuste::obtener($ajuste) : null;

        return view('legal.pagina', [
            'titulo' => $titulo,
            'organizador' => $organizador instanceof Organizador ? $organizador : null,
            'texto' => is_string($texto) && trim($texto) !== '' ? $texto : null,
            'titular' => config()->array('saas.titular'),
            'esPrivacidad' => $ajuste === Ajuste::TextoLegalPrivacidad,
        ]);
    }
}
