<?php
declare(strict_types=1);

namespace Spdv;

use PDO;

final class Db
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $c = Config::get()['db'];
        $opcoes = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        if ($c['driver'] === 'sqlite') {
            $dir = dirname($c['sqlite_path']);
            if (!is_dir($dir)) {
                mkdir($dir, 0750, true);
            }
            $pdo = new PDO('sqlite:' . $c['sqlite_path'], null, null, $opcoes);
            $pdo->exec('PRAGMA foreign_keys = ON');
            $pdo->exec('PRAGMA journal_mode = WAL');
        } else {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $c['host'], $c['port'], $c['name']);
            $pdo = new PDO($dsn, $c['user'], $c['pass'], $opcoes);
        }

        return self::$pdo = $pdo;
    }

    public static function driver(): string
    {
        return Config::get()['db']['driver'] === 'sqlite' ? 'sqlite' : 'mysql';
    }
}
