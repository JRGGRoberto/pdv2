<?php
declare(strict_types=1);

namespace Spdv;

enum Forma: string
{
    case Dinheiro = 'dinheiro';
    case Cheque   = 'cheque';
    case Cartao   = 'cartao';
    case Fiado    = 'fiado';

    public function rotulo(): string
    {
        return match ($this) {
            self::Dinheiro => 'Dinheiro',
            self::Cheque   => 'Cheque',
            self::Cartao   => 'Cartao',
            self::Fiado    => 'Fiado',
        };
    }
}
