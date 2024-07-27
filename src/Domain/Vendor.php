<?php
declare(strict_types = 1);

namespace App\Domain;

use Formal\ORM\{
    Definition\Contains,
    Id,
};
use Innmind\Url\Url;
use Innmind\Immutable\Set;

/**
 * @psalm-immutable
 */
final class Vendor
{
    /**
     * @param Id<self> $id
     * @param non-empty-string $name
     * @param Set<Package> $packages
     */
    private function __construct(
        private Id $id,
        private string $name,
        private Url $image,
        #[Contains(Package::class)]
        private Set $packages,
    ) {
    }

    /**
     * @param non-empty-string $name
     * @param Set<Package> $packages
     */
    public static function of(
        string $name,
        Url $image,
        Set $packages,
    ): self {
        return new self(
            Id::new(self::class),
            $name,
            $image,
            $packages,
        );
    }
}
