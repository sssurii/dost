<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\AiResponseReady;
use App\Services\Analytics\UserProgressService;

final class InvalidateProgressCache
{
    public function __construct(
        private readonly UserProgressService $service,
    ) {}

    public function handle(AiResponseReady $event): void
    {
        $this->service->invalidateCache($event->recording->user);
    }
}
