# SPDV — conversão de Pascal (SPDVJRGG.pas) para PHP 8.5 + HTML5/JS/CSS

Ponto de venda (PDV) com cadastro de produtos, cabeçalho do cupom, vendas com múltiplas formas de
pagamento e impressão do cupom. Banco: **SQLite** (padrão) ou **MariaDB**.

## Requisitos
- PHP 8.5 (usa `mb_str_pad`, disponível desde 8.3) com extensões `pdo_sqlite` ou `pdo_mysql`, e `mbstring`.

## Instalação rápida (SQLite)
```bash
php bin/install.php          # cria data/spdv.sqlite e importa legado/prod.txt e legado/msg.txt
php -S localhost:8080 -t public
# abra http://localhost:8080
```

## MariaDB
```sql
CREATE DATABASE spdv CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'spdv'@'localhost' IDENTIFIED BY 'senha';
GRANT ALL ON spdv.* TO 'spdv'@'localhost';
```
```bash
SPDV_DB_DRIVER=mysql SPDV_DB_NAME=spdv SPDV_DB_USER=spdv SPDV_DB_PASS=senha php bin/install.php
```
Use as mesmas variáveis de ambiente no servidor web (ou edite `config.php`).

## Produção (Apache/Nginx)
Aponte o *DocumentRoot* para a pasta `public/`. Nada fora dela deve ser acessível pela web
(`config.php`, `data/`, `src/`, `legado/`).

## Estrutura
```
public/index.php        página (HTML5) + token CSRF
public/api.php          API JSON (produtos, loja, vendas)
public/assets/app.js    interface (vendas, produtos, cabeçalho, histórico)
public/assets/app.css   estilos (inclui impressão em bobina 80 mm)
src/                    Db, Money, Produtos, Loja, Vendas, Cupom, Forma
database/               schema.sqlite.sql  e  schema.mysql.sql
bin/install.php         cria tabelas e importa os arquivos legados
legado/                 SPDVJRGG.pas, prod.txt, msg.txt originais
```

## Mapeamento Pascal → web
| Original | Agora |
|---|---|
| `prod.txt` (vetor de 100, busca binária, `ordena`) | tabela `produtos` (sem limite de 100; índice único em `cod`) |
| `msg.txt` (4 linhas) | tabela `configuracao` (razão, endereço, número, bairro, cidade, UF, mensagem) |
| MenuP / MenuConf / MenuProdu | abas Vendas, Produtos, Cabeçalho, Histórico |
| `CadastProd`, `ExclEdtProd`, `editprod`, `exclprod` | diálogo Cadastrar/Editar e botão Excluir |
| `ListaProd` (tela / impressora) | tabela de produtos + botão "Imprimir lista" |
| `vender` / `telavenda` | aba Vendas (Enter no código vazio fecha a venda) |
| `fechamento` (Dinheiro/Cheque/Cartão/Fiado) | diálogo de pagamento (F1–F4), com pagamentos parciais/mistos |
| `writeln(lst, ...)` + ESC/POS `fontAlt2` | cupom de 48 colunas; "altura dupla" em negrito/linha alta; `window.print()` |
| `sound(2000)` | bipe via Web Audio |
| `horas` | data/hora no cupom (`d/m/Y H:i:s`) |

## Diferenças e correções em relação ao original
- **Valores em centavos (inteiros)**, não `real`: sem erro de arredondamento.
- **Preço sempre vem do banco** na hora de fechar a venda; o navegador só envia código e quantidade.
- **Cálculo de pagamento reescrito.** No Pascal, `totall` virava `pag - totall` e `pag := totall + 1` para
  cheque/cartão/fiado; o troco usava `pag - totall` e a repetição dependia desses truques. Agora:
  soma dos pagamentos ≥ total; troco = soma − total, e só pode existir se houve dinheiro suficiente.
- **Busca binária** (que não tratava vetor vazio nem o último elemento direito) virou consulta SQL.
- Vendas, itens e pagamentos agora são **gravados** (histórico e reimpressão); o original só imprimia.
- Proteções novas: consultas preparadas (PDO), escape de HTML, CSRF, CSP.
- `msg.txt` de exemplo tem 3 linhas (falta a de endereço). O importador trata: razão / "cidade UF bairro" / mensagem.

## O que não dá para fazer num navegador
- **Abrir a gaveta** (`abregaveta`, ESC/POS na LPT1) e comandos ESC/POS crus: navegador não acessa porta
  paralela/serial. Opções: imprimir pelo driver da impressora com "abrir gaveta antes/depois de imprimir"
  (configuração do driver Bematech/Epson), ou usar um agente local (ex.: QZ Tray) chamando esta API.
- Impressão: `window.print()` usa o driver instalado; ajuste a escala em 100 % e margens mínimas.

## Segurança
Como o original, **não há login**. Se for acessível além do balcão, coloque autenticação
(ex.: senha HTTP básica no servidor) antes de expor.
