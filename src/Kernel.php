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
use Innmind\DependencyGraph\{
    Loader,
    Render,
};
use Innmind\OperatingSystem\OperatingSystem\Resilient;
use Innmind\Html\Reader\Reader;
use Innmind\Url\Path;
use Innmind\Url\Url;

final class Kernel implements Middleware
{
    public function __invoke(Application $app): Application
    {
        return $app
            ->mapOperatingSystem(Resilient::of(...))
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
            ->service(
                Services::loadPackage,
                static fn($_, $os) => new Loader\Package(
                    $os->remote()->http(),
                ),
            )
            ->service(
                Services::loadVendor,
                static fn($get, $os) => new Loader\Vendor(
                    $os->remote()->http(),
                    $get(Services::loadPackage()),
                ),
            )
            ->service(
                Services::loadVendorDependencies,
                static fn($get) => new Loader\VendorDependencies(
                    $get(Services::loadVendor()),
                    $get(Services::loadPackage()),
                ),
            )
            ->service(
                Services::loadPackages,
                static fn($_, $os) => new Infrastructure\LoadPackages(
                    $os->remote()->http(),
                ),
            )
            ->command(static fn($get, $os) => new Command\AddVendor(
                $os->clock(),
                $os->remote()->http(),
                $get(Services::reader()),
                $get(Services::orm()),
                $get(Services::loadPackages()),
            ))
            ->command(static fn($get, $os) => new Command\RenderVendor(
                $get(Services::orm()),
                $get(Services::loadVendorDependencies()),
                new Loader\Dependencies($get(Services::loadPackage())),
                new Loader\Dependents($get(Services::loadVendor())),
                new Render(),
                $os->control()->processes(),
                $get(Services::storage()),
            ))
            ->command(static fn($get, $os) => new Command\UpdateVendors(
                $get(Services::orm()),
                $get(Services::loadPackages()),
                $get(Services::storage()),
            ));
    }
}
