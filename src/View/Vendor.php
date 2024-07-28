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

final class Vendor
{
    public static function of(
        Adapter $storage,
        Domain\Vendor $vendor,
        Domain\Zoom $zoom,
    ): Content {
        $svg = $storage
            ->get(Name::of(\sprintf(
                '%s.svg',
                $vendor->name(),
            )))
            ->keep(Instance::of(File::class));

        $toolbar = Toolbar::of(Text::of($vendor->name()))
            ->leading(Button::of(
                Routes::index->template()->expand(Map::of()),
                Text::of('< Vendors'),
            ));

        $toolbar = $svg->match(
            static fn() => $toolbar->trailing(Picker::of(
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
            )),
            static fn() => $toolbar,
        );

        $view = Window::of(
            $vendor->name(),
            Stack::vertical(
                $toolbar, // todo trailing buttons
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
                        static fn($svg) => ScrollView::of(
                            Svg::of($svg->content())->zoom(
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
