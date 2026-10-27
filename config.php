<?php
declare(strict_types=1);

/**
 * Configuração do SPDV.
 * Para usar MariaDB, defina SPDV_DB_DRIVER=mysql (ou altere abaixo) e preencha host/nome/usuário/senha.
 */
return [
    'db' => [
        'driver'      => getenv('SPDV_DB_DRIVER') ?: 'sqlite',      // 'sqlite' | 'mysql' (MariaDB)
        'sqlite_path' => getenv('SPDV_SQLITE_PATH') ?: __DIR__ . '/data/spdv.sqlite',
        'host'        => getenv('SPDV_DB_HOST') ?: '127.0.0.1',
        'port'        => (int) (getenv('SPDV_DB_PORT') ?: 3306),
        'name'        => getenv('SPDV_DB_NAME') ?: 'spdv',
        'user'        => getenv('SPDV_DB_USER') ?: 'spdv',
        'pass'        => getenv('SPDV_DB_PASS') ?: '',
    ],
    'cupom_colunas' => 48,               // equivale a 2 * col(24) do programa original
    'timezone'      => 'America/Sao_Paulo',
];
