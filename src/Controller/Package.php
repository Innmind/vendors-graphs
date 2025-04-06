<?php
declare(strict_types = 1);

namespace App\Controller;

use App\{
    Domain,
    View,
    Routes,
};
use Formal\ORM\Manager;
use Innmind\Filesystem\{
    Adapter,
    File,
    Directory,
    Name,
};
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
    Predicate\Instance,
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

        $direction = match (Str::of($request->url()->path()->toString())->contains('dependencies')) {
            true => Domain\Direction::dependencies,
            false => Domain\Direction::dependents,
        };
        $zoom = $variables
            ->maybe('size')
            ->match(
                static fn($size) => match ($size) {
                    'small' => Domain\Zoom::small,
                    'medium' => Domain\Zoom::medium,
                    default => Domain\Zoom::full,
                },
                static fn() => Domain\Zoom::full,
            );

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
                        $this
                            ->storage
                            ->get(Name::of($vendor->name()))
                            ->keep(Instance::of(Directory::class))
                            ->flatMap(static fn($directory) => $directory->get(Name::of(
                                $package,
                            )))
                            ->keep(Instance::of(Directory::class))
                            ->flatMap(static fn($directory) => $directory->get(Name::of(
                                \sprintf(
                                    '%s.svg',
                                    $direction->name,
                                ),
                            )))
                            ->keep(Instance::of(File::class))
                            ->map(static fn($file) => $file->content()),
                        $vendor,
                        $package,
                        $direction,
                        $zoom,
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
