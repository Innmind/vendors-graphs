<?php
declare(strict_types = 1);

namespace App\Command;

use App\{
    Domain\Vendor,
    Infrastructure\LoadPackages,
};
use Innmind\CLI\{
    Command,
    Console,
};
use Formal\ORM\Manager;
use Innmind\Filesystem\{
    Adapter,
    Name,
    Directory,
};
use Innmind\Immutable\{
    Str,
    Either,
};

final class UpdateVendors implements Command
{
    private Manager $orm;
    private LoadPackages $load;
    private Adapter $storage;

    public function __construct(
        Manager $orm,
        LoadPackages $load,
        Adapter $storage,
    ) {
        $this->orm = $orm;
        $this->load = $load;
        $this->storage = $storage;
    }

    public function __invoke(Console $console): Console
    {
        return $this
            ->orm
            ->repository(Vendor::class)
            ->all()
            ->reduce(
                $console,
                $this->update(...),
            );
    }

    /**
     * @psalm-mutation-free
     */
    public function usage(): string
    {
        return 'update-vendors';
    }

    private function update(Console $console, Vendor $vendor): Console
    {
        $console = $console->output(Str::of("Updating {$vendor->name()}...\n"));
        $packages = ($this->load)($vendor->name());
        $stillExisting = $packages->map(static fn($package) => $package->name());
        $console = $vendor
            ->packages()
            ->filter(static fn($package) => !$stillExisting->contains($package->name()))
            ->reduce(
                $console,
                function(Console $console, $package) use ($vendor) {
                    $this->storage->add(
                        Directory::named($vendor->name())
                            ->remove(Name::of($package->name())),
                    );

                    return $console->output(
                        Str::of("%s/%s removed\n")->sprintf(
                            $vendor->name(),
                            $package->name(),
                        ),
                    );
                },
            );
        $this->orm->transactional(
            fn() => Either::right(
                $this
                    ->orm
                    ->repository(Vendor::class)
                    ->put($vendor->update($packages)),
            ),
        );

        return $console->output(Str::of("%s updated\n")->sprintf(
            $vendor->name(),
        ));
    }
}
