<?php

declare(strict_types=1);

namespace App\Livewire\Demo;

use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Laravel\Ai\Audio;
use Laravel\Ai\Transcription;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('layouts.demo')]
final class PodcastToolkit extends Component
{
    use WithFileUploads;

    public string $articleText = '';

    public string $episodeUrl = '';

    public string $ttsError = '';

    /** @var TemporaryUploadedFile|null */
    public $audioFile = null;

    public string $transcript = '';

    public string $sttError = '';

    public function generateEpisode(): void
    {
        $this->reset('episodeUrl', 'ttsError');

        if (blank($this->articleText)) {
            $this->ttsError = 'Please enter some text to convert to audio.';

            return;
        }

        $text = mb_substr(trim($this->articleText), 0, 4000);

        try {
            $response = Audio::of($text)
                ->voice('nova')
                ->instructions('Read this as a professional podcast host, with clear enunciation and natural pacing.')
                ->generate('openai');

            $filename = 'episode_'.time().'.mp3';
            $path = "demo/{$filename}";

            Storage::disk('public')->put($path, $response->content());

            $this->episodeUrl = Storage::disk('public')->url($path);
        } catch (\Throwable $e) {
            $this->ttsError = $e->getMessage();
        }
    }

    public function transcribe(): void
    {
        $this->reset('transcript', 'sttError');

        if (! $this->audioFile) {
            $this->sttError = 'Please upload an audio file.';

            return;
        }

        try {
            $response = Transcription::of($this->audioFile)
                ->language('en')
                ->generate('openai');

            $this->transcript = $response->text;
        } catch (\Throwable $e) {
            $this->sttError = $e->getMessage();
        }
    }

    public function render(): View
    {
        return view('livewire.demo.podcast-toolkit')
            ->title('Podcast Toolkit');
    }
}
