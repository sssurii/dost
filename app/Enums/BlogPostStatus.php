<?php

declare(strict_types=1);

namespace App\Enums;

enum BlogPostStatus: string
{
    case Generating = 'generating';
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Generating => 'Generating',
            self::Draft => 'Draft',
            self::Published => 'Published',
            self::Archived => 'Archived',
        };
    }

    public function isPublished(): bool
    {
        return $this === self::Published;
    }
}
