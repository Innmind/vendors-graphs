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
use Innmind\UI\Theme;
use Innmind\Http\{
    Response,
    Response\StatusCode,
    Headers,
    Header\ContentType,
    Header\Location,
};
use Innmind\Specification\{
    Comparator\Property,
    Sign,
};
use Innmind\Url\{
    Url,
    Path,
};
use Innmind\Immutable\Map;

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
            ))
            ->route(
                Routes::index->toString(),
                static fn($request, $_, $get) => Response::of(
                    StatusCode::ok,
                    $request->protocolVersion(),
                    null,
                    View\Main::of($get(Services::orm())),
                ),
            )
            ->route(
                Routes::vendor->toString(),
                static fn($request, $variables, $get) => $get(Services::orm())
                    ->repository(Domain\Vendor::class)
                    ->matching(Property::of(
                        'name',
                        Sign::equality,
                        $variables->get('name'),
                    ))
                    ->take(1)
                    ->first()
                    ->match(
                        static fn($vendor) => Response::of(
                            StatusCode::ok,
                            $request->protocolVersion(),
                            null,
                            View\Vendor::of(
                                $get(Services::storage()),
                                $vendor,
                            ),
                        ),
                        static fn() => Response::of(
                            StatusCode::found,
                            $request->protocolVersion(),
                            Headers::of(
                                Location::of(Routes::index->template()->expand(Map::of())),
                            ),
                        ),
                    ),
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
