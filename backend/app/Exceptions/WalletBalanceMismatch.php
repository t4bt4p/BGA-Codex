<?php

namespace App\Exceptions;

use RuntimeException;

class WalletBalanceMismatch extends RuntimeException
{
    public function __construct(public readonly int $userId)
    {
        parent::__construct('Wallet balance does not match verified history');
    }
}
