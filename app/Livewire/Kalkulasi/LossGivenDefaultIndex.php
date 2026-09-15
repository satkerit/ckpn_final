<?php

declare(strict_types=1);

namespace App\Livewire\Kalkulasi;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app', ['title' => 'Loss Given Default (LGD)'])]
class LossGivenDefaultIndex extends Component
{
    public string $activeTab = 'er';

    public function render(): View
    {
        return view('livewire.kalkulasi.loss-given-default-index');
    }
}
