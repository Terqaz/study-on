<?php

namespace App\Enum;

class PaymentStatus
{
    public const SUCCEEDED = 0;
    public const INSUFFICIENT_FUNDS = 1;
    public const ALREADY_PAID = 2;
    public const FAILED = 3;

    public const MESSAGES = [
        self::SUCCEEDED => 'Курс успешно оплачен',
        self::INSUFFICIENT_FUNDS => 'Недостаточно средств для оплаты курса',
        self::ALREADY_PAID => 'Курс уже оплачен ранее',
        self::FAILED => 'Не удалось оплатить курс. Попробуйте позже',
    ];
}
