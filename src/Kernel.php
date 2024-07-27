<?php
declare(strict_types = 1);

namespace App;

use Innmind\Framework\{
    Application,
    Middleware,
};

final class Kernel implements Middleware
{
    public function __invoke(Application $app): Application
    {
        return $app;
    }
}
