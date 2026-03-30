<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class BlogStudioResultReady implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, array{provider: string, status: string, message: string}>  $failoverLog
     */
    public function __construct(
        public string $blogPostId,
        public string $type,
        public array $payload,
        public array $failoverLog = [],
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel("demo.studio.{$this->blogPostId}")];
    }

    public function broadcastAs(): string
    {
        return 'studio.result';
    }
}
