<?php
declare(strict_types = 1);

namespace App\Domain;

use Formal\ORM\{
    Definition\Contains,
    Id,
};
use Innmind\Url\Url;
use Innmind\Immutable\Set;
use Innmind\TimeContinuum\Clock;
use Innmind\TimeContinuum\PointInTime;

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
        private PointInTime $addedAt,
    ) {
    }

    /**
     * @param non-empty-string $name
     * @param Set<Package> $packages
     */
    public static function of(
        Clock $clock,
        string $name,
        Url $image,
        Set $packages,
    ): self {
        return new self(
            Id::new(self::class),
            $name,
            $image,
            $packages,
            $clock->now(),
        );
    }

    /**
     * @return non-empty-string
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * @return Set<Package>
     */
    public function packages(): Set
    {
        return $this->packages;
    }

    /**
     * @param Set<Package> $packages
     */
    public function update(Set $packages): self
    {
        return new self(
            $this->id,
            $this->name,
            $this->image,
            $packages,
            $this->addedAt,
        );
    }
}
