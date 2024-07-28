<?php
declare(strict_types = 1);

namespace App;

use Innmind\DI\Service;
use Formal\ORM\Manager;
use Innmind\Xml\Reader;
use Innmind\Filesystem\Adapter;

/**
 * @template S of object
 * @implements Service<S>
 */
enum Services implements Service
{
    case orm;
    case reader;
    case storage;

    /**
     * @return self<Manager>
     */
    public static function orm(): self
    {
        /** @var self<Manager> */
        return self::orm;
    }

    /**
     * @return self<Reader>
     */
    public static function reader(): self
    {
        /** @var self<Reader> */
        return self::reader;
    }

    /**
     * @return self<Adapter>
     */
    public static function storage(): self
    {
        /** @var self<Adapter> */
        return self::storage;
    }
}
