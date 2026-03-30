<?php

declare(strict_types=1);

namespace App\Livewire\Demo;

use Illuminate\View\View;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Image;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.demo')]
final class BlogGenerator extends Component
{
    public string $topic = '';

    public string $article = '';

    public string $imageData = '';

    public string $imageMime = '';

    public string $error = '';

    public function generate(): void
    {
        $this->reset('article', 'imageData', 'imageMime', 'error');

        if (blank($this->topic)) {
            $this->error = 'Please enter a blog topic.';

            return;
        }

        try {
            $agent = new AnonymousAgent(
                'You are a professional blog writer. Write a 3-paragraph blog post with a catchy title on the given topic. Use markdown formatting with # for the title.',
                messages: [],
                tools: [],
            );

            $textResult = $agent->prompt(
                "Write a blog post about: {$this->topic}",
                provider: 'gemini',
            );

            $this->article = $textResult->text;
        } catch (\Throwable $e) {
            $this->error = 'Text generation failed: '.$e->getMessage();

            return;
        }

        try {
            $imageResult = Image::of(
                "Professional blog featured image for an article about: {$this->topic}. Modern, clean, editorial photography style.",
            )->landscape()->generate('gemini');

            $image = $imageResult->firstImage();
            $this->imageData = $image->image;
            $this->imageMime = $image->mime ?? 'image/png';
        } catch (\Throwable $e) {
            $this->error = 'Image generation failed (article still shown): '.$e->getMessage();
        }
    }

    public function render(): View
    {
        return view('livewire.demo.blog-generator')
            ->title('Blog Post Generator');
    }
}
