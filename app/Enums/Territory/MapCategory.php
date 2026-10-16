<?php

namespace App\Enums\Territory;

enum MapCategory: string
{
    case BOUNDARIES = 'boundaries';
    case ROADS = 'roads';
    case RIVERS = 'rivers';
    case CABLEWAYS = 'cableways';
    case OTHER = 'other';
}
