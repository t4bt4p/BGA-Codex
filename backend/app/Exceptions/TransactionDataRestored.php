<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class TransactionDataRestored extends HttpException
{
    public function __construct()
    {
        parent::__construct(409, 'พบข้อผิดพลาดในประวัติธุรกรรม ระบบกู้ข้อมูลจากหลักฐานแล้ว กรุณาทำรายการอีกครั้ง');
    }
}
