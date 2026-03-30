<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DemoDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class DemoDocument extends Model
{
    /** @use HasFactory<DemoDocumentFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'title',
        'content',
        'embedding',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'embedding' => 'array',
        ];
    }
}
