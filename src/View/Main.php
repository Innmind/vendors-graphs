<?php
declare(strict_types = 1);

namespace App\View;

use App\{
    Domain\Vendor,
    Routes,
};
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
use Innmind\Immutable\{
    Map,
    Sequence,
};

final class Main
{
    /**
     * @param Sequence<Vendor> $vendors
     */
    public static function of(Sequence $vendors): Content
    {
        return Window::of(
            'Vendors',
            Stack::vertical(
                Toolbar::of(Text::of('Vendors'))
                    ->trailing(Button::text(Url::of('https://twitter.com/Baptouuuu'), '+ Add')),
                Grid::of(
                    $vendors
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
        )
            ->stylesheet(Routes::style->template()->expand(Map::of()))
            ->render();
    }
}
