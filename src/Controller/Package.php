<?php
declare(strict_types = 1);

namespace App\Controller;

use App\{
    Domain,
    View,
    Routes,
};
use Formal\ORM\Manager;
use Innmind\Filesystem\Adapter;
use Innmind\Http\{
    ServerRequest,
    Response,
    Response\StatusCode,
    Headers,
    Header\Location,
};
use Innmind\Router\Route\Variables;
use Innmind\Specification\{
    Comparator\Property,
    Sign,
};
use Innmind\Immutable\{
    Map,
    Str,
};

final class Package
{
    public function __construct(
        private Manager $orm,
        private Adapter $storage,
    ) {
    }

    public function __invoke(ServerRequest $request, Variables $variables): Response
    {
        $package = $variables->get('package');

        if ($package === '') {
            return Response::of(
                StatusCode::found,
                $request->protocolVersion(),
                Headers::of(
                    Location::of(
                        Routes::vendor->template()->expand(Map::of(
                            ['name', $variables->get('vendor')],
                        )),
                    ),
                ),
            );
        }

        return $this
            ->orm
            ->repository(Domain\Vendor::class)
            ->matching(Property::of(
                'name',
                Sign::equality,
                $variables->get('vendor'),
            ))
            ->take(1)
            ->first()
            ->match(
                fn($vendor) => Response::of(
                    StatusCode::ok,
                    $request->protocolVersion(),
                    null,
                    View\Package::of(
                        $this->storage,
                        $vendor,
                        $package,
                        match (Str::of($request->url()->path()->toString())->contains('dependencies')) {
                            true => Domain\Direction::dependencies,
                            false => Domain\Direction::dependents,
                        },
                        $variables
                            ->maybe('size')
                            ->match(
                                static fn($size) => match ($size) {
                                    'small' => Domain\Zoom::small,
                                    'medium' => Domain\Zoom::medium,
                                    default => Domain\Zoom::full,
                                },
                                static fn() => Domain\Zoom::full,
                            ),
                    ),
                ),
                static fn() => Response::of(
                    StatusCode::found,
                    $request->protocolVersion(),
                    Headers::of(
                        Location::of(Routes::index->template()->expand(Map::of())),
                    ),
                ),
            );
    }
}
