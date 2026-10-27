<?php
declare(strict_types=1);

namespace Spdv;

final class Config
{
    private static ?array $cfg = null;

    public static function get(): array
    {
        return self::$cfg ??= require dirname(__DIR__) . '/config.php';
    }
}
