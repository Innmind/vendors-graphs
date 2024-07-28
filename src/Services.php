<?php
declare(strict_types = 1);

namespace App;

use App\Infrastructure\LoadPackages;
use Innmind\DI\Service;
use Formal\ORM\Manager;
use Innmind\Xml\Reader;
use Innmind\Filesystem\Adapter;
use Innmind\DependencyGraph\Loader\{
    VendorDependencies,
    Vendor,
    Package,
};

/**
 * @template S of object
 * @implements Service<S>
 */
enum Services implements Service
{
    case orm;
    case reader;
    case storage;
    case loadVendorDependencies;
    case loadVendor;
    case loadPackage;
    case loadPackages;

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

    /**
     * @return self<VendorDependencies>
     */
    public static function loadVendorDependencies(): self
    {
        /** @var self<VendorDependencies> */
        return self::loadVendorDependencies;
    }

    /**
     * @return self<Vendor>
     */
    public static function loadVendor(): self
    {
        /** @var self<Vendor> */
        return self::loadVendor;
    }

    /**
     * @return self<Package>
     */
    public static function loadPackage(): self
    {
        /** @var self<Package> */
        return self::loadPackage;
    }

    /**
     * @return self<LoadPackages>
     */
    public static function loadPackages(): self
    {
        /** @var self<LoadPackages> */
        return self::loadPackages;
    }
}
