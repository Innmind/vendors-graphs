<?php
declare(strict_types = 1);

namespace App\Domain;

enum Zoom
{
    case small;
    case medium;
    case full;

    /**
     * @return int<1, 100>
     */
    public function toInt(): int
    {
        return match ($this) {
            self::small => 25,
            self::medium => 50,
            self::full => 100,
        };
    }
}
