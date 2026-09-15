<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Models\CkpnCollectiveResult;
use App\Models\CkpnIndividualResult;
use App\Models\CkpnPeriod;
use App\Models\DataQualityAnomaly;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app', ['title' => 'Dashboard'])]
class Index extends Component
{
    public function render(): View
    {
        $activePeriods = CkpnPeriod::whereIn('status', ['draft', 'in_progress'])->count();
        $approvedPeriods = CkpnPeriod::where('status', 'approved')->count();
        $unresolvedAnomalies = DataQualityAnomaly::where('is_resolved', false)->count();

        $lastPeriod = CkpnPeriod::whereIn('status', ['completed', 'approved'])
            ->orderByDesc('period')
            ->value('period');

        $totalCkpn = 0.0;
        if ($lastPeriod) {
            $totalCkpn = (float) CkpnCollectiveResult::where('calculation_period', $lastPeriod)->sum('ckpn_amount')
                       + (float) CkpnIndividualResult::where('calculation_period', $lastPeriod)->sum('ckpn_amount');
        }

        $recentPeriods = CkpnPeriod::with(['createdBy', 'approvedBy'])
            ->orderByDesc('period')
            ->limit(10)
            ->get();

        return view('livewire.dashboard.index', compact(
            'activePeriods', 'approvedPeriods', 'unresolvedAnomalies',
            'totalCkpn', 'lastPeriod', 'recentPeriods'
        ));
    }
}
