<?php
declare(strict_types = 1);

namespace App\Infrastructure\HttpTransport;

use Innmind\Filesystem\{
    Adapter,
    File,
    File\Content,
    Directory,
    Name,
};
use Innmind\HttpTransport\{
    Transport,
    Success,
};
use Innmind\TimeContinuum\{
    Clock,
    Earth\Format\ISO8601,
    Earth\Period\Second,
};
use Innmind\Http\{
    Request,
    Response,
    Response\StatusCode,
    Header\CacheControl,
    Header\CacheControlValue\SharedMaxAge,
};
use Innmind\Hash\Hash;
use Innmind\Immutable\{
    Either,
    Str,
    Predicate\Instance,
};

/**
 * @psalm-import-type Errors from Transport
 */
final class Cache implements Transport
{
    public function __construct(
        private Transport $http,
        private Adapter $cache,
        private Clock $clock,
    ) {
    }

    public function __invoke(Request $request): Either
    {
        $hash = Hash::sha512
            ->ofSequence(Str::of($request->url()->toString())->chunk())
            ->hex();

        /** @var Either<Errors, Success> */
        return $this
            ->cache
            ->get(Name::of($hash))
            ->keep(Instance::of(Directory::class))
            ->filter(
                fn($directory) => $directory
                    ->get(Name::of('expires-at'))
                    ->keep(Instance::of(File::class))
                    ->map(static fn($file) => $file->content()->toString())
                    ->flatMap($this->clock->at(...))
                    ->filter(fn($expires) => $expires->aheadOf($this->clock->now()))
                    ->match(
                        static fn() => true,
                        static fn() => false,
                    ),
            )
            ->flatMap(
                static fn($directory) => $directory
                    ->get(Name::of('content'))
                    ->keep(Instance::of(File::class))
                    ->map(static fn($file) => Response::of(
                        StatusCode::ok,
                        $request->protocolVersion(),
                        null,
                        $file->content(),
                    ))
                    ->map(static fn($response) => new Success($request, $response)),
            )
            ->either()
            ->otherwise(fn() => $this->fetch($request, $hash));
    }

    /**
     * @param non-empty-string  $hash
     */
    private function fetch(Request $request, string $hash): Either
    {
        return ($this->http)($request)
            ->map(function($success) use ($hash) {
                /** @psalm-suppress ArgumentTypeCoercion Due to the age */
                $_ = $success
                    ->response()
                    ->headers()
                    ->find(CacheControl::class)
                    ->flatMap(
                        static fn($header) => $header
                            ->values()
                            ->keep(Instance::of(SharedMaxAge::class))
                            ->find(static fn() => true),
                    )
                    ->map(static fn($value) => $value->age())
                    ->filter(static fn($age) => $age > 0)
                    ->map(Second::of(...))
                    ->map($this->clock->now()->goForward(...))
                    ->map(static fn($expires) => $expires->format(new ISO8601))
                    ->map(Content::ofString(...))
                    ->map(static fn($content) => File::named(
                        'expires-at',
                        $content,
                    ))
                    ->map(
                        Directory::named($hash)
                            ->add(File::named(
                                'content',
                                $success->response()->body(),
                            ))
                            ->add(...),
                    )
                    ->match(
                        $this->cache->add(...),
                        static fn() => null,
                    );

                return $success;
            });
    }
}
