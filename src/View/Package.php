<?php
declare(strict_types = 1);

namespace App\View;

use App\{
    Routes,
    Domain,
};
use Innmind\Filesystem\{
    Adapter,
    File,
    Directory,
    Name,
};
use Innmind\UI\{
    Window,
    Toolbar,
    Stack,
    Text,
    Button,
    Listing,
    ScrollView,
    Svg,
    Center,
    NavigationLink,
    Progress,
    Picker,
};
use Innmind\Filesystem\File\Content;
use Innmind\Immutable\{
    Map,
    Sequence,
    Predicate\Instance,
};

final class Package
{
    /**
     * @param non-empty-string $selectedPackage
     */
    public static function of(
        Adapter $storage,
        Domain\Vendor $vendor,
        string $selectedPackage,
        Domain\Direction $direction,
    ): Content {
        $toolbar = Toolbar::of(Text::of(\sprintf(
            '%s/%s',
            $vendor->name(),
            $selectedPackage,
        )))
            ->leading(Button::of(
                Routes::index->template()->expand(Map::of()),
                Text::of('< Vendors'),
            ));

        $toolbar = $vendor
            ->packages()
            ->find(static fn($package) => $package->name() === $selectedPackage)
            ->match(
                static fn($package) => $toolbar->trailing(Stack::horizontal(
                    Button::of(
                        $package->packagist(),
                        Text::of('Packagist'),
                    ),
                    Button::of(
                        $package->github(),
                        Text::of('GitHub'),
                    ),
                    Button::of(
                        $package->ci(),
                        Text::of('Actions'),
                    ),
                    Button::of(
                        $package->releases(),
                        Text::of('Releases'),
                    ),
                    Picker::of(
                        $direction,
                        Picker\Value::of(
                            Domain\Direction::dependencies,
                            Button::of(
                                Routes::packageDependencies->template()->expand(Map::of(
                                    ['vendor', $vendor->name()],
                                    ['package', $package->name()],
                                )),
                                Text::of('Dependencies'),
                            ),
                        ),
                        Picker\Value::of(
                            Domain\Direction::dependents,
                            Button::of(
                                Routes::packageDependents->template()->expand(Map::of(
                                    ['vendor', $vendor->name()],
                                    ['package', $package->name()],
                                )),
                                Text::of('Dependents'),
                            ),
                        ),
                    ),
                )),
                static fn() => $toolbar,
            );

        $view = Window::of(
            $vendor->name(),
            Stack::vertical(
                $toolbar,
                Stack::horizontal(
                    Listing::of(
                        $vendor
                            ->packages()
                            ->sort(static fn($a, $b) => $a->name() <=> $b->name())
                            ->map(static fn($package) => NavigationLink::of(
                                (match ($direction) {
                                    Domain\Direction::dependencies => Routes::packageDependencies,
                                    Domain\Direction::dependents => Routes::packageDependents,
                                })->template()->expand(Map::of(
                                    ['vendor', $vendor->name()],
                                    ['package', $package->name()],
                                )),
                                Text::of($package->name()),
                            )->selectedWhen($selectedPackage === $package->name()))
                            ->prepend(Sequence::of(NavigationLink::of(
                                Routes::vendor->template()->expand(Map::of(
                                    ['name', $vendor->name()],
                                )),
                                Text::of('Overview'),
                            ))),
                    ),
                    $storage
                        ->get(Name::of($vendor->name()))
                        ->keep(Instance::of(Directory::class))
                        ->flatMap(static fn($directory) => $directory->get(Name::of(
                            $selectedPackage,
                        )))
                        ->keep(Instance::of(Directory::class))
                        ->flatMap(static fn($directory) => $directory->get(Name::of(
                            \sprintf(
                                '%s.svg',
                                $direction->name,
                            ),
                        )))
                        ->keep(Instance::of(File::class))
                        ->match(
                            static fn($svg) => ScrollView::of(
                                Svg::of($svg->content()),
                            ),
                            static fn() => Center::of(
                                Stack::horizontal(
                                    Progress::new(),
                                    Text::of('Pending rendering'),
                                ),
                            ),
                        ),
                ),
            ),
        )->stylesheet(Routes::style->template()->expand(Map::of()));

        return Content::ofLines($view->render());
    }
}
