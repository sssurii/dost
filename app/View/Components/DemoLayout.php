<?php

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

final class DemoLayout extends Component
{
    public function __construct(
        public string $title = 'Laravel AI SDK Demo',
    ) {}

    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        return view('layouts.demo');
    }
}
