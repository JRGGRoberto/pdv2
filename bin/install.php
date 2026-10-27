<?php
declare(strict_types=1);

/**
 * Cria as tabelas e (opcionalmente) importa legado/prod.txt e legado/msg.txt.
 *
 *   php bin/install.php                 # cria o esquema e importa os arquivos legados se o banco estiver vazio
 *   php bin/install.php --sem-importar  # só cria o esquema
 *   php bin/install.php --prod=/caminho/prod.txt --msg=/caminho/msg.txt
 */

if (PHP_SAPI !== 'cli') {
    exit("Execute pela linha de comando.\n");
}

require __DIR__ . '/../src/bootstrap.php';

use Spdv\{Db, Loja, Money, Produtos};

$raiz = dirname(__DIR__);
$opt  = getopt('', ['sem-importar', 'prod::', 'msg::']);

$pdo = Db::pdo();
$sql = file_get_contents($raiz . '/database/schema.' . Db::driver() . '.sql');
$sql = preg_replace('/^\s*--.*$/m', '', $sql);
foreach (array_filter(array_map('trim', explode(';', $sql))) as $comando) {
    $pdo->exec($comando);
}
echo "Esquema criado (" . Db::driver() . ").\n";

if (isset($opt['sem-importar'])) {
    exit(0);
}

/** @return list<string> linhas do arquivo legado em UTF-8 (o original era DOS/CP850). */
function lerLegado(string $arquivo): array
{
    if (!is_file($arquivo)) {
        return [];
    }
    $txt = (string) file_get_contents($arquivo);
    if (!mb_check_encoding($txt, 'UTF-8')) {
        $txt = mb_convert_encoding($txt, 'UTF-8', 'CP850');
    }
    return preg_split('/\R/u', $txt);
}

// ---- produtos: trios cod / nome / valor, até cod vazio (como InicioProd) ----
$arqProd = $opt['prod'] ?? $raiz . '/legado/prod.txt';
$vazio   = (int) $pdo->query('SELECT COUNT(*) FROM produtos')->fetchColumn() === 0;
if ($vazio && is_file($arqProd)) {
    $l = lerLegado($arqProd);
    $n = 0;
    for ($i = 0; $i + 2 < count($l); $i += 3) {
        $cod = trim($l[$i]);
        if ($cod === '') {
            break;
        }
        Produtos::salvar(null, ['cod' => $cod, 'nome' => trim($l[$i + 1]), 'valor' => trim($l[$i + 2])]);
        $n++;
    }
    echo "$n produto(s) importado(s) de $arqProd\n";
}

// ---- cabeçalho do cupom ----
$arqMsg = $opt['msg'] ?? $raiz . '/legado/msg.txt';
$loja   = Loja::obter();
if ($loja['razao_social'] === '' && is_file($arqMsg)) {
    $l = array_values(array_filter(array_map('trim', lerLegado($arqMsg)), static fn(string $x): bool => $x !== ''));

    // Original: razão / "endereço número" / "cidade UF bairro" / mensagem.
    // O msg.txt de exemplo tem só 3 linhas (sem a linha de endereço): razão / cidade UF bairro / mensagem.
    if (count($l) >= 4) {
        [$razao, $ende, $cidLinha, $msg] = $l;
    } else {
        [$razao, $cidLinha, $msg] = array_pad($l, 3, '');
        $ende = '';
    }

    $cidade = $cidLinha; $uf = ''; $bairro = '';
    if (preg_match('/^(.*)\s([A-Z]{2})\s(.+)$/u', $cidLinha, $m)) {
        [, $cidade, $uf, $bairro] = $m;
    }

    Loja::salvar([
        'razao_social' => $razao, 'endereco' => $ende, 'numero' => '',
        'bairro' => $bairro, 'cidade' => $cidade, 'uf' => $uf, 'mensagem' => $msg,
    ]);
    echo "Cabeçalho do cupom importado de $arqMsg\n";
}

echo "Pronto.\n";
