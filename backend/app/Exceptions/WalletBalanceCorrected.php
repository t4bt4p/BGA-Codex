<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class WalletBalanceCorrected extends HttpException
{
    public function __construct(public readonly int $balance)
    {
        parent::__construct(409, 'พบข้อผิดพลาดของยอดเงิน ระบบปรับยอดให้ตรงกับหลักฐานแล้ว กรุณาตรวจสอบยอดและทำรายการอีกครั้ง');
    }
}
