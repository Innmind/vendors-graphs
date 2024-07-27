<?php
declare(strict_types = 1);

namespace App;

use Innmind\Framework\{
    Application,
    Middleware,
};
use Formal\ORM\{
    Manager,
    Definition\Aggregates,
    Definition\Types,
    Definition\Type\Support,
};
use Innmind\Url\Path;
use Innmind\Url\Url;

final class Kernel implements Middleware
{
    public function __invoke(Application $app): Application
    {
        return $app
            ->service('orm', static fn($_, $os) => Manager::filesystem(
                $os
                    ->filesystem()
                    ->mount(Path::of(__DIR__.'/../var/')),
                Aggregates::of(
                    Types::of(
                        Support::class(Url::class, new ORM\UrlType),
                    ),
                ),
            ));
    }
}
