<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DemoDocument;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Laravel\Ai\Embeddings;

#[Signature('demo:seed-embeddings')]
#[Description('Seed the demo_documents table with knowledge base articles and generate embeddings via Gemini')]
final class SeedDemoEmbeddings extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $documents = $this->documents();

        $this->info('Truncating demo_documents...');
        DemoDocument::query()->truncate();

        $this->info('Inserting '.count($documents).' documents...');
        foreach ($documents as $doc) {
            DemoDocument::query()->create([
                'title' => $doc['title'],
                'content' => $doc['content'],
            ]);
        }

        $this->info('Generating embeddings via Gemini (single batch)...');
        $contents = array_column($documents, 'content');

        $response = Embeddings::for($contents)->generate('gemini');

        $models = DemoDocument::query()->orderBy('id')->get();

        foreach ($models as $i => $model) {
            $model->update(['embedding' => $response->embeddings[$i]]);
        }

        $this->info('✅ Done! '.$models->count().' documents seeded with embeddings.');

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{title: string, content: string}>
     */
    private function documents(): array
    {
        return [
            [
                'title' => 'Getting Started with Routing',
                'content' => 'Laravel routing allows you to define URL endpoints for your application. You can define routes in web.php using Route::get, Route::post, and other HTTP verbs. Route groups let you share middleware, prefixes, and namespaces across multiple routes. Named routes enable URL generation using the route() helper. Route parameters can be required or optional, with regex constraints for validation.',
            ],
            [
                'title' => 'Database Models & Relationships',
                'content' => 'Eloquent ORM provides an elegant ActiveRecord implementation for working with your database. Each table has a corresponding Model class. Relationships like belongsTo, hasMany, belongsToMany, and morphMany let you define connections between models. Query scopes add reusable constraints. Eager loading with with() prevents N+1 query problems when accessing related models.',
            ],
            [
                'title' => 'Building Dynamic Views',
                'content' => 'Blade is Laravel\'s templating engine that compiles templates into plain PHP for zero overhead. Template inheritance via @extends and @section lets you build consistent layouts. Components and slots create reusable UI pieces. Directives like @if, @foreach, @auth provide clean control flow. You can also use inline Blade components with x-prefix syntax for modern component-driven development.',
            ],
            [
                'title' => 'Background Job Processing',
                'content' => 'Laravel queues let you defer time-consuming tasks like sending emails or processing files. Jobs implement the ShouldQueue interface and are dispatched to queue backends like Redis, database, or SQS. Workers process jobs with configurable timeouts, retries, and backoff strategies. Failed jobs are stored for inspection and retry. Job batching lets you dispatch groups of jobs and track completion with callbacks.',
            ],
            [
                'title' => 'User Authentication & Security',
                'content' => 'Laravel provides robust authentication out of the box. Breeze and Jetstream offer starter kits with login, registration, and password reset. Guards define how users are authenticated for each request. API authentication uses Sanctum for token-based auth. Policies and gates control authorization logic. Password hashing, CSRF protection, and encryption are built in for security best practices.',
            ],
            [
                'title' => 'Automated Testing Guide',
                'content' => 'Pest and PHPUnit provide powerful testing frameworks for Laravel applications. Feature tests verify HTTP endpoints, form submissions, and database interactions. Unit tests validate individual classes and methods. Database factories generate fake model data for tests. Mocking with Mockery lets you isolate dependencies. RefreshDatabase trait ensures a clean state for each test. Browser testing with Laravel Dusk automates UI interaction testing.',
            ],
            [
                'title' => 'Event-Driven Architecture',
                'content' => 'Events decouple your application\'s concerns by letting classes communicate through dispatched events. Listeners handle the event logic and can be queued for async processing. Event subscribers group multiple event handlers in one class. Broadcasting sends events to WebSocket channels using Reverb or Pusher for real-time features. Model observers provide convenient hooks for Eloquent model lifecycle events like creating, updating, and deleting.',
            ],
            [
                'title' => 'HTTP Middleware Pipeline',
                'content' => 'Middleware filters HTTP requests entering your application. Common uses include authentication checks, CORS headers, rate limiting, and logging. Middleware can run before or after request handling. Route middleware applies to specific routes or groups. Global middleware runs on every request. Laravel includes built-in middleware for throttling, encryption, session handling, and maintenance mode.',
            ],
        ];
    }
}
