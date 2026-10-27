<?php
declare(strict_types=1);

namespace Spdv;

/**
 * Monta o cupom em texto de largura fixa (48 colunas, como o original).
 * Cada linha: ['t' => texto, 'dupla' => true se era impressa em "altura dupla" (fontAlt2 do Pascal)].
 */
final class Cupom
{
    /** @return list<array{t:string,dupla:bool}> */
    public static function linhas(array $venda, array $loja): array
    {
        $w = (int) Config::get()['cupom_colunas'];
        $o = [];
        $add = static function (string $t, bool $dupla = false) use (&$o): void {
            $o[] = ['t' => $t, 'dupla' => $dupla];
        };

        $add(self::centra($loja['razao_social'], $w), true);
        $linhaEnd = trim($loja['endereco'] . ' ' . $loja['numero']);
        if ($linhaEnd !== '') {
            $add(self::centra($linhaEnd, $w));
        }
        $add(self::centra(trim($loja['cidade'] . ' ' . $loja['uf'] . ' ' . $loja['bairro']), $w));
        $add('');
        $add(self::lr('CUPOM Nº ' . str_pad((string) $venda['id'], 6, '0', STR_PAD_LEFT), self::dataHora($venda['criado_em']), $w));
        $add(str_repeat('-', $w));
        $add('ITEM CODIGO        DESCRICAO');
        $add(self::lr('QTD x UNIT', 'SUBTOTAL', $w));
        $add(str_repeat('-', $w));

        foreach ($venda['itens'] as $it) {
            $add(
                str_pad((string) $it['seq'], 3, '0', STR_PAD_LEFT) . ' '
                . mb_str_pad($it['cod'], 13) . ' '
                . mb_substr($it['nome'], 0, 27)
            );
            $add(self::lr($it['qtd'] . ' x ' . Money::fmt($it['valor']), Money::fmt($it['subtotal']), $w));
        }

        $add(str_repeat('-', $w));
        $add(self::lr('TOTAL R$', Money::fmt($venda['total']), $w), true);

        foreach ($venda['pagamentos'] as $pg) {
            $add(self::lr(Forma::from($pg['forma'])->rotulo() . ' R$', Money::fmt($pg['valor']), $w));
        }
        if ($venda['troco'] > 0) {
            $add(self::lr('Troco    R$', Money::fmt($venda['troco']), $w));
        }

        $add(str_repeat('_', $w));
        $add(self::lr('', substr(self::dataHora($venda['criado_em']), 11), $w));
        $add(self::centra($loja['mensagem'], $w), true);

        return $o;
    }

    private static function centra(string $t, int $w): string
    {
        $t = mb_substr($t, 0, $w);
        return str_repeat(' ', intdiv($w - mb_strlen($t), 2)) . $t;
    }

    private static function lr(string $esq, string $dir, int $w): string
    {
        $espaco = max(1, $w - mb_strlen($esq) - mb_strlen($dir));
        return $esq . str_repeat(' ', $espaco) . $dir;
    }

    private static function dataHora(string $iso): string
    {
        return date('d/m/Y H:i:s', strtotime($iso) ?: 0);
    }
}
