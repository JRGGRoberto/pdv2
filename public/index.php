<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Strict']);
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'");
$v = (string) filemtime(__DIR__ . '/assets/app.js');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf" content="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES) ?>">
  <title>SPDV · JRGG Roberto Sistemas</title>
  <link rel="stylesheet" href="assets/app.css?v=<?= $v ?>">
</head>
<body>
  <header class="topo">
    <div class="marca">
      <strong>JRGG Roberto Sistemas</strong>
      <small>SPDV · ponto de venda</small>
    </div>
    <nav id="menu">
      <a href="#vendas">Vendas</a>
      <a href="#produtos">Produtos</a>
      <a href="#cabecalho">Cabeçalho do cupom</a>
      <a href="#historico">Histórico</a>
    </nav>
  </header>

  <main id="view" class="app" tabindex="-1"></main>

  <footer class="rodape">
    <small>copyright (c) 1982 - 2006 JRGG Roberto · versão web (PHP 8.5, SQLite/MariaDB)</small>
  </footer>

  <!-- Cupom (visualização / impressão) -->
  <dialog id="dlgCupom">
    <pre id="cupom" class="cupom"></pre>
    <div class="acoes">
      <button type="button" id="btnImprimir" class="sec">Imprimir</button>
      <button type="button" id="btnNovo" class="pri">Caixa disponível (Enter)</button>
    </div>
  </dialog>

  <!-- Pagamento -->
  <dialog id="dlgPag">
    <h2>Forma de pagamento</h2>
    <dl class="totais">
      <div><dt>Total R$</dt><dd id="pgTotal">0,00</dd></div>
      <div><dt>Pago R$</dt><dd id="pgPago">0,00</dd></div>
      <div><dt id="pgRotulo">Faltando R$</dt><dd id="pgFalta" class="destaque">0,00</dd></div>
    </dl>
    <label for="pgValor">Pagar R$</label>
    <input id="pgValor" inputmode="decimal" autocomplete="off">
    <div class="formas">
      <button type="button" data-forma="dinheiro">F1 · Dinheiro</button>
      <button type="button" data-forma="cheque">F2 · Cheque</button>
      <button type="button" data-forma="cartao">F3 · Cartão</button>
      <button type="button" data-forma="fiado">F4 · Fiado</button>
    </div>
    <ul id="pgLista" class="lista-pag"></ul>
    <p id="pgErro" class="erro" role="alert"></p>
    <div class="acoes">
      <button type="button" id="pgCancelar" class="sec">Voltar à venda</button>
      <button type="button" id="pgConcluir" class="pri" disabled>Concluir venda</button>
    </div>
  </dialog>

  <!-- Produto (cadastrar / editar) -->
  <dialog id="dlgProd">
    <form id="formProd" method="dialog" autocomplete="off">
      <h2 id="prodTitulo">Cadastrar produto</h2>
      <input type="hidden" name="id">
      <label>Código (até 13) <input name="cod" maxlength="13" required></label>
      <label>Nome (até 27) <input name="nome" maxlength="27" required></label>
      <label>Valor R$ <input name="valor" inputmode="decimal" required></label>
      <p id="prodErro" class="erro" role="alert"></p>
      <div class="acoes">
        <button type="button" id="prodCancelar" class="sec">Cancelar</button>
        <button type="submit" class="pri">Salvar</button>
      </div>
    </form>
  </dialog>

  <script src="assets/app.js?v=<?= $v ?>"></script>
</body>
</html>
