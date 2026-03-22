<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Recording;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class RecordingFinished implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly Recording $recording,
    ) {}

    /**
     * Broadcast on the authenticated user's private channel.
     *
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.'.$this->recording->user_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'recording.finished';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'recording_id' => $this->recording->id,
            'status' => $this->recording->status->value,
        ];
    }
}
