<?php

namespace App\Service;

use DateTime;
use DateTimeInterface;

class ConverterService
{
    public const SIMPLE_DATETIME_FORMAT = 'H:i:s d.m.Y';

    public static function reformatDateTime(
        string $time,
        string $actual = DateTimeInterface::ATOM,
        string $new = self::SIMPLE_DATETIME_FORMAT
    ) {
        $time = DateTime::createFromFormat($actual, $time);
        return $time === false ? false : $time->format($new);
    }
}
