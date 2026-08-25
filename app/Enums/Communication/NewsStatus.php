<?php

namespace App\Enums\Communication;

enum NewsStatus: string
{
    case DRAFT = 'draft';
    case PUBLISHED = 'published';
    case ARCHIVED = 'archived';
}
