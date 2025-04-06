<?php
declare(strict_types = 1);

namespace App\Controller;

use Innmind\Filesystem\{
    Adapter,
    File,
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
use Innmind\Immutable\Predicate\Instance;

final class VendorSvg
{
    public function __construct(
        private Adapter $storage,
    ) {
    }

    public function __invoke(ServerRequest $request, Variables $variables): Response
    {
        return $this
            ->storage
            ->get(Name::of(\sprintf(
                '%s.svg',
                $variables->get('name'),
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
