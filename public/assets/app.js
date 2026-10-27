'use strict';

/* ============================== utilidades ============================== */
const csrf = document.querySelector('meta[name="csrf"]').content;
const $ = (sel, raiz = document) => raiz.querySelector(sel);
const main = $('#view');

const esc = (s) => String(s).replace(/[&<>"']/g, (c) =>
  ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

const fmt = (centavos) =>
  (centavos / 100).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

/** "1.234,50" | "1234.5" -> centavos (inteiro) ou null se inválido. Espelha Money::parse do PHP. */
function parseMoney(v) {
  let s = String(v).trim();
  if (s.includes(',')) s = s.replace(/\./g, '').replace(',', '.');
  if (!/^\d{1,9}(\.\d{1,2})?$/.test(s)) return null;
  const [i, d = ''] = s.split('.');
  return Number(i) * 100 + Number(d.padEnd(2, '0'));
}

async function api(acao, { method = 'GET', params = {}, body } = {}) {
  const qs = new URLSearchParams({ a: acao, ...params });
  const resp = await fetch('api.php?' + qs, {
    method,
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
    body: body === undefined ? undefined : JSON.stringify(body),
  });
  const dados = await resp.json().catch(() => ({ erro: 'Resposta inválida do servidor.' }));
  if (!resp.ok) throw Object.assign(new Error(dados.erro || 'Erro'), { status: resp.status });
  return dados;
}

/** Bipe de erro (o sound(2000) do Pascal). */
function bipe() {
  try {
    const ctx = new (window.AudioContext || window.webkitAudioContext)();
    const osc = ctx.createOscillator();
    osc.frequency.value = 2000;
    osc.connect(ctx.destination);
    osc.start();
    setTimeout(() => { osc.stop(); ctx.close(); }, 150);
  } catch { /* sem áudio: ignora */ }
}

const FORMAS = { dinheiro: 'Dinheiro', cheque: 'Cheque', cartao: 'Cartão', fiado: 'Fiado' };

/* ============================== roteador ============================== */
const rotas = { vendas: viewVendas, produtos: viewProdutos, cabecalho: viewCabecalho, historico: viewHistorico };

function rotear() {
  const nome = location.hash.slice(1) in rotas ? location.hash.slice(1) : 'vendas';
  document.querySelectorAll('#menu a').forEach((a) => {
    if (a.getAttribute('href') === '#' + nome) a.setAttribute('aria-current', 'page');
    else a.removeAttribute('aria-current');
  });
  rotas[nome]();
}
window.addEventListener('hashchange', rotear);

/* ============================== cupom ============================== */
const dlgCupom = $('#dlgCupom');
let aoFecharCupom = null;

function mostrarCupom(linhas, { novoAtendimento = false } = {}) {
  $('#cupom').innerHTML = linhas
    .map((l) => `<span class="${l.dupla ? 'dupla' : 'l'}">${esc(l.t) || ' '}</span>`)
    .join('');
  $('#btnNovo').textContent = novoAtendimento ? 'Caixa disponível (Enter)' : 'Fechar';
  aoFecharCupom = novoAtendimento ? () => { reiniciarVenda(); } : null;
  dlgCupom.showModal();
  $('#btnNovo').focus();
}
$('#btnImprimir').addEventListener('click', () => window.print());
$('#btnNovo').addEventListener('click', () => dlgCupom.close());
dlgCupom.addEventListener('close', () => { if (aoFecharCupom) { const f = aoFecharCupom; aoFecharCupom = null; f(); } });

/* ============================== VENDAS ============================== */
const venda = { itens: [], pagamentos: [], atual: null };

function totalVenda() { return venda.itens.reduce((t, i) => t + i.valor * i.qtd, 0); }
function totalPago() { return venda.pagamentos.reduce((t, p) => t + p.valor, 0); }

function reiniciarVenda() {
  venda.itens = []; venda.pagamentos = []; venda.atual = null;
  if (location.hash.slice(1) === 'vendas' || !location.hash) viewVendas();
}

function viewVendas() {
  main.innerHTML = `
    <h1>Modo vendas</h1>
    <div class="grid-pdv">
      <section class="card" aria-label="Itens da venda">
        <div class="tabela-rolavel">
          <table>
            <thead><tr><th>#</th><th>Código</th><th>Produto</th><th class="num">Qtd</th><th class="num">Unit.</th><th class="num">Subtotal</th><th></th></tr></thead>
            <tbody id="itens"></tbody>
          </table>
        </div>
        <p id="vazio" class="dica">Nenhum item. Digite o código do produto.</p>
      </section>
      <section class="card" aria-label="Lançamento">
        <label for="cod">Código</label>
        <input id="cod" autocomplete="off" inputmode="numeric" autofocus>
        <label>Produto <input id="prodNome" readonly tabindex="-1"></label>
        <div class="linha2">
          <label for="qtd">Quantidade <input id="qtd" inputmode="numeric" value="1" disabled></label>
          <label>Valor unit. R$ <input id="valUnit" readonly tabindex="-1"></label>
        </div>
        <label>Subtotal R$ <input id="subtot" readonly tabindex="-1"></label>
        <p id="aviso" class="msg erro" role="alert"></p>
        <div class="total-grande"><small>Total</small><strong id="total">0,00</strong></div>
        <p class="dica">Enter no código vazio: fechar a venda.</p>
        <div class="acoes">
          <button type="button" id="btnCancelar" class="perigo">Cancelar venda</button>
          <button type="button" id="btnFechar" class="pri">Fechar venda</button>
        </div>
      </section>
    </div>`;

  const cod = $('#cod'), qtd = $('#qtd');

  cod.addEventListener('keydown', async (e) => {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    const codigo = cod.value.trim();
    $('#aviso').textContent = '';

    if (codigo === '') { abrirPagamento(); return; }
    try {
      const { produto } = await api('produto', { params: { cod: codigo } });
      venda.atual = produto;
      $('#prodNome').value = produto.nome;
      $('#valUnit').value = fmt(produto.valor);
      $('#subtot').value = fmt(produto.valor);
      qtd.disabled = false; qtd.value = '1'; qtd.focus(); qtd.select();
    } catch (err) {
      bipe();
      venda.atual = null;
      $('#aviso').textContent = err.status === 404 ? 'PRODUTO NAO CADASTRADO!!!' : err.message;
      cod.select();
    }
  });

  qtd.addEventListener('input', () => {
    const q = Number(qtd.value);
    if (venda.atual && Number.isInteger(q) && q > 0) $('#subtot').value = fmt(venda.atual.valor * q);
  });

  qtd.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') { limparLancamento(); return; }
    if (e.key !== 'Enter') return;
    e.preventDefault();
    const q = Number(qtd.value);
    if (!venda.atual || !Number.isInteger(q) || q < 1 || q > 9999) {
      $('#aviso').textContent = 'Quantidade inválida (1 a 9999).'; qtd.select(); return;
    }
    venda.itens.push({ cod: venda.atual.cod, nome: venda.atual.nome, valor: venda.atual.valor, qtd: q });
    limparLancamento();
    desenharItens();
  });

  $('#btnFechar').addEventListener('click', abrirPagamento);
  $('#btnCancelar').addEventListener('click', () => {
    if (venda.itens.length && !confirm('Cancelar a venda em andamento?')) return;
    reiniciarVenda();
  });

  desenharItens();
  cod.focus();
}

function limparLancamento() {
  venda.atual = null;
  for (const id of ['cod', 'prodNome', 'valUnit', 'subtot']) $('#' + id).value = '';
  const q = $('#qtd'); q.value = '1'; q.disabled = true;
  $('#cod').focus();
}

function desenharItens() {
  const corpo = $('#itens');
  if (!corpo) return;
  corpo.innerHTML = venda.itens.map((it, i) => `
    <tr>
      <td>${i + 1}</td><td>${esc(it.cod)}</td><td>${esc(it.nome)}</td>
      <td class="num">${it.qtd}</td><td class="num">${fmt(it.valor)}</td><td class="num">${fmt(it.valor * it.qtd)}</td>
      <td><button type="button" class="mini perigo" data-rm="${i}" aria-label="Remover item ${i + 1}">×</button></td>
    </tr>`).join('');
  $('#vazio').hidden = venda.itens.length > 0;
  $('#total').textContent = fmt(totalVenda());
  corpo.querySelectorAll('[data-rm]').forEach((b) =>
    b.addEventListener('click', () => { venda.itens.splice(Number(b.dataset.rm), 1); desenharItens(); $('#cod').focus(); }));
}

/* ---------- pagamento ---------- */
const dlgPag = $('#dlgPag');
const pgValor = $('#pgValor');

function abrirPagamento() {
  if (!venda.itens.length) { $('#aviso').textContent = 'Nenhum item na venda.'; return; }
  venda.pagamentos = [];
  $('#pgErro').textContent = '';
  atualizarPagamento();
  dlgPag.showModal();
  pgValor.select();
}

function atualizarPagamento() {
  const total = totalVenda(), pago = totalPago(), falta = total - pago;
  $('#pgTotal').textContent = fmt(total);
  $('#pgPago').textContent = fmt(pago);
  $('#pgRotulo').textContent = falta > 0 ? 'Faltando R$' : 'Troco R$';
  $('#pgFalta').textContent = fmt(Math.abs(falta));
  pgValor.value = falta > 0 ? fmt(falta) : '';

  $('#pgLista').innerHTML = venda.pagamentos.map((p, i) => `
    <li><span>${FORMAS[p.forma]} · R$ ${fmt(p.valor)}</span>
        <button type="button" class="mini perigo" data-rmp="${i}" aria-label="Remover pagamento ${i + 1}">×</button></li>`).join('');
  $('#pgLista').querySelectorAll('[data-rmp]').forEach((b) =>
    b.addEventListener('click', () => { venda.pagamentos.splice(Number(b.dataset.rmp), 1); atualizarPagamento(); pgValor.select(); }));

  $('#pgConcluir').disabled = !(pago >= total && trocoValido());
}

function trocoValido() {
  const troco = totalPago() - totalVenda();
  const dinheiro = venda.pagamentos.filter((p) => p.forma === 'dinheiro').reduce((t, p) => t + p.valor, 0);
  return troco <= dinheiro;
}

function addPagamento(forma) {
  const v = parseMoney(pgValor.value);
  $('#pgErro').textContent = '';
  if (v === null || v <= 0) { $('#pgErro').textContent = 'Informe um valor válido.'; pgValor.select(); return; }
  venda.pagamentos.push({ forma, valor: v });
  atualizarPagamento();
  if (totalPago() >= totalVenda() && !trocoValido()) {
    $('#pgErro').textContent = 'O troco não pode ser maior que o valor recebido em dinheiro.';
  }
  pgValor.select();
}

dlgPag.querySelectorAll('[data-forma]').forEach((b) => b.addEventListener('click', () => addPagamento(b.dataset.forma)));
dlgPag.addEventListener('keydown', (e) => {
  const mapa = { F1: 'dinheiro', F2: 'cheque', F3: 'cartao', F4: 'fiado' };
  if (mapa[e.key]) { e.preventDefault(); addPagamento(mapa[e.key]); }
  if (e.key === 'Enter' && e.target === pgValor && totalPago() >= totalVenda() && trocoValido()) {
    e.preventDefault(); concluirVenda();
  }
});
$('#pgCancelar').addEventListener('click', () => { dlgPag.close(); $('#cod')?.focus(); });
$('#pgConcluir').addEventListener('click', concluirVenda);

async function concluirVenda() {
  $('#pgConcluir').disabled = true;
  try {
    const r = await api('venda_finalizar', {
      method: 'POST',
      body: {
        itens: venda.itens.map(({ cod, qtd }) => ({ cod, qtd })),
        pagamentos: venda.pagamentos.map(({ forma, valor }) => ({ forma, valor: (valor / 100).toFixed(2) })),
      },
    });
    dlgPag.close();
    mostrarCupom(r.cupom, { novoAtendimento: true });
  } catch (err) {
    $('#pgErro').textContent = err.message;
    $('#pgConcluir').disabled = false;
  }
}

/* ============================== PRODUTOS ============================== */
async function viewProdutos() {
  main.innerHTML = `
    <h1>Configuração · Produtos</h1>
    <div class="barra">
      <input id="busca" type="search" placeholder="Buscar por código ou nome" aria-label="Buscar produto">
      <span class="espaco"></span>
      <button type="button" id="btnImpLista">Imprimir lista</button>
      <button type="button" id="btnNovoProd" class="pri">Cadastrar produto</button>
    </div>
    <section class="card tabela-rolavel">
      <table>
        <thead><tr><th>Código</th><th>Nome</th><th class="num">R$</th><th class="col-acoes"></th></tr></thead>
        <tbody id="listaProd"></tbody>
      </table>
      <p id="contProd" class="dica"></p>
    </section>`;

  let timer;
  $('#busca').addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(carregarProdutos, 200); });
  $('#btnNovoProd').addEventListener('click', () => abrirProduto(null));
  $('#btnImpLista').addEventListener('click', () => {
    document.body.classList.add('imprime-lista');
    window.addEventListener('afterprint', () => document.body.classList.remove('imprime-lista'), { once: true });
    window.print();
  });
  await carregarProdutos();
}

async function carregarProdutos() {
  const corpo = $('#listaProd');
  if (!corpo) return;
  try {
    const { produtos } = await api('produtos', { params: { q: $('#busca').value } });
    corpo.innerHTML = produtos.map((p) => `
      <tr>
        <td>${esc(p.cod)}</td><td>${esc(p.nome)}</td><td class="num">${fmt(p.valor)}</td>
        <td class="col-acoes">
          <button type="button" class="mini" data-ed="${p.id}">Editar</button>
          <button type="button" class="mini perigo" data-ex="${p.id}">Excluir</button>
        </td>
      </tr>`).join('');
    $('#contProd').textContent = `${produtos.length} produto(s)`;
    corpo.querySelectorAll('[data-ed]').forEach((b) =>
      b.addEventListener('click', () => abrirProduto(produtos.find((p) => p.id === Number(b.dataset.ed)))));
    corpo.querySelectorAll('[data-ex]').forEach((b) =>
      b.addEventListener('click', () => excluirProduto(produtos.find((p) => p.id === Number(b.dataset.ex)))));
  } catch (err) {
    corpo.innerHTML = `<tr><td colspan="4" class="erro">${esc(err.message)}</td></tr>`;
  }
}

const dlgProd = $('#dlgProd'), formProd = $('#formProd');

function abrirProduto(p) {
  formProd.reset();
  $('#prodErro').textContent = '';
  $('#prodTitulo').textContent = p ? 'Editar produto' : 'Cadastrar produto';
  formProd.id.value = p ? p.id : '';
  if (p) { formProd.cod.value = p.cod; formProd.nome.value = p.nome; formProd.valor.value = fmt(p.valor); }
  dlgProd.showModal();
  formProd.cod.focus();
}
$('#prodCancelar').addEventListener('click', () => dlgProd.close());
formProd.addEventListener('submit', async (e) => {
  e.preventDefault();
  const d = Object.fromEntries(new FormData(formProd));
  try {
    await api('produto_salvar', {
      method: 'POST',
      body: { id: d.id === '' ? null : Number(d.id), cod: d.cod, nome: d.nome, valor: d.valor },
    });
    dlgProd.close();
    carregarProdutos();
  } catch (err) {
    $('#prodErro').textContent = err.message;
  }
});

async function excluirProduto(p) {
  if (!p || !confirm(`Excluir "${p.nome}"? Esta ação não pode ser desfeita.`)) return;
  try { await api('produto_excluir', { method: 'POST', body: { id: p.id } }); carregarProdutos(); }
  catch (err) { alert(err.message); }
}

/* ============================== CABEÇALHO ============================== */
async function viewCabecalho() {
  main.innerHTML = `<h1>Configuração · Cabeçalho do cupom</h1><p class="dica">Carregando…</p>`;
  let loja;
  try { ({ loja } = await api('loja')); } catch (err) { main.innerHTML = `<p class="erro">${esc(err.message)}</p>`; return; }

  main.innerHTML = `
    <h1>Configuração · Cabeçalho do cupom</h1>
    <div class="grid-pdv">
      <form id="formLoja" class="card" autocomplete="off">
        <label>Razão social <input name="razao_social" maxlength="48" value="${esc(loja.razao_social)}"></label>
        <div class="linha2">
          <label>Endereço <input name="endereco" maxlength="48" value="${esc(loja.endereco)}"></label>
          <label>Número <input name="numero" maxlength="9" value="${esc(loja.numero)}"></label>
        </div>
        <div class="linha2">
          <label>Bairro <input name="bairro" maxlength="18" value="${esc(loja.bairro)}"></label>
          <label>Cidade <input name="cidade" maxlength="40" value="${esc(loja.cidade)}"></label>
        </div>
        <label>UF <input name="uf" maxlength="2" value="${esc(loja.uf)}"></label>
        <label>Mensagem final <input name="mensagem" maxlength="48" value="${esc(loja.mensagem)}"></label>
        <p id="lojaMsg" class="msg" role="status"></p>
        <div class="acoes"><button type="submit" class="pri">Salvar</button></div>
      </form>
      <section class="card"><h2>Prévia</h2><pre id="previa" class="cupom"></pre></section>
    </div>`;

  const form = $('#formLoja');
  const centra = (t) => t.slice(0, 48).padStart(Math.floor((48 - t.slice(0, 48).length) / 2) + t.slice(0, 48).length);
  const previa = () => {
    const f = Object.fromEntries(new FormData(form));
    $('#previa').innerHTML = [
      `<span class="dupla">${esc(centra(f.razao_social))}</span>`,
      `<span class="l">${esc(centra((f.endereco + ' ' + f.numero).trim()))}</span>`,
      `<span class="l">${esc(centra((f.cidade + ' ' + f.uf.toUpperCase() + ' ' + f.bairro).trim()))}</span>`,
      `<span class="l">${'-'.repeat(48)}</span>`,
      `<span class="dupla">${esc(centra(f.mensagem))}</span>`,
    ].join('');
  };
  form.addEventListener('input', previa);
  previa();

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const msg = $('#lojaMsg'); msg.className = 'msg';
    try {
      await api('loja_salvar', { method: 'POST', body: Object.fromEntries(new FormData(form)) });
      msg.textContent = 'Alterado com sucesso!'; msg.classList.add('ok');
    } catch (err) { msg.textContent = err.message; msg.classList.add('erro'); }
  });
}

/* ============================== HISTÓRICO ============================== */
async function viewHistorico() {
  main.innerHTML = `
    <h1>Histórico de vendas</h1>
    <section class="card tabela-rolavel">
      <table>
        <thead><tr><th>Cupom</th><th>Data/hora</th><th class="num">Total R$</th><th></th></tr></thead>
        <tbody id="listaVendas"><tr><td colspan="4" class="dica">Carregando…</td></tr></tbody>
      </table>
    </section>`;
  try {
    const { vendas } = await api('vendas');
    $('#listaVendas').innerHTML = vendas.length ? vendas.map((v) => `
      <tr>
        <td>${String(v.id).padStart(6, '0')}</td>
        <td>${esc(new Date(v.criado_em.replace(' ', 'T')).toLocaleString('pt-BR'))}</td>
        <td class="num">${fmt(v.total)}</td>
        <td><button type="button" class="mini" data-v="${v.id}">Ver / reimprimir</button></td>
      </tr>`).join('') : '<tr><td colspan="4" class="dica">Nenhuma venda registrada.</td></tr>';
    document.querySelectorAll('[data-v]').forEach((b) => b.addEventListener('click', async () => {
      try { const r = await api('venda', { params: { id: b.dataset.v } }); mostrarCupom(r.cupom); }
      catch (err) { alert(err.message); }
    }));
  } catch (err) {
    $('#listaVendas').innerHTML = `<tr><td colspan="4" class="erro">${esc(err.message)}</td></tr>`;
  }
}

rotear();
