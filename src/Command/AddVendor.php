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
use Innmind\UrlTemplate\Template;
use Innmind\Json\Json;
use Innmind\Immutable\{
    Str,
    Set,
    Map,
    Either,
    Predicate\Instance,
};

final class AddVendor implements Command
{
    private Clock $clock;
    private Transport $http;
    private Reader $parse;
    private Manager $orm;
    private Template $list;
    private Template $github;

    public function __construct(
        Clock $clock,
        Transport $http,
        Reader $parse,
        Manager $orm,
    ) {
        $this->clock = $clock;
        $this->http = $http;
        $this->parse = $parse;
        $this->orm = $orm;
        $this->list = Template::of('https://packagist.org/packages/list.json?vendor={name}&fields[]=repository&fields[]=abandoned');
        $this->github = Template::of('https://github.com/{vendor}');
    }

    public function __invoke(Console $console): Console
    {
        $vendor = $console->arguments()->get('vendor');

        if ($vendor === '') {
            return $console
                ->error(Str::of("Vendor must not be empty\n"))
                ->exit(1);
        }

        return ($this->http)(Request::of(
            $this->list->expand(Map::of(['name', $vendor])),
            Method::get,
            ProtocolVersion::v11,
        ))
            ->maybe()
            ->map(static fn($success) => $success->response()->body()->toString())
            ->flatMap(Json::maybeDecode(...))
            ->match(
                fn($content) => $this->parse($console, $vendor, $content),
                static fn() => $console
                    ->error(Str::of("Unknown vendor\n"))
                    ->exit(1),
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
     */
    private function parse(
        Console $console,
        string $vendor,
        mixed $content,
    ): Console {
        // TODO use innmind/validation (but it requires to add Is::associativeArray(Constraint, Constraint))
        if (
            !\is_array($content) ||
            !\array_key_exists('packages', $content) ||
            !\is_array($content['packages'])
        ) {
            return $console
                ->error(Str::of("Invalid packagist response\n"))
                ->exit(1);
        }

        /** @var Set<Package> */
        $packages = Set::of();
        $packagist = Template::of('https://packagist.org/packages{/vendor,package}');

        foreach ($content['packages'] as $key => $value) {
            if (
                !\is_string($key) ||
                !\is_array($value) ||
                !\array_key_exists('repository', $value) ||
                !\is_string($value['repository']) ||
                !\array_key_exists('abandoned', $value) ||
                $key === '' ||
                $value['abandoned'] !== false
            ) {
                continue;
            }

            /** @var non-empty-string */
            $package = Str::of($key)
                ->drop(Str::of($vendor)->length() + 1) // +1 for the /
                ->toString();
            $repository = Url::of($value['repository'].'/');

            $packages = ($packages)(Package::of(
                $package,
                $packagist->expand(Map::of(
                    ['vendor', $vendor],
                    ['package', $key],
                )),
                $repository,
                $repository->withPath(
                    $repository->path()->resolve(Path::of('actions')),
                ),
                $repository->withPath(
                    $repository->path()->resolve(Path::of('releases')),
                ),
            ));
        }

        return ($this->http)(Request::of(
            $this->github->expand(Map::of(['vendor', $vendor])),
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
