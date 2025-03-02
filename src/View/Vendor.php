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
use Innmind\Immutable\{
    Map,
    Sequence,
    Maybe,
};

final class Vendor
{
    /**
     * @param Maybe<Content> $svg
     */
    public static function of(
        Maybe $svg,
        Domain\Vendor $vendor,
        Domain\Zoom $zoom,
    ): Content {
        $extra = $svg->match(
            static fn() => [Picker::of(
                $zoom,
                Picker\Value::of(
                    Domain\Zoom::small,
                    Button::of(
                        Routes::vendorWithSize->template()->expand(Map::of(
                            ['name', $vendor->name()],
                            ['size', 'small'],
                        )),
                        Text::of('25%'),
                    ),
                ),
                Picker\Value::of(
                    Domain\Zoom::medium,
                    Button::of(
                        Routes::vendorWithSize->template()->expand(Map::of(
                            ['name', $vendor->name()],
                            ['size', 'medium'],
                        )),
                        Text::of('50%'),
                    ),
                ),
                Picker\Value::of(
                    Domain\Zoom::full,
                    Button::of(
                        Routes::vendorWithSize->template()->expand(Map::of(
                            ['name', $vendor->name()],
                            ['size', 'full'],
                        )),
                        Text::of('100%'),
                    ),
                ),
            )],
            static fn() => [],
        );
        $toolbar = Toolbar::of(Text::of($vendor->name()))
            ->leading(Button::of(
                Routes::index->template()->expand(Map::of()),
                Text::of('< Vendors'),
            ))
            ->trailing(Stack::horizontal(
                Button::of(
                    $vendor->packagist(),
                    Text::of('Packagist'),
                ),
                Button::of(
                    $vendor->github(),
                    Text::of('GitHub'),
                ),
                ...$extra,
            ));

        return Window::of(
            $vendor->name(),
            Stack::vertical(
                $toolbar,
                Stack::horizontal(
                    Listing::of(
                        $vendor
                            ->packages()
                            ->sort(static fn($a, $b) => $a->name() <=> $b->name())
                            ->map(static fn($package) => NavigationLink::of(
                                Routes::packageDependencies->template()->expand(Map::of(
                                    ['vendor', $vendor->name()],
                                    ['package', $package->name()],
                                )),
                                Text::of($package->name()),
                            ))
                            ->prepend(Sequence::of(NavigationLink::of(
                                Routes::vendor->template()->expand(Map::of(
                                    ['name', $vendor->name()],
                                )),
                                Text::of('Overview'),
                            )->selectedWhen(true))),
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
        )
            ->stylesheet(Routes::style->template()->expand(Map::of()))
            ->render();
    }
}
