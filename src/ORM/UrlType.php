<?php
declare(strict_types = 1);

namespace App\ORM;

use Formal\ORM\Definition\Type;
use Innmind\Url\Url;

/**
 * @psalm-immutable
 * @implements Type<Url>
 */
final class UrlType implements Type
{
    public function normalize(mixed $value): null|string|int|bool
    {
        return $value->toString();
    }

    public function denormalize(null|string|int|bool $value): mixed
    {
        if (!\is_string($value)) {
            throw new \RuntimeException;
        }

        return Url::of($value);
    }
}
