<?php

declare(strict_types=1);

namespace App\Livewire\Reporting;

use App\Enums\UsageType;
use App\Exports\CkpnSummaryExport;
use App\Models\CkpnCollectiveResult;
use App\Models\CkpnIndividualResult;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Ref: PRD FR-12 */
#[Layout('layouts.app', ['title' => 'Ringkasan CKPN'])]
class CkpnSummaryIndex extends Component
{
    public string $filterPeriod = '';

    public string $search = '';

    public function exportExcel(): BinaryFileResponse
    {
        return Excel::download(new CkpnSummaryExport, 'ringkasan-ckpn.xlsx');
    }

    public function render(): View
    {
        // Periode unik untuk dropdown filter (gabungan dari kolektif & individual)
        $periods = CkpnCollectiveResult::query()
            ->select('calculation_period')
            ->distinct()
            ->pluck('calculation_period')
            ->merge(
                CkpnIndividualResult::query()
                    ->select('calculation_period')
                    ->distinct()
                    ->pluck('calculation_period')
            )
            ->unique()
            ->sortDesc()
            ->values();

        // Agregasi kolektif per periode + usage_type
        $collectiveQuery = CkpnCollectiveResult::query()
            ->select([
                'calculation_period',
                'usage_type',
                DB::raw('SUM(ckpn_amount) as total_collective'),
                DB::raw('COUNT(*) as count_collective'),
            ])
            ->groupBy('calculation_period', 'usage_type')
            ->orderByDesc('calculation_period');

        if ($this->filterPeriod !== '') {
            $collectiveQuery->where('calculation_period', $this->filterPeriod);
        }

        $collectiveRows = $collectiveQuery->get()->keyBy(
            fn ($row) => $row->calculation_period.'_'.$row->usage_type->value
        );

        // Agregasi individual per periode + usage_type (via JOIN ke calculation_run_logs)
        $individualQuery = CkpnIndividualResult::query()
            ->select([
                'ckpn_individual_result.calculation_period',
                'calculation_run_log.usage_type',
                DB::raw('SUM(ckpn_individual_result.ckpn_amount) as total_individual'),
                DB::raw('COUNT(*) as count_individual'),
            ])
            ->join('calculation_run_log', 'calculation_run_log.id', '=', 'ckpn_individual_result.calculation_run_log_id')
            ->groupBy('ckpn_individual_result.calculation_period', 'calculation_run_log.usage_type')
            ->orderByDesc('ckpn_individual_result.calculation_period');

        if ($this->filterPeriod !== '') {
            $individualQuery->where('ckpn_individual_result.calculation_period', $this->filterPeriod);
        }

        $individualRows = $individualQuery->get()->keyBy(
            fn ($row) => $row->calculation_period.'_'.$row->usage_type
        );

        // Helper: bangun objek baris dari satu collective row
        $buildRow = function ($collective) use ($individualRows): object {
            $lookupKey = $collective->calculation_period.'_'.$collective->usage_type->value;
            $individual = $individualRows->get($lookupKey);
            $totalIndividual = $individual ? (float) $individual->total_individual : 0.0;
            $totalCollective = (float) $collective->total_collective;

            return (object) [
                'period' => $collective->calculation_period,
                'usage_type' => $collective->usage_type,
                'total_collective' => $totalCollective,
                'count_collective' => (int) $collective->count_collective,
                'total_individual' => $totalIndividual,
                'grand_total' => $totalIndividual + $totalCollective,
            ];
        };

        $searchLower = strtolower(trim($this->search));

        // 1. Data dikelompokkan per usage_type untuk 3 tabel terpisah — Ref: PRD FR-12
        $rowsGrouped = collect(UsageType::cases())->mapWithKeys(
            function (UsageType $type) use ($collectiveRows, $buildRow, $searchLower): array {
                $rows = $collectiveRows
                    ->filter(fn ($c) => $c->usage_type === $type)
                    ->map($buildRow)
                    ->filter(
                        fn ($r) => $searchLower === '' ||
                            str_contains(strtolower((string) $r->period), $searchLower)
                    )
                    ->values();

                return [$type->value => ['type' => $type, 'rows' => $rows]];
            }
        );

        // 2. Data Konsolidasi Total CKPN per Periode (Gabungan Seluruh Segmen)
        $collectiveByPeriod = CkpnCollectiveResult::query()
            ->select([
                'calculation_period',
                DB::raw('SUM(ckpn_amount) as total_collective'),
                DB::raw('COUNT(*) as count_collective'),
            ])
            ->when($this->filterPeriod !== '', fn ($q) => $q->where('calculation_period', $this->filterPeriod))
            ->groupBy('calculation_period')
            ->get()
            ->keyBy('calculation_period');

        $individualByPeriod = CkpnIndividualResult::query()
            ->select([
                'calculation_period',
                DB::raw('SUM(ckpn_amount) as total_individual'),
                DB::raw('COUNT(*) as count_individual'),
            ])
            ->when($this->filterPeriod !== '', fn ($q) => $q->where('calculation_period', $this->filterPeriod))
            ->groupBy('calculation_period')
            ->get()
            ->keyBy('calculation_period');

        $allPeriodsForConsolidated = $collectiveByPeriod->keys()
            ->merge($individualByPeriod->keys())
            ->unique()
            ->sortDesc()
            ->values();

        $consolidatedRows = $allPeriodsForConsolidated->map(function ($period) use ($collectiveByPeriod, $individualByPeriod) {
            $col = $collectiveByPeriod->get($period);
            $ind = $individualByPeriod->get($period);

            $totalCol = $col ? (float) $col->total_collective : 0.0;
            $countCol = $col ? (int) $col->count_collective : 0;
            $totalInd = $ind ? (float) $ind->total_individual : 0.0;
            $countInd = $ind ? (int) $ind->count_individual : 0;

            return (object) [
                'period' => $period,
                'total_individual' => $totalInd,
                'count_individual' => $countInd,
                'total_collective' => $totalCol,
                'count_collective' => $countCol,
                'total_accounts' => $countInd + $countCol,
                'grand_total' => $totalInd + $totalCol,
            ];
        })->filter(
            fn ($r) => $searchLower === '' || str_contains(strtolower((string) $r->period), $searchLower)
        )->values();

        // 3. Ringkasan Konsolidasi Keseluruhan (Summary Metrics)
        $consolidatedSummary = (object) [
            'total_individual' => (float) $consolidatedRows->sum('total_individual'),
            'count_individual' => (int) $consolidatedRows->sum('count_individual'),
            'total_collective' => (float) $consolidatedRows->sum('total_collective'),
            'count_collective' => (int) $consolidatedRows->sum('count_collective'),
            'total_accounts' => (int) $consolidatedRows->sum('total_accounts'),
            'grand_total' => (float) $consolidatedRows->sum('grand_total'),
        ];

        // $rows flat untuk ekspor Excel
        $rows = $collectiveRows->map($buildRow)
            ->filter(
                fn ($r) => $searchLower === '' ||
                    str_contains(strtolower((string) $r->period), $searchLower) ||
                    str_contains(strtolower((string) ($r->usage_type->value ?? '')), $searchLower)
            )
            ->values();

        return view('livewire.reporting.ckpn-summary-index', compact(
            'rows',
            'rowsGrouped',
            'periods',
            'consolidatedRows',
            'consolidatedSummary'
        ));
    }
}
