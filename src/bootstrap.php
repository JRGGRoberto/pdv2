<?php
declare(strict_types=1);

spl_autoload_register(static function (string $classe): void {
    if (!str_starts_with($classe, 'Spdv\\')) {
        return;
    }
    $arquivo = __DIR__ . '/' . substr($classe, 5) . '.php';
    if (is_file($arquivo)) {
        require $arquivo;
    }
});

date_default_timezone_set(Spdv\Config::get()['timezone']);
