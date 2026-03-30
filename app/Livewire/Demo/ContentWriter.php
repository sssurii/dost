<?php

declare(strict_types=1);

namespace App\Livewire\Demo;

use Illuminate\View\View;
use Laravel\Ai\AnonymousAgent;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.demo')]
final class ContentWriter extends Component
{
    public string $prompt = '';

    public string $selectedProvider = 'gemini';

    public string $response = '';

    public string $providerUsed = '';

    public int $latencyMs = 0;

    public string $error = '';

    public function generate(): void
    {
        $this->reset('response', 'providerUsed', 'latencyMs', 'error');

        if (blank($this->prompt)) {
            $this->error = 'Please enter a prompt.';

            return;
        }

        $key = config("ai.providers.{$this->selectedProvider}.key");

        if (blank($key)) {
            $this->error = "No API key configured for {$this->selectedProvider}.";

            return;
        }

        try {
            $start = microtime(true);

            $agent = new AnonymousAgent(
                'You are an expert marketing copywriter. Write compelling, professional content based on the user\'s request. Keep it concise — 2 to 4 paragraphs max.',
                messages: [],
                tools: [],
            );

            $result = $agent->prompt($this->prompt, provider: $this->selectedProvider);

            $this->latencyMs = (int) round((microtime(true) - $start) * 1000);
            $this->response = $result->text;
            $this->providerUsed = $this->selectedProvider;
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    /**
     * @return array<string, array{label: string, available: bool}>
     */
    public function getProvidersProperty(): array
    {
        return collect(['gemini', 'openai', 'anthropic'])
            ->mapWithKeys(fn (string $name) => [
                $name => [
                    'label' => ucfirst($name),
                    'available' => filled(config("ai.providers.{$name}.key")),
                ],
            ])
            ->all();
    }

    public function render(): View
    {
        return view('livewire.demo.content-writer')
            ->title('AI Content Writer');
    }
}
