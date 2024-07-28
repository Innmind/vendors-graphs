<?php
declare(strict_types = 1);

namespace App\Infrastructure;

use App\Domain\Package;
use Innmind\HttpTransport\Transport;
use Innmind\Http\{
    Request,
    Method,
    ProtocolVersion,
};
use Innmind\Url\{
    Url,
    Path,
};
use Innmind\Json\Json;
use Innmind\UrlTemplate\Template;
use Innmind\Immutable\{
    Sequence,
    Map,
    Set,
    Str,
};

final class LoadPackages
{
    private Transport $http;
    private Template $list;

    public function __construct(Transport $http)
    {
        $this->http = $http;
        $this->list = Template::of('https://packagist.org/packages/list.json?vendor={name}&fields[]=repository&fields[]=abandoned');
    }

    /**
     * @param non-empty-string $vendor
     *
     * @return Set<Package>
     */
    public function __invoke(string $vendor): Set
    {
        return ($this->http)(Request::of(
            $this->list->expand(Map::of(['name', $vendor])),
            Method::get,
            ProtocolVersion::v20,
        ))
            ->maybe()
            ->map(static fn($success) => $success->response()->body()->toString())
            ->flatMap(Json::maybeDecode(...))
            ->toSequence()
            ->flatMap(fn($content) => $this->parse($vendor, $content))
            ->toSet();
    }

    /**
     * @param non-empty-string $vendor
     *
     * @return Sequence<Package>
     */
    private function parse(string $vendor, mixed $content): Sequence
    {
        // TODO use innmind/validation (but it requires to add Is::associativeArray(Constraint, Constraint))
        if (
            !\is_array($content) ||
            !\array_key_exists('packages', $content) ||
            !\is_array($content['packages'])
        ) {
            return Sequence::of();
        }

        /** @var Sequence<Package> */
        $packages = Sequence::of();
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
                ->drop(Str::of($key)->length() + 1) // +1 for the /
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

        return $packages;
    }
}
