<?php
declare(strict_types=1);

namespace Spdv;

final class Vendas
{
    /**
     * Fecha a venda. Os preços vêm SEMPRE do banco (o navegador só informa código e quantidade).
     *
     * @param list<array{cod:string,qtd:int|string}>        $itens
     * @param list<array{forma:string,valor:int|string}>    $pagamentos
     */
    public static function finalizar(array $itens, array $pagamentos): int
    {
        if ($itens === []) {
            throw new HttpException(422, 'Venda sem itens.');
        }

        $linhas = [];
        $total  = 0;
        foreach ($itens as $i => $it) {
            $qtd = filter_var($it['qtd'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 9999]]);
            if ($qtd === false) {
                throw new HttpException(422, 'Quantidade inválida (1 a 9999).');
            }
            $cod = Produtos::normalizaCod((string) ($it['cod'] ?? ''));
            $p   = Produtos::porCodigo($cod) ?? throw new HttpException(404, "Produto não cadastrado: $cod");
            $sub = $p['valor'] * $qtd;
            $total += $sub;
            $linhas[] = [$i + 1, $p['cod'], $p['nome'], $qtd, $p['valor'], $sub];
        }

        $pago     = 0;
        $dinheiro = 0;
        $pags     = [];
        foreach ($pagamentos as $pg) {
            $forma = Forma::tryFrom((string) ($pg['forma'] ?? '')) ?? throw new HttpException(422, 'Forma de pagamento inválida.');
            $v     = Money::parse($pg['valor'] ?? '');
            if ($v <= 0) {
                throw new HttpException(422, 'Valor de pagamento deve ser maior que zero.');
            }
            $pago += $v;
            if ($forma === Forma::Dinheiro) {
                $dinheiro += $v;
            }
            $pags[] = [$forma->value, $v];
        }

        if ($pago < $total) {
            throw new HttpException(422, 'Pagamento insuficiente: faltam R$ ' . Money::fmt($total - $pago));
        }
        $troco = $pago - $total;
        if ($troco > $dinheiro) {
            throw new HttpException(422, 'Troco maior que o valor recebido em dinheiro.');
        }

        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('INSERT INTO vendas (criado_em, total_centavos, troco_centavos) VALUES (:d, :t, :tr)')
                ->execute([':d' => date('Y-m-d H:i:s'), ':t' => $total, ':tr' => $troco]);
            $vendaId = (int) $pdo->lastInsertId();

            $stI = $pdo->prepare(
                'INSERT INTO venda_itens (venda_id, seq, cod, nome, qtd, valor_centavos, subtotal_centavos)
                 VALUES (:v, :s, :c, :n, :q, :val, :sub)'
            );
            foreach ($linhas as [$seq, $cod, $nome, $qtd, $val, $sub]) {
                $stI->execute([':v' => $vendaId, ':s' => $seq, ':c' => $cod, ':n' => $nome, ':q' => $qtd, ':val' => $val, ':sub' => $sub]);
            }

            $stP = $pdo->prepare('INSERT INTO pagamentos (venda_id, forma, valor_centavos) VALUES (:v, :f, :val)');
            foreach ($pags as [$forma, $v]) {
                $stP->execute([':v' => $vendaId, ':f' => $forma, ':val' => $v]);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return $vendaId;
    }

    public static function obter(int $id): array
    {
        $pdo = Db::pdo();

        $st = $pdo->prepare('SELECT id, criado_em, total_centavos, troco_centavos FROM vendas WHERE id = :i');
        $st->execute([':i' => $id]);
        $v = $st->fetch() ?: throw new HttpException(404, 'Venda não encontrada.');

        $st = $pdo->prepare(
            'SELECT seq, cod, nome, qtd, valor_centavos, subtotal_centavos FROM venda_itens WHERE venda_id = :i ORDER BY seq'
        );
        $st->execute([':i' => $id]);
        $itens = array_map(static fn(array $r): array => [
            'seq'      => (int) $r['seq'],
            'cod'      => $r['cod'],
            'nome'     => $r['nome'],
            'qtd'      => (int) $r['qtd'],
            'valor'    => (int) $r['valor_centavos'],
            'subtotal' => (int) $r['subtotal_centavos'],
        ], $st->fetchAll());

        $st = $pdo->prepare('SELECT forma, valor_centavos FROM pagamentos WHERE venda_id = :i ORDER BY id');
        $st->execute([':i' => $id]);
        $pags = array_map(static fn(array $r): array => [
            'forma' => $r['forma'],
            'valor' => (int) $r['valor_centavos'],
        ], $st->fetchAll());

        return [
            'id'         => (int) $v['id'],
            'criado_em'  => $v['criado_em'],
            'total'      => (int) $v['total_centavos'],
            'troco'      => (int) $v['troco_centavos'],
            'itens'      => $itens,
            'pagamentos' => $pags,
        ];
    }

    public static function listar(int $limite = 50): array
    {
        $limite = max(1, min(500, $limite));
        $st = Db::pdo()->query(
            "SELECT id, criado_em, total_centavos FROM vendas ORDER BY id DESC LIMIT $limite"
        );
        return array_map(static fn(array $r): array => [
            'id'        => (int) $r['id'],
            'criado_em' => $r['criado_em'],
            'total'     => (int) $r['total_centavos'],
        ], $st->fetchAll());
    }
}
