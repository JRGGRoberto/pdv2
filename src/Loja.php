<?php
declare(strict_types=1);

namespace Spdv;

/** Dados do cabeçalho do cupom (era o arquivo msg.txt). */
final class Loja
{
    private const CAMPOS = [
        'razao_social' => 48,
        'endereco'     => 48,
        'numero'       => 9,
        'bairro'       => 18,
        'cidade'       => 40,
        'uf'           => 2,
        'mensagem'     => 48,
    ];

    public static function obter(): array
    {
        $r = Db::pdo()->query(
            'SELECT razao_social, endereco, numero, bairro, cidade, uf, mensagem FROM configuracao WHERE id = 1'
        )->fetch();
        return $r ?: array_fill_keys(array_keys(self::CAMPOS), '');
    }

    public static function salvar(array $d): array
    {
        $novo = [];
        foreach (self::CAMPOS as $campo => $max) {
            $v = trim((string) ($d[$campo] ?? ''));
            if ($campo === 'uf') {
                $v = mb_strtoupper($v);
            }
            if (mb_strlen($v) > $max) {
                throw new HttpException(422, "Campo '$campo' excede $max caracteres.");
            }
            $novo[$campo] = $v;
        }

        Db::pdo()->prepare(
            'UPDATE configuracao SET razao_social = :razao_social, endereco = :endereco, numero = :numero,
                    bairro = :bairro, cidade = :cidade, uf = :uf, mensagem = :mensagem WHERE id = 1'
        )->execute($novo);

        return $novo;
    }
}
