<?php
declare(strict_types = 1);

namespace App\Command;

use App\{
    Domain\Vendor,
    Domain\Package,
    Infrastructure\LoadPackages,
};
use Innmind\CLI\{
    Command,
    Console,
};
use Formal\ORM\Manager;
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
    Url,
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
    private LoadPackages $load;

    public function __construct(
        Clock $clock,
        Transport $http,
        Reader $parse,
        Manager $orm,
        LoadPackages $load,
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
            ($this->load)($vendor),
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
     * @param Set<Package> $packages
     */
    private function parse(
        Console $console,
        string $vendor,
        Set $packages,
    ): Console {
        return $packages
            ->find(static fn() => true)
            ->map(
                static fn($package) => $package
                    ->github()
                    ->withPath(
                        Path::of(\dirname(
                            $package
                                ->github()
                                ->path()
                                ->toString(),
                        )),
                    ),
            )
            ->flatMap(
                fn($url) => ($this->http)(Request::of(
                    $url,
                    Method::get,
                    ProtocolVersion::v11,
                ))
                    ->maybe()
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
                        Url::of('https://packagist.org/packages/'.$vendor),
                        $url,
                        $img,
                        $packages,
                    )),
            )
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
