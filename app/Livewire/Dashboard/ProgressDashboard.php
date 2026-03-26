<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Models\User;
use App\Services\Analytics\ProgressData;
use App\Services\Analytics\UserProgressService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('layouts.app')]
final class ProgressDashboard extends Component
{
    public int $userId = 0;

    public function mount(): void
    {
        $this->userId = (int) Auth::id();
    }

    #[On('echo-private:user.{userId},recording.completed')]
    public function refresh(): void
    {
        unset($this->progress);
    }

    #[Computed]
    public function progress(): ProgressData
    {
        /** @var User $user */
        $user = Auth::user();

        return app(UserProgressService::class)->getProgressData($user);
    }

    public function render(): View
    {
        return view('livewire.dashboard.progress-dashboard');
    }
}
