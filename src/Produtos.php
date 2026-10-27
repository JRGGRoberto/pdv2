<?php
declare(strict_types=1);

namespace Spdv;

final class Produtos
{
    public static function normalizaCod(string $cod): string
    {
        return mb_strtoupper(trim($cod));
    }

    /** @return list<array{id:int,cod:string,nome:string,valor:int}> */
    public static function listar(string $q = ''): array
    {
        $sql  = 'SELECT id, cod, nome, valor_centavos FROM produtos';
        $args = [];
        $q    = trim($q);
        if ($q !== '') {
            $sql .= " WHERE cod LIKE :q1 ESCAPE '!' OR nome LIKE :q2 ESCAPE '!'";
            $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q) . '%';
            $args = [':q1' => $like, ':q2' => $like];
        }
        $st = Db::pdo()->prepare($sql . ' ORDER BY cod');
        $st->execute($args);
        return array_map(self::linha(...), $st->fetchAll());
    }

    public static function porCodigo(string $cod): ?array
    {
        $st = Db::pdo()->prepare('SELECT id, cod, nome, valor_centavos FROM produtos WHERE cod = :c');
        $st->execute([':c' => self::normalizaCod($cod)]);
        $r = $st->fetch();
        return $r ? self::linha($r) : null;
    }

    public static function salvar(?int $id, array $d): array
    {
        $cod  = self::normalizaCod((string) ($d['cod'] ?? ''));
        $nome = mb_strtoupper(trim((string) ($d['nome'] ?? '')));
        $val  = Money::parse($d['valor'] ?? '');

        if ($cod === '' || mb_strlen($cod) > 13) {
            throw new HttpException(422, 'Código deve ter de 1 a 13 caracteres.');
        }
        if ($nome === '' || mb_strlen($nome) > 27) {
            throw new HttpException(422, 'Nome deve ter de 1 a 27 caracteres.');
        }

        $pdo = Db::pdo();
        $st  = $pdo->prepare('SELECT id FROM produtos WHERE cod = :c AND id <> :i');
        $st->execute([':c' => $cod, ':i' => $id ?? 0]);
        if ($st->fetch()) {
            throw new HttpException(409, 'Produto já cadastrado!');
        }

        if ($id === null) {
            $pdo->prepare('INSERT INTO produtos (cod, nome, valor_centavos) VALUES (:c, :n, :v)')
                ->execute([':c' => $cod, ':n' => $nome, ':v' => $val]);
            $id = (int) $pdo->lastInsertId();
        } else {
            $st = $pdo->prepare('UPDATE produtos SET cod = :c, nome = :n, valor_centavos = :v WHERE id = :i');
            $st->execute([':c' => $cod, ':n' => $nome, ':v' => $val, ':i' => $id]);
            $chk = $pdo->prepare('SELECT 1 FROM produtos WHERE id = :i');
            $chk->execute([':i' => $id]);
            if (!$chk->fetch()) {
                throw new HttpException(404, 'Produto não encontrado.');
            }
        }

        return ['id' => $id, 'cod' => $cod, 'nome' => $nome, 'valor' => $val];
    }

    public static function excluir(int $id): void
    {
        $st = Db::pdo()->prepare('DELETE FROM produtos WHERE id = :i');
        $st->execute([':i' => $id]);
        if ($st->rowCount() === 0) {
            throw new HttpException(404, 'Produto não encontrado.');
        }
    }

    private static function linha(array $r): array
    {
        return [
            'id'    => (int) $r['id'],
            'cod'   => $r['cod'],
            'nome'  => $r['nome'],
            'valor' => (int) $r['valor_centavos'],
        ];
    }
}
