<?php
declare(strict_types = 1);

namespace App\Command;

use App\Domain\Vendor;
use Innmind\CLI\{
    Command,
    Console,
};
use Formal\ORM\Manager;
use Innmind\DependencyGraph\{
    Loader\VendorDependencies,
    Loader\Dependencies,
    Loader\Dependents,
    Render,
    Vendor\Name as VendorName,
    Package\Name as PackageName,
};
use Innmind\Filesystem\{
    Adapter,
    File,
    Directory,
};
use Innmind\Server\Control\{
    Server,
    Server\Processes,
};
use Innmind\Immutable\{
    Str,
    Set,
};
use Innmind\Specification\Comparator\Property;
use Innmind\Specification\Sign;

final class RenderVendor implements Command
{
    private Manager $orm;
    private VendorDependencies $vendor;
    private Dependencies $dependencies;
    private Dependents $dependents;
    private Render $render;
    private Processes $processes;
    private Adapter $storage;

    public function __construct(
        Manager $orm,
        VendorDependencies $vendor,
        Dependencies $dependencies,
        Dependents $dependents,
        Render $render,
        Processes $processes,
        Adapter $storage,
    ) {
        $this->orm = $orm;
        $this->vendor = $vendor;
        $this->dependencies = $dependencies;
        $this->dependents = $dependents;
        $this->render = $render;
        $this->processes = $processes;
        $this->storage = $storage;
    }

    public function __invoke(Console $console): Console
    {
        $repository = $this->orm->repository(Vendor::class);
        $vendors = $console
            ->arguments()
            ->maybe('vendor')
            ->match(
                static fn($vendor) => $repository->matching(Property::of(
                    'name',
                    Sign::equality,
                    $vendor,
                )),
                static fn() => $repository->all(),
            );

        return $vendors->reduce(
            $console,
            function(Console $console, $vendor) {
                $console = $this->render($console, $vendor->name());

                $console = $vendor->packages()->reduce(
                    $console,
                    fn(Console $console, $package) => $this->render(
                        $console,
                        $vendor->name(),
                        $package->name(),
                        true,
                    ),
                );

                return $vendor->packages()->reduce(
                    $console,
                    fn(Console $console, $package) => $this->render(
                        $console,
                        $vendor->name(),
                        $package->name(),
                        false,
                    ),
                );
            },
        );
    }

    /**
     * @psalm-mutation-free
     */
    public function usage(): string
    {
        return 'render-vendor [vendor]';
    }

    /**
     * @param non-empty-string $vendor
     * @param ?non-empty-string $package
     */
    private function render(
        Console $console,
        string $vendor,
        string $package = null,
        bool $dependencies = true,
    ): Console {
        $console = $console->output(
            Str::of("Rendering %s...\n")->sprintf(match ($package) {
                null => $vendor,
                default => \sprintf(
                    '%s/%s %s',
                    $vendor,
                    $package,
                    match ($dependencies) {
                        true => 'dependencies',
                        false => 'dependents',
                    },
                ),
            }),
        );
        $packages = match ($package) {
            null => ($this->vendor)(VendorName::of($vendor)),
            default => match ($dependencies) {
                true => ($this->dependencies)(PackageName::of("$vendor/$package")),
                false => ($this->dependents)(
                    PackageName::of("$vendor/$package"),
                    Set::of(VendorName::of($vendor)),
                ),
            },
        };

        /** @psalm-suppress ArgumentTypeCoercion */
        return $this
            ->processes
            ->execute(
                Server\Command::foreground('dot')
                    ->withEnvironments($console->variables()->filter(
                        static fn($name) => $name === 'PATH',
                    ))
                    ->withShortOption('Tsvg')
                    ->withWorkingDirectory($console->workingDirectory())
                    ->withInput(($this->render)($packages)),
            )
            ->wait()
            ->map(static fn($success) => File\Content::ofChunks(
                $success
                    ->output()
                    ->chunks()
                    ->map(static fn($pair) => $pair[0]),
            ))
            ->map(static fn($content) => match ($package) {
                null => File::named(
                    "$vendor.svg",
                    $content,
                ),
                default => Directory::named($vendor)->add(
                    Directory::named($package)->add(
                        File::named(
                            match ($dependencies) {
                                true => 'dependencies.svg',
                                false => 'dependents.svg',
                            },
                            $content,
                        ),
                    ),
                ),
            })
            ->map($this->storage->add(...))
            ->match(
                static fn() => $console->output(Str::of("Done\n")),
                static fn() => $console
                    ->error(Str::of("Failed, did you install `dot` ?\n"))
                    ->exit(1),
            );
    }
}
