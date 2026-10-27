-- SPDV - esquema SQLite
CREATE TABLE IF NOT EXISTS produtos (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    cod             TEXT    NOT NULL UNIQUE CHECK (length(cod) BETWEEN 1 AND 13),
    nome            TEXT    NOT NULL CHECK (length(nome) BETWEEN 1 AND 27),
    valor_centavos  INTEGER NOT NULL CHECK (valor_centavos >= 0),
    criado_em       TEXT    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS configuracao (
    id            INTEGER PRIMARY KEY CHECK (id = 1),
    razao_social  TEXT NOT NULL DEFAULT '',
    endereco      TEXT NOT NULL DEFAULT '',
    numero        TEXT NOT NULL DEFAULT '',
    bairro        TEXT NOT NULL DEFAULT '',
    cidade        TEXT NOT NULL DEFAULT '',
    uf            TEXT NOT NULL DEFAULT '',
    mensagem      TEXT NOT NULL DEFAULT ''
);

INSERT OR IGNORE INTO configuracao (id) VALUES (1);

CREATE TABLE IF NOT EXISTS vendas (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    criado_em       TEXT    NOT NULL,
    total_centavos  INTEGER NOT NULL,
    troco_centavos  INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS venda_itens (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    venda_id            INTEGER NOT NULL REFERENCES vendas(id) ON DELETE CASCADE,
    seq                 INTEGER NOT NULL,
    cod                 TEXT    NOT NULL,
    nome                TEXT    NOT NULL,
    qtd                 INTEGER NOT NULL,
    valor_centavos      INTEGER NOT NULL,
    subtotal_centavos   INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_venda_itens_venda ON venda_itens (venda_id);

CREATE TABLE IF NOT EXISTS pagamentos (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    venda_id        INTEGER NOT NULL REFERENCES vendas(id) ON DELETE CASCADE,
    forma           TEXT    NOT NULL CHECK (forma IN ('dinheiro','cheque','cartao','fiado')),
    valor_centavos  INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_pagamentos_venda ON pagamentos (venda_id);
