<?php
declare(strict_types=1);

namespace Spdv;

/** Dinheiro sempre em centavos (inteiro): evita erros de arredondamento do `real` do Pascal. */
final class Money
{
    public static function parse(mixed $valor): int
    {
        $s = trim((string) $valor);
        if (str_contains($s, ',')) {
            $s = str_replace(['.', ','], ['', '.'], $s);   // 1.234,50 -> 1234.50
        }
        if (!preg_match('/^\d{1,9}(\.\d{1,2})?$/', $s)) {
            throw new HttpException(422, 'Valor inválido: ' . $valor);
        }
        [$inteiro, $decimal] = array_pad(explode('.', $s), 2, '0');
        return (int) $inteiro * 100 + (int) str_pad($decimal, 2, '0');
    }

    public static function fmt(int $centavos): string
    {
        return number_format($centavos / 100, 2, ',', '.');
    }
}
