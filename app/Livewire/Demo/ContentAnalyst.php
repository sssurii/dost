<?php

declare(strict_types=1);

namespace App\Livewire\Demo;

use App\Ai\Agents\Demo\ContentAnalystAgent;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.demo')]
final class ContentAnalyst extends Component
{
    public string $userInput = '';

    /** @var array<int, array{role: string, content: string}> */
    public array $messages = [];

    public string $error = '';

    public function send(): void
    {
        $this->reset('error');

        if (blank($this->userInput)) {
            return;
        }

        $input = $this->userInput;
        $this->messages[] = ['role' => 'user', 'content' => $input];
        $this->userInput = '';

        try {
            $result = ContentAnalystAgent::make()->prompt($input);
            $this->messages[] = ['role' => 'assistant', 'content' => $result->text];
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
            $this->messages[] = ['role' => 'assistant', 'content' => 'Sorry, something went wrong. Please try again.'];
        }
    }

    public function useSuggestion(string $text): void
    {
        $this->userInput = $text;
    }

    public function render(): View
    {
        return view('livewire.demo.content-analyst')
            ->title('Content Analyst Agent');
    }
}
