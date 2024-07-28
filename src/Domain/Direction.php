<?php
declare(strict_types = 1);

namespace App\Domain;

enum Direction
{
    case dependencies;
    case dependents;
}
