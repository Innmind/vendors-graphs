<?php
declare(strict_types = 1);

namespace App;

use Innmind\UrlTemplate\Template;

enum Routes
{
    case index;
    case vendor;
    case packageDependencies;
    case packageDependents;
    case style;

    public function template(): Template
    {
        return match ($this) {
            self::index => Template::of('/'),
            self::vendor => Template::of('/vendor{/name}'),
            self::packageDependencies => Template::of('/vendor{/vendor,package}/dependencies'),
            self::packageDependents => Template::of('/vendor{/vendor,package}/dependents'),
            self::style => Template::of('/style'),
        };
    }

    /**
     * @return literal-string
     */
    public function toString(): string
    {
        return match ($this) {
            self::index => 'GET /',
            self::vendor => 'GET /vendor{/name}',
            self::packageDependencies => 'GET /vendor{/vendor,package}/dependencies',
            self::packageDependents => 'GET /vendor{/vendor,package}/dependents',
            self::style => 'GET /style',
        };
    }
}
