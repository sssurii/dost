<?php

declare(strict_types=1);

namespace App\Livewire\Demo;

use App\Models\DemoDocument;
use App\Support\CosineSimilarity;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Laravel\Ai\Embeddings;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.demo')]
final class SmartHelpDesk extends Component
{
    public string $query = '';

    /** @var array<int, array{title: string, content: string, score: float}> */
    public array $results = [];

    public string $error = '';

    public bool $hasSearched = false;

    public function search(): void
    {
        $this->reset('results', 'error');
        $this->hasSearched = true;

        if (blank($this->query)) {
            $this->error = 'Please enter a search query.';

            return;
        }

        try {
            $queryEmbedding = Embeddings::for([$this->query])->generate('gemini');
            $queryVector = $queryEmbedding->embeddings[0];

            $documents = DemoDocument::query()->whereNotNull('embedding')->get();

            if ($documents->isEmpty()) {
                $this->error = 'No documents found. Run: ./bin/artisan demo:seed-embeddings';

                return;
            }

            $scored = $documents->map(function (DemoDocument $doc) use ($queryVector) {
                /** @var float[] $embedding */
                $embedding = $doc->embedding;

                return [
                    'title' => $doc->title,
                    'content' => $doc->content,
                    'score' => CosineSimilarity::calculate($queryVector, $embedding),
                ];
            })
                ->sortByDesc('score')
                ->take(5)
                ->values()
                ->all();

            $this->results = $scored;
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    /**
     * @return Collection<int, DemoDocument>
     */
    #[Computed]
    public function allDocuments(): Collection
    {
        return DemoDocument::query()->orderBy('id')->get();
    }

    public function render(): View
    {
        return view('livewire.demo.smart-help-desk')
            ->title('Smart Help Desk');
    }
}
