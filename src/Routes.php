<?php
declare(strict_types = 1);

namespace App;

use Innmind\UrlTemplate\Template;

enum Routes
{
    case index;
    case vendor;
    case vendorSvg;
    case vendorWithSize;
    case packageDependencies;
    case packageDependenciesSvg;
    case packageDependenciesWithSize;
    case packageDependents;
    case packageDependentsSvg;
    case packageDependentsWithSize;
    case style;

    public function template(): Template
    {
        return match ($this) {
            self::index => Template::of('/'),
            self::vendor => Template::of('/vendor{/name}'),
            self::vendorSvg => Template::of('/vendor{/name}.svg'),
            self::vendorWithSize => Template::of('/vendor{/name,size}'),
            self::packageDependencies => Template::of('/vendor{/vendor,package}/dependencies'),
            self::packageDependenciesSvg => Template::of('/vendor{/vendor,package}/dependencies.svg'),
            self::packageDependenciesWithSize => Template::of('/vendor{/vendor,package}/dependencies{/size}'),
            self::packageDependents => Template::of('/vendor{/vendor,package}/dependents'),
            self::packageDependentsSvg => Template::of('/vendor{/vendor,package}/dependents.svg'),
            self::packageDependentsWithSize => Template::of('/vendor{/vendor,package}/dependents{/size}'),
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
            self::vendorSvg => 'GET /vendor{/name}.svg',
            self::vendorWithSize => 'GET /vendor{/name,size}',
            self::packageDependencies => 'GET /vendor{/vendor,package}/dependencies',
            self::packageDependenciesSvg => 'GET /vendor{/vendor,package}/dependencies.svg',
            self::packageDependenciesWithSize => 'GET /vendor{/vendor,package}/dependencies{/size}',
            self::packageDependents => 'GET /vendor{/vendor,package}/dependents',
            self::packageDependentsSvg => 'GET /vendor{/vendor,package}/dependents.svg',
            self::packageDependentsWithSize => 'GET /vendor{/vendor,package}/dependents{/size}',
            self::style => 'GET /style',
        };
    }
}
