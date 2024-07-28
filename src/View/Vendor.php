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
    Progress,
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
    ): Content {
        $view = Window::of(
            $vendor->name(),
            Stack::vertical(
                Toolbar::of(Text::of($vendor->name()))
                    ->leading(Button::of(
                        Routes::index->template()->expand(Map::of()),
                        Text::of('< Vendors'),
                    )), // todo trailing buttons
                Stack::horizontal(
                    Listing::of(
                        $vendor
                            ->packages()
                            ->unsorted()
                            ->map(static fn($package) => $package->name())
                            ->prepend(Sequence::of('overview'))
                            ->map(Text::of(...)),
                    ),
                    $storage
                        ->get(Name::of(\sprintf(
                            '%s.svg',
                            $vendor->name(),
                        )))
                        ->keep(Instance::of(File::class))
                        ->match(
                            static fn($svg) => ScrollView::of(
                                Svg::of($svg->content())->zoom(50),
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
