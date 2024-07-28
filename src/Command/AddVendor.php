<?php
declare(strict_types = 1);

namespace App\Command;

use App\Domain\{
    Vendor,
    Package,
};
use Innmind\CLI\{
    Command,
    Console,
};
use Formal\ORM\Manager;
use Innmind\DependencyGraph\{
    Loader,
    Vendor\Name,
    Package as PackageModel,
};
use Innmind\TimeContinuum\Clock;
use Innmind\HttpTransport\Transport;
use Innmind\Http\{
    Method,
    ProtocolVersion,
    Request,
};
use Innmind\Html\{
    Element\Img,
    Visitor\Elements,
};
use Innmind\Xml\Reader;
use Innmind\Url\{
    Path,
    Query,
};
use Innmind\Immutable\{
    Str,
    Set,
    Either,
    Predicate\Instance,
};

final class AddVendor implements Command
{
    private Clock $clock;
    private Transport $http;
    private Reader $parse;
    private Manager $orm;
    private Loader\Vendor $load;

    public function __construct(
        Clock $clock,
        Transport $http,
        Reader $parse,
        Manager $orm,
        Loader\Vendor $load,
    ) {
        $this->clock = $clock;
        $this->http = $http;
        $this->parse = $parse;
        $this->orm = $orm;
        $this->load = $load;
    }

    public function __invoke(Console $console): Console
    {
        $vendor = $console->arguments()->get('vendor');

        if ($vendor === '') {
            return $console
                ->error(Str::of("Vendor must not be empty\n"))
                ->exit(1);
        }

        return $this->parse(
            $console,
            $vendor,
            ($this->load)(Name::of($vendor))->packages(),
        );
    }

    /**
     * @psalm-mutation-free
     */
    public function usage(): string
    {
        return 'add-vendor vendor';
    }

    /**
     * @param non-empty-string $vendor
     * @param Set<PackageModel> $packages
     */
    private function parse(
        Console $console,
        string $vendor,
        Set $packages,
    ): Console {
        /** @psalm-suppress ArgumentTypeCoercion */
        $packages = $packages
            ->filter(static fn($package) => !$package->abandoned())
            ->map(static fn($package) => Package::of(
                $package->name()->package(),
                $package->packagist(),
                $package->repository(),
                $package->repository()->withPath(
                    $package->repository()->path()->resolve(Path::of('actions')),
                ),
                $package->repository()->withPath(
                    $package->repository()->path()->resolve(Path::of('releases')),
                ),
            ));

        return $packages
            ->find(static fn() => true)
            ->map(
                static fn($package) => $package
                    ->github()
                    ->withPath(
                        $package
                            ->github()
                            ->path()
                            ->resolve(Path::of('../')),
                    ),
            )
            ->flatMap(
                fn($url) => ($this->http)(Request::of(
                    $url,
                    Method::get,
                    ProtocolVersion::v11,
                ))
                    ->maybe(),
            )
            ->map(static fn($success) => $success->response()->body())
            ->flatMap($this->parse)
            ->map(Elements::of('img'))
            ->flatMap(
                static fn($imgs) => $imgs
                    ->keep(Instance::of(Img::class))
                    ->find(
                        static fn(Img $img) => $img
                            ->attribute('class')
                            ->filter(static fn($class) => \str_contains(
                                $class->value(),
                                'avatar',
                            ))
                            ->match(
                                static fn() => true,
                                static fn() => false,
                            ),
                    ),
            )
            ->map(static fn($img) => $img->src()->withQuery(
                Query::of('s=150'),
            ))
            ->map(fn($img) => Vendor::of(
                $this->clock,
                $vendor,
                $img,
                $packages,
            ))
            ->flatMap(
                fn($vendor) => $this
                    ->orm
                    ->transactional(
                        fn() => Either::right(
                            $this
                                ->orm
                                ->repository(Vendor::class)
                                ->put($vendor),
                        ),
                    )
                    ->maybe(),
            )
            ->match(
                static fn() => $console->output(Str::of("Vendor added\n")),
                static fn() => $console
                    ->error(Str::of("Unable to add the vendor\n"))
                    ->exit(1),
            );
    }
}
