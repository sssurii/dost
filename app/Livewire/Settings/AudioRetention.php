<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Enums\RecordingStatus;
use App\Enums\RetentionDays;
use App\Livewire\Actions\Logout;
use App\Models\Recording;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.app')]
final class AudioRetention extends Component
{
    #[Validate('required|integer|in:1,2,7')]
    public int $retentionDays = 2;

    public function mount(): void
    {
        /** @var User $user */
        $user = Auth::user();
        $this->retentionDays = ($user->audio_retention_days ?? RetentionDays::Two)->value;
    }

    public function save(): void
    {
        $this->validate();

        /** @var User $user */
        $user = Auth::user();

        $user->update(['audio_retention_days' => $this->retentionDays]);

        // Recompute expiry from the original recording time so retention remains
        // "N days after recording", not "N days after changing settings".
        $user->recordings()
            ->whereIn('status', [
                RecordingStatus::Pending->value,
                RecordingStatus::Processing->value,
                RecordingStatus::Completed->value,
            ])
            ->where('expires_at', '>', now())
            ->chunkById(100, function ($recordings): void {
                /** @var Recording $recording */
                foreach ($recordings as $recording) {
                    $recording->update([
                        'expires_at' => $recording->created_at->copy()->addDays($this->retentionDays),
                    ]);
                }
            });

        $this->dispatch('saved');
    }

    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect(route('login'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.settings.audio-retention');
    }
}
