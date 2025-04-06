<?php
declare(strict_types = 1);

namespace App\Controller;

use App\Domain;
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
    Header\Header,
    Header\Value\Value,
};
use Innmind\Router\Route\Variables;
use Innmind\Immutable\{
    Str,
    Predicate\Instance,
};

final class PackageSvg
{
    public function __construct(
        private Adapter $storage,
    ) {
    }

    public function __invoke(ServerRequest $request, Variables $variables): Response
    {
        $package = $variables->get('package');

        if ($package === '') {
            return Response::of(
                StatusCode::notFound,
                $request->protocolVersion(),
            );
        }

        $direction = match (Str::of($request->url()->path()->toString())->contains('dependencies')) {
            true => Domain\Direction::dependencies,
            false => Domain\Direction::dependents,
        };

        return $this
            ->storage
            ->get(Name::of($variables->get('vendor')))
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
            ->map(static fn($file) => $file->content())
            ->match(
                static fn($svg) => Response::of(
                    StatusCode::ok,
                    $request->protocolVersion(),
                    Headers::of(
                        new Header(
                            'Content-Type',
                            new Value('image/svg+xml'),
                        ),
                    ),
                    $svg,
                ),
                static fn() => Response::of(
                    StatusCode::notFound,
                    $request->protocolVersion(),
                ),
            );
    }
}
