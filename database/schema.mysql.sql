-- SPDV - esquema MariaDB (>= 10.4)
CREATE TABLE IF NOT EXISTS produtos (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    cod             VARCHAR(13)  NOT NULL,
    nome            VARCHAR(27)  NOT NULL,
    valor_centavos  INT UNSIGNED NOT NULL,
    criado_em       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_produtos_cod (cod)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS configuracao (
    id            TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    razao_social  VARCHAR(48) NOT NULL DEFAULT '',
    endereco      VARCHAR(48) NOT NULL DEFAULT '',
    numero        VARCHAR(9)  NOT NULL DEFAULT '',
    bairro        VARCHAR(18) NOT NULL DEFAULT '',
    cidade        VARCHAR(40) NOT NULL DEFAULT '',
    uf            CHAR(2)     NOT NULL DEFAULT '',
    mensagem      VARCHAR(48) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO configuracao (id) VALUES (1);

CREATE TABLE IF NOT EXISTS vendas (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    criado_em       DATETIME     NOT NULL,
    total_centavos  INT UNSIGNED NOT NULL,
    troco_centavos  INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS venda_itens (
    id                 INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    venda_id           INT UNSIGNED NOT NULL,
    seq                SMALLINT UNSIGNED NOT NULL,
    cod                VARCHAR(13)  NOT NULL,
    nome               VARCHAR(27)  NOT NULL,
    qtd                SMALLINT UNSIGNED NOT NULL,
    valor_centavos     INT UNSIGNED NOT NULL,
    subtotal_centavos  INT UNSIGNED NOT NULL,
    KEY idx_venda_itens_venda (venda_id),
    CONSTRAINT fk_itens_venda FOREIGN KEY (venda_id) REFERENCES vendas (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pagamentos (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    venda_id        INT UNSIGNED NOT NULL,
    forma           ENUM('dinheiro','cheque','cartao','fiado') NOT NULL,
    valor_centavos  INT UNSIGNED NOT NULL,
    KEY idx_pagamentos_venda (venda_id),
    CONSTRAINT fk_pag_venda FOREIGN KEY (venda_id) REFERENCES vendas (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
