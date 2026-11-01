<?php
/*
 * Una imitación mínima de Pest para ejecutar tests/Unit/Competicion con
 * PHP a secas: test(), expect() con los matchers que usamos, datasets con
 * ->with() y propiedades de orden superior (expect($x)->campo->toBe()).
 */

final class FalloDeExpectativa extends Exception {}

final class RegistroDePruebas
{
    /** @var list<array{nombre: string, fn: Closure, datasets: array|null, archivo: string}> */
    public static array $pruebas = [];
}

final class PruebaDefinida
{
    public function __construct(private int $indice) {}

    public function with(array $casos): self
    {
        RegistroDePruebas::$pruebas[$this->indice]['datasets'] = $casos;

        return $this;
    }

    public function throws(string $clase, ?string $mensaje = null): self
    {
        $prueba = RegistroDePruebas::$pruebas[$this->indice];
        $fn = $prueba['fn'];
        RegistroDePruebas::$pruebas[$this->indice]['fn'] = function (...$args) use ($fn, $clase, $mensaje): void {
            try {
                $fn(...$args);
            } catch (Throwable $e) {
                if (! $e instanceof $clase) {
                    throw new FalloDeExpectativa('Se esperaba '.$clase.' y saltó '.get_class($e).': '.$e->getMessage());
                }
                if ($mensaje !== null && ! str_contains($e->getMessage(), $mensaje)) {
                    throw new FalloDeExpectativa('Se esperaba el mensaje «'.$mensaje.'» y fue «'.$e->getMessage().'»');
                }

                return;
            }
            throw new FalloDeExpectativa('Se esperaba que lanzara '.$clase);
        };

        return $this;
    }
}

function test(string $nombre, Closure $fn): PruebaDefinida
{
    $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
    RegistroDePruebas::$pruebas[] = ['nombre' => $nombre, 'fn' => $fn, 'datasets' => null, 'archivo' => basename($trace['file'] ?? '?')];

    return new PruebaDefinida(count(RegistroDePruebas::$pruebas) - 1);
}

function it(string $nombre, Closure $fn): PruebaDefinida
{
    return test('it '.$nombre, $fn);
}

function expect(mixed $valor = null): Expectativa
{
    return new Expectativa($valor);
}

final class Expectativa
{
    private bool $negar = false;

    /** El valor original cuando se ha bajado a una propiedad (expect($u)->a->toBe()->b->toBe()). */
    private mixed $raiz = null;

    private bool $derivada = false;

    public function __construct(public mixed $valor) {}

    public function __get(string $nombre): mixed
    {
        if ($nombre === 'not') {
            $copia = clone $this;
            $copia->negar = ! $this->negar;

            return $copia;
        }

        $base = $this->derivada ? $this->raiz : $this->valor;

        if (is_object($base) && (property_exists($base, $nombre) || isset($base->{$nombre}))) {
            $copia = new self($base->{$nombre});
        } elseif (is_array($base) && array_key_exists($nombre, $base)) {
            $copia = new self($base[$nombre]);
        } else {
            throw new FalloDeExpectativa("No hay propiedad {$nombre} en el valor esperado");
        }
        $copia->raiz = $base;
        $copia->derivada = true;

        return $copia;

        throw new FalloDeExpectativa("No hay propiedad {$nombre} en el valor esperado");
    }

    public function __call(string $nombre, array $args): mixed
    {
        if (is_object($this->valor) && method_exists($this->valor, $nombre)) {
            return new self($this->valor->{$nombre}(...$args));
        }

        throw new FalloDeExpectativa("Matcher o método desconocido: {$nombre}");
    }

    private function comprobar(bool $ok, string $mensaje): self
    {
        if ($ok === $this->negar) {
            throw new FalloDeExpectativa(($this->negar ? 'NO ' : '').$mensaje);
        }
        $this->negar = false;

        return $this;
    }

    private static function texto(mixed $v): string
    {
        if ($v instanceof BackedEnum) {
            return $v::class.'::'.$v->name;
        }

        return is_scalar($v) || $v === null ? var_export($v, true) : (is_object($v) ? get_class($v).' '.json_encode($v, JSON_PARTIAL_OUTPUT_ON_ERROR) : json_encode($v, JSON_PARTIAL_OUTPUT_ON_ERROR));
    }

    public function and(mixed $valor): self
    {
        return new self($valor);
    }

    public function toBe(mixed $esperado): self
    {
        return $this->comprobar($this->valor === $esperado, 'esperaba '.self::texto($esperado).' y hay '.self::texto($this->valor));
    }

    public function toEqual(mixed $esperado): self
    {
        return $this->comprobar($this->valor == $esperado, 'esperaba (==) '.self::texto($esperado).' y hay '.self::texto($this->valor));
    }

    public function toEqualCanonicalizing(array $esperado): self
    {
        $a = $this->valor;
        $b = $esperado;
        sort($a);
        sort($b);

        return $this->comprobar($a == $b, 'esperaba (sin orden) '.self::texto($esperado).' y hay '.self::texto($this->valor));
    }

    public function toBeTrue(): self
    {
        return $this->toBe(true);
    }

    public function toBeFalse(): self
    {
        return $this->toBe(false);
    }

    public function toBeNull(): self
    {
        return $this->toBe(null);
    }

    public function toBeEmpty(): self
    {
        return $this->comprobar(empty($this->valor), 'esperaba vacío y hay '.self::texto($this->valor));
    }

    public function toBeInt(): self
    {
        return $this->comprobar(is_int($this->valor), 'esperaba un entero');
    }

    public function toBeString(): self
    {
        return $this->comprobar(is_string($this->valor), 'esperaba una cadena');
    }

    public function toBeArray(): self
    {
        return $this->comprobar(is_array($this->valor), 'esperaba un array');
    }

    public function toBeInstanceOf(string $clase): self
    {
        return $this->comprobar($this->valor instanceof $clase, 'esperaba instancia de '.$clase);
    }

    public function toHaveCount(int $n): self
    {
        return $this->comprobar(count($this->valor) === $n, "esperaba {$n} elementos y hay ".count($this->valor));
    }

    public function toContain(mixed ...$esperados): self
    {
        foreach ($esperados as $esperado) {
            $ok = is_string($this->valor) ? str_contains($this->valor, (string) $esperado) : in_array($esperado, $this->valor, true);
            $this->comprobar($ok, 'esperaba que contuviera '.self::texto($esperado).' en '.self::texto($this->valor));
        }

        return $this;
    }

    public function toHaveKey(string|int $clave): self
    {
        return $this->comprobar(is_array($this->valor) && array_key_exists($clave, $this->valor), 'esperaba la clave '.$clave);
    }

    public function toHaveKeys(array $claves): self
    {
        foreach ($claves as $clave) {
            $this->toHaveKey($clave);
        }

        return $this;
    }

    public function toBeGreaterThan(int|float $n): self
    {
        return $this->comprobar($this->valor > $n, "esperaba > {$n} y hay ".self::texto($this->valor));
    }

    public function toBeGreaterThanOrEqual(int|float $n): self
    {
        return $this->comprobar($this->valor >= $n, "esperaba >= {$n} y hay ".self::texto($this->valor));
    }

    public function toBeLessThan(int|float $n): self
    {
        return $this->comprobar($this->valor < $n, "esperaba < {$n} y hay ".self::texto($this->valor));
    }

    public function toBeLessThanOrEqual(int|float $n): self
    {
        return $this->comprobar($this->valor <= $n, "esperaba <= {$n} y hay ".self::texto($this->valor));
    }

    public function toBeBetween(int|float $a, int|float $b): self
    {
        return $this->comprobar($this->valor >= $a && $this->valor <= $b, "esperaba entre {$a} y {$b} y hay ".self::texto($this->valor));
    }

    public function toStartWith(string $s): self
    {
        return $this->comprobar(str_starts_with($this->valor, $s), "esperaba que empezara por {$s}: ".self::texto($this->valor));
    }

    public function toEndWith(string $s): self
    {
        return $this->comprobar(str_ends_with($this->valor, $s), "esperaba que acabara en {$s}: ".self::texto($this->valor));
    }

    public function toMatch(string $regex): self
    {
        return $this->comprobar(preg_match($regex, $this->valor) === 1, "esperaba que casara con {$regex}: ".self::texto($this->valor));
    }

    public function toThrow(string $clase, ?string $mensaje = null): self
    {
        try {
            ($this->valor)();
        } catch (Throwable $e) {
            if ($this->negar) {
                throw new FalloDeExpectativa('No se esperaba excepción y saltó '.get_class($e).': '.$e->getMessage());
            }
            if (! $e instanceof $clase) {
                throw new FalloDeExpectativa('Se esperaba '.$clase.' y saltó '.get_class($e).': '.$e->getMessage());
            }
            if ($mensaje !== null && ! str_contains($e->getMessage(), $mensaje)) {
                throw new FalloDeExpectativa('Se esperaba el mensaje «'.$mensaje.'» y fue «'.$e->getMessage().'»');
            }

            return $this;
        }
        if ($this->negar) {
            $this->negar = false;

            return $this;
        }
        throw new FalloDeExpectativa('Se esperaba que lanzara '.$clase);
    }
}

function ejecutarPruebas(array $archivos): int
{
    foreach ($archivos as $archivo) {
        require $archivo;
    }

    $ok = 0;
    $fallos = [];

    foreach (RegistroDePruebas::$pruebas as $prueba) {
        $casos = $prueba['datasets'] === null ? [null => []] : $prueba['datasets'];

        foreach ($casos as $etiqueta => $argumentos) {
            $args = $prueba['datasets'] === null ? [] : (is_array($argumentos) ? array_values($argumentos) : [$argumentos]);
            $nombre = $prueba['archivo'].' › '.$prueba['nombre'].($prueba['datasets'] === null ? '' : ' ('.(is_int($etiqueta) ? 'caso '.($etiqueta + 1) : $etiqueta).')');

            try {
                ($prueba['fn'])(...$args);
                $ok++;
            } catch (Throwable $e) {
                $fallos[] = $nombre.': '.get_class($e).' — '.$e->getMessage().' @ '.basename($e->getFile()).':'.$e->getLine();
            }
        }
    }

    foreach ($fallos as $fallo) {
        echo "FALLO ", $fallo, "\n";
    }
    echo "\n", $ok, " pruebas en verde, ", count($fallos), " fallos.\n";

    return $fallos === [] ? 0 : 1;
}
