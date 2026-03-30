<?php

declare(strict_types=1);

namespace App\Livewire\Demo;

use Illuminate\Support\Facades\Event;
use Illuminate\View\View;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Events\ProviderFailedOver;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.demo')]
final class AlertWriter extends Component
{
    public string $scenario = 'service_outage';

    public string $response = '';

    public string $error = '';

    /** @var array<int, array{provider: string, status: string, message: string}> */
    public array $failoverLog = [];

    public bool $isLoading = false;

    /**
     * @return array<string, string>
     */
    private function scenarioPrompts(): array
    {
        return [
            'service_outage' => 'Draft an urgent notification to customers about a 2-hour service outage affecting payment processing. Be clear, empathetic, and include next steps.',
            'security_alert' => 'Draft a security advisory to users about a detected unauthorized access attempt. Be direct, include immediate actions users should take.',
            'weather_warning' => 'Draft an emergency weather alert for a severe thunderstorm warning in the Mumbai metropolitan area. Include safety instructions.',
        ];
    }

    public function generate(): void
    {
        $this->reset('response', 'error', 'failoverLog');
        $this->isLoading = true;

        $prompt = $this->scenarioPrompts()[$this->scenario] ?? '';

        if (blank($prompt)) {
            $this->error = 'Invalid scenario selected.';
            $this->isLoading = false;

            return;
        }

        Event::listen(ProviderFailedOver::class, function (ProviderFailedOver $event): void {
            $exception = $event->exception;

            $this->failoverLog[] = [
                'provider' => $event->provider->name ?? class_basename($event->provider),
                'status' => 'failed',
                'message' => $exception instanceof \Throwable ? $exception->getMessage() : 'Provider failed',
            ];
        });

        try {
            $agent = new AnonymousAgent(
                'You are an emergency communications specialist. Write clear, professional, urgent notifications. Keep it to 3-4 short paragraphs.',
                messages: [],
                tools: [],
            );

            $result = $agent->prompt($prompt, provider: [
                'demo-broken' => 'gpt-4o',
                'gemini' => 'gemini-2.5-flash',
            ]);

            $this->failoverLog[] = [
                'provider' => 'Gemini',
                'status' => 'success',
                'message' => 'Response generated successfully.',
            ];

            $this->response = $result->text;
        } catch (\Throwable $e) {
            $this->error = 'All providers failed: '.$e->getMessage();
        } finally {
            $this->isLoading = false;
        }
    }

    public function render(): View
    {
        return view('livewire.demo.alert-writer')
            ->title('Mission-Critical Alert Writer');
    }
}
