<?php

declare(strict_types=1);

namespace App\Livewire\Components;

use Livewire\Component;
use Livewire\Attributes\On;

class ProgressBar extends Component
{
    public string $title = '';

    public int $percentage = 0;

    public string $statusText = '';

    public string $infoText = '';

    public bool $isVisible = false;

    public bool $isCompleted = false;

    public bool $hasError = false;

    public string $errorMessage = '';

    // Detail panel properties
    public array $details = [];

    public int $startTime = 0;

    public int $elapsedSeconds = 0;

    #[On('progress:start')]
    public function start(string $title, array $initialDetails = []): void
    {
        $this->title = $title;
        $this->percentage = 0;
        $this->statusText = 'Memulai...';
        $this->infoText = '';
        $this->isVisible = true;
        $this->isCompleted = false;
        $this->hasError = false;
        $this->errorMessage = '';
        $this->startTime = time();
        $this->elapsedSeconds = 0;
        $this->details = $initialDetails;
    }

    #[On('progress:update')]
    public function update(int $percentage, string $statusText = '', string $infoText = '', array $details = []): void
    {
        $this->percentage = min(100, max(0, $percentage));
        $this->statusText = $statusText ?: $this->statusText;
        $this->infoText = $infoText ?: $this->infoText;
        $this->elapsedSeconds = max(0, time() - $this->startTime);
        if ($details) {
            $this->details = array_merge($this->details, $details);
        }
    }

    #[On('progress:complete')]
    public function complete(?string $message = null): void
    {
        $this->percentage = 100;
        $this->statusText = $message ?? 'Selesai';
        $this->isCompleted = true;
    }

    #[On('progress:error')]
    public function error(string $message): void
    {
        $this->hasError = true;
        $this->errorMessage = $message;
        $this->isCompleted = true;
    }

    #[On('progress:close')]
    public function close(): void
    {
        $this->isVisible = false;
        $this->reset();
    }

    public function render()
    {
        return view('livewire.components.progress-bar');
    }
}
