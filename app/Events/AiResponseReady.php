<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Recording;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class AiResponseReady implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly Recording $recording,
    ) {}

    /**
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
        return 'recording.completed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'recording_id' => $this->recording->id,
            'transcript' => $this->recording->transcript,
            'response_text' => $this->recording->ai_response_text,
            'audio_url' => null,
        ];
    }
}
