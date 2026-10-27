<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use Spdv\{Cupom, HttpException, Loja, Produtos, Vendas};

session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Strict']);
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

try {
    $metodo = $_SERVER['REQUEST_METHOD'];
    $acao   = (string) ($_GET['a'] ?? '');
    $in     = [];

    if ($metodo === 'POST') {
        $token = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!hash_equals((string) ($_SESSION['csrf'] ?? ''), $token)) {
            throw new HttpException(403, 'Sessão expirada. Recarregue a página.');
        }
        $in = json_decode((string) file_get_contents('php://input'), true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($in)) {
            throw new HttpException(400, 'Corpo da requisição inválido.');
        }
    } elseif ($metodo !== 'GET') {
        throw new HttpException(405, 'Método não permitido.');
    }

    $saida = match ("$metodo $acao") {
        'GET produtos'        => ['produtos' => Produtos::listar((string) ($_GET['q'] ?? ''))],
        'GET produto'         => ['produto' => Produtos::porCodigo((string) ($_GET['cod'] ?? ''))
                                    ?? throw new HttpException(404, 'PRODUTO NAO CADASTRADO!!!')],
        'POST produto_salvar' => ['produto' => Produtos::salvar(
                                    isset($in['id']) ? (int) $in['id'] : null, $in)],
        'POST produto_excluir'=> (static function () use ($in): array {
                                    Produtos::excluir((int) ($in['id'] ?? 0));
                                    return ['ok' => true];
                                })(),
        'GET loja'            => ['loja' => Loja::obter()],
        'POST loja_salvar'    => ['loja' => Loja::salvar($in)],
        'POST venda_finalizar'=> (static function () use ($in): array {
                                    $id = Vendas::finalizar((array) ($in['itens'] ?? []), (array) ($in['pagamentos'] ?? []));
                                    $v  = Vendas::obter($id);
                                    return ['venda' => $v, 'cupom' => Cupom::linhas($v, Loja::obter())];
                                })(),
        'GET venda'           => (static function (): array {
                                    $v = Vendas::obter((int) ($_GET['id'] ?? 0));
                                    return ['venda' => $v, 'cupom' => Cupom::linhas($v, Loja::obter())];
                                })(),
        'GET vendas'          => ['vendas' => Vendas::listar(50)],
        default               => throw new HttpException(404, 'Ação desconhecida.'),
    };

    echo json_encode($saida, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (HttpException $e) {
    http_response_code($e->status);
    echo json_encode(['erro' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (JsonException) {
    http_response_code(400);
    echo json_encode(['erro' => 'JSON inválido.']);
} catch (Throwable $e) {
    error_log('[SPDV] ' . $e);
    http_response_code(500);
    echo json_encode(['erro' => 'Erro interno do servidor.']);
}
