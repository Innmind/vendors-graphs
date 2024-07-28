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
    Definition\Type\PointInTimeType,
};
use Innmind\DependencyGraph\Loader;
use Innmind\DependencyGraph\Render;
use Innmind\Html\Reader\Reader;
use Innmind\Url\Path;
use Innmind\Url\Url;

final class Kernel implements Middleware
{
    public function __invoke(Application $app): Application
    {
        return $app
            ->service(Services::orm, static fn($_, $os) => Manager::filesystem(
                $os
                    ->filesystem()
                    ->mount(Path::of(__DIR__.'/../var/orm/')),
                Aggregates::of(
                    Types::of(
                        Support::class(Url::class, new ORM\UrlType),
                        PointInTimeType::of($os->clock()),
                    ),
                ),
            ))
            ->service(Services::reader, static fn() => Reader::default())
            ->service(
                Services::storage,
                static fn($_, $os) => $os
                    ->filesystem()
                    ->mount(Path::of(__DIR__.'/../var/svg/')),
            )
            ->command(static fn($get, $os) => new Command\AddVendor(
                $os->clock(),
                $os->remote()->http(),
                $get(Services::reader()),
                $get(Services::orm()),
            ))
            ->command(static fn($get, $os) => new Command\RenderVendor(
                $get(Services::orm()),
                new Loader\VendorDependencies(
                    $vendor = new Loader\Vendor(
                        $os->remote()->http(),
                        $package = new Loader\Package($os->remote()->http()),
                    ),
                    $package,
                ),
                new Loader\Dependencies($package),
                new Loader\Dependents($vendor),
                new Render(),
                $os->control()->processes(),
                $get(Services::storage()),
            ));
    }
}
