<?php

declare(strict_types=1);

namespace App\Livewire\Ckpn;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app', ['title' => 'Periode & Klasifikasi'])]
class PeriodeKlasifikasiIndex extends Component
{
    public string $activeTab = 'periode';

    public bool $showParameterTab = true;

    public function render(): View
    {
        return view('livewire.ckpn.periode-klasifikasi-index')
            ->with([
                'showParameterTab' => true,
            ]);
    }
}
