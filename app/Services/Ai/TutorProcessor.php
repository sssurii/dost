<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Ai\Agents\TutorAgent;
use App\Models\Recording;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Files\Audio;
use Laravel\Ai\Responses\StructuredAgentResponse;

final class TutorProcessor
{
    /**
     * Process a recording: send audio to Gemini, receive structured response, save to DB.
     *
     * @throws \Throwable
     */
    public function process(Recording $recording): TutorResult
    {
        $recording->markAsProcessing();
        Log::info('audio file path: '.$recording->path);

        try {
            $agent = $this->resolveAgent($recording->user);

            /** @var StructuredAgentResponse $response */
            $response = $agent->prompt(
                'Transcribe the audio and respond as Dost.',
                attachments: [Audio::fromStorage($recording->path, 'public')],
            );
            $transcript = (string) $response['transcript'];
            $aiResponse = (string) $response['response'];
            $recording->markAsCompleted($transcript, $aiResponse);
            Log::info('TutorProcessor: completed', [
                'recording_id' => $recording->id,
                'conversation_id' => $response->conversationId,
            ]);

            return new TutorResult(
                transcript: $transcript,
                response: $aiResponse,
                recording: $recording->fresh() ?? $recording,
            );
        } catch (\Throwable $e) {
            $recording->markAsFailed();
            Log::error('TutorProcessor: failed', [
                'recording_id' => $recording->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Resolve today's conversation or start a fresh one.
     * New conversation each day; context preserved within the same day.
     */
    private function resolveAgent(User $user): TutorAgent
    {
        $todayConversation = DB::table('agent_conversations')
            ->where('user_id', $user->id)
            ->whereDate('created_at', today())
            ->latest('updated_at')
            ->first();
        if ($todayConversation !== null) {
            return (new TutorAgent)->continue($todayConversation->id, as: $user);
        }

        return (new TutorAgent)->forUser($user);
    }
}
