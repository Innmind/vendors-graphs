<?php
declare(strict_types = 1);

namespace App\View;

use App\{
    Routes,
    Domain,
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
use Innmind\Url\Url;
use Innmind\Immutable\{
    Map,
    Sequence,
    Maybe,
};

final class Package
{
    /**
     * @param Maybe<Content> $svg
     * @param non-empty-string $selectedPackage
     */
    public static function of(
        Maybe $svg,
        Domain\Vendor $vendor,
        string $selectedPackage,
        Domain\Direction $direction,
        Domain\Zoom $zoom,
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
        $withSize = static fn(string $size): Url => (match ($direction) {
            Domain\Direction::dependencies => Routes::packageDependenciesWithSize->template(),
            Domain\Direction::dependents => Routes::packageDependentsWithSize->template(),
        })->expand(Map::of(
            ['vendor', $vendor->name()],
            ['package', $selectedPackage],
            ['size', $size],
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
                    ...$svg->match(
                        static fn() => [Picker::of(
                            $zoom,
                            Picker\Value::of(
                                Domain\Zoom::small,
                                Button::of(
                                    $withSize('small'),
                                    Text::of('25%'),
                                ),
                            ),
                            Picker\Value::of(
                                Domain\Zoom::medium,
                                Button::of(
                                    $withSize('medium'),
                                    Text::of('50%'),
                                ),
                            ),
                            Picker\Value::of(
                                Domain\Zoom::full,
                                Button::of(
                                    $withSize('full'),
                                    Text::of('100%'),
                                ),
                            ),
                        )],
                        static fn() => [],
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
                    $svg->match(
                        static fn($content) => ScrollView::of(
                            Svg::of($content)->zoom(
                                $zoom->toInt(),
                            ),
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
