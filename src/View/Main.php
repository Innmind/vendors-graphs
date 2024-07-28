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
                        ->map(static fn($vendor) => Stack::vertical(
                            Image::of($vendor->image())
                                ->shape(Shape::cornered),
                            Text::of($vendor->name()),
                        ))
                        // todo navigation link
                        ->map(Card::of(...)),
                ),
            ),
        )->stylesheet(Routes::style->template()->expand(Map::of()));

        return Content::ofLines($view->render());
    }
}
