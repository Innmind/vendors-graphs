<?php
declare(strict_types = 1);

namespace App;

use Innmind\Framework\{
    Application,
    Middleware,
    Http\To,
};
use Formal\ORM\{
    Manager,
    Definition\Aggregates,
    Definition\Types,
    Definition\Type\Support,
    Definition\Type\PointInTimeType,
    Sort,
};
use Innmind\DependencyGraph\{
    Loader,
    Render,
};
use Innmind\OperatingSystem\OperatingSystem\Resilient;
use Innmind\Html\Reader\Reader;
use Innmind\UI\Theme;
use Innmind\Http\{
    Response,
    Response\StatusCode,
    Headers,
    Header\ContentType,
};
use Innmind\TimeContinuum\PointInTime;
use Innmind\Url\{
    Url,
    Path,
};

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
                        Support::class(PointInTime::class, PointInTimeType::new($os->clock())),
                    ),
                ),
            ))
            ->service(Services::reader, static fn() => Reader::default())
            ->service(Services::http, static fn($_, $os) => new Infrastructure\HttpTransport\Cache(
                $os->remote()->http(),
                $os
                    ->filesystem()
                    ->mount(Path::of(__DIR__.'/../var/cache/')),
                $os->clock(),
            ))
            ->service(
                Services::storage,
                static fn($_, $os) => $os
                    ->filesystem()
                    ->mount(Path::of(__DIR__.'/../var/svg/')),
            )
            ->service(
                Services::loadPackage,
                static fn($get) => new Loader\Package(
                    $get(Services::http()),
                ),
            )
            ->service(
                Services::loadVendor,
                static fn($get) => new Loader\Vendor(
                    $get(Services::http()),
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
                static fn($get) => new Infrastructure\LoadPackages(
                    $get(Services::http()),
                ),
            )
            ->service(
                'controller.vendor',
                static fn($get) => new Controller\Vendor(
                    $get(Services::orm()),
                    $get(Services::storage()),
                ),
            )
            ->service(
                'controller.package',
                static fn($get) => new Controller\Package(
                    $get(Services::orm()),
                    $get(Services::storage()),
                ),
            )
            ->command(static fn($get, $os) => new Command\AddVendor(
                $os->clock(),
                $get(Services::http()),
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
            ->command(static fn($get) => new Command\UpdateVendors(
                $get(Services::orm()),
                $get(Services::loadPackages()),
                $get(Services::storage()),
            ))
            ->route(
                Routes::index->toString(),
                static fn($request, $_, $get) => Response::of(
                    StatusCode::ok,
                    $request->protocolVersion(),
                    null,
                    View\Main::of(
                        $get(Services::orm())
                            ->repository(Domain\Vendor::class)
                            ->all()
                            ->sort('addedAt', Sort::asc)
                            ->sequence(),
                    ),
                ),
            )
            ->route(
                Routes::vendor->toString(),
                To::service('controller.vendor'),
            )
            ->route(
                Routes::vendorWithSize->toString(),
                To::service('controller.vendor'),
            )
            ->route(
                Routes::packageDependencies->toString(),
                To::service('controller.package'),
            )
            ->route(
                Routes::packageDependenciesWithSize->toString(),
                To::service('controller.package'),
            )
            ->route(
                Routes::packageDependents->toString(),
                To::service('controller.package'),
            )
            ->route(
                Routes::packageDependentsWithSize->toString(),
                To::service('controller.package'),
            )
            ->route(
                Routes::style->toString(),
                static fn($request, $_, $__, $os) => Theme::default
                    ->load($os->filesystem())
                    ->match(
                        static fn($content) => Response::of(
                            StatusCode::ok,
                            $request->protocolVersion(),
                            Headers::of(
                                ContentType::of('text', 'css'),
                            ),
                            $content,
                        ),
                        static fn() => Response::of(
                            StatusCode::notFound,
                            $request->protocolVersion(),
                        ),
                    ),
            );
    }
}
