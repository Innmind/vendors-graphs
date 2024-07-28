<?php
declare(strict_types = 1);

namespace App\Domain;

use Innmind\Url\Url;

/**
 * @psalm-immutable
 */
final class Package
{
    /**
     * @param non-empty-string $name
     */
    private function __construct(
        private string $name,
        private Url $packgist,
        private Url $github,
        private Url $ci,
        private Url $releases,
    ) {
    }

    /**
     * @psalm-pure
     *
     * @param non-empty-string $name
     */
    public static function of(
        string $name,
        Url $packgist,
        Url $github,
        Url $ci,
        Url $releases,
    ): self {
        return new self(
            $name,
            $packgist,
            $github,
            $ci,
            $releases,
        );
    }

    /**
     * @return non-empty-string
     */
    public function name(): string
    {
        return $this->name;
    }

    public function github(): Url
    {
        return $this->github;
    }
}
