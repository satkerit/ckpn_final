<?php

declare(strict_types=1);

namespace App\Livewire\Kalkulasi;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app', ['title' => 'Probabilitas Default (PD)'])]
class ProbabilitasDefaultIndex extends Component
{
    public string $activeTab = 'netflow';

    public function render(): View
    {
        return view('livewire.kalkulasi.probabilitas-default-index');
    }
}
