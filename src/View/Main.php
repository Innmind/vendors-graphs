<?php
declare(strict_types = 1);

namespace App\View;

use App\{
    Domain\Vendor,
    Routes,
};
use Formal\ORM\Manager;
use Innmind\Filesystem\File\Content;
use Innmind\UI\{
    Window,
    Stack,
    Toolbar,
    Text,
    Button,
    Grid,
    Card,
    Shape,
    Image,
    NavigationLink,
};
use Innmind\Url\Url;
use Innmind\Immutable\Map;

final class Main
{
    public static function of(Manager $orm): Content
    {
        $view = Window::of(
            'Vendors',
            Stack::vertical(
                Toolbar::of(Text::of('Vendors'))
                    ->trailing(Button::text(Url::of('https://twitter.com/Baptouuuu'), '+ Add')),
                Grid::of(
                    $orm
                        ->repository(Vendor::class)
                        ->all()
                        ->map(static fn($vendor) => NavigationLink::of(
                            Routes::vendor->template()->expand(Map::of(
                                ['name', $vendor->name()],
                            )),
                            Stack::vertical(
                                Image::of($vendor->image())
                                    ->shape(Shape::cornered),
                                Text::of($vendor->name()),
                            ),
                        ))
                        ->map(Card::of(...)),
                ),
            ),
        )->stylesheet(Routes::style->template()->expand(Map::of()));

        return Content::ofLines($view->render());
    }
}
