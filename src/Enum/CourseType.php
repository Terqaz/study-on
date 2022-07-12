<?php

namespace App\Enum;

class CourseType
{
    public const FREE = 0;
    public const RENT = 1;
    public const BUY = 2;

    public const NAMES = [
        self::FREE => 'free',
        self::RENT => 'rent',
        self::BUY => 'buy',
    ];

    public const VALUES = [
        'free' => self::FREE,
        'rent' => self::RENT,
        'buy' => self::BUY,
    ];
}
