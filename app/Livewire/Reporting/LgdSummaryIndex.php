<?php

declare(strict_types=1);

namespace App\Livewire\Reporting;

use App\Enums\UsageType;
use App\Models\LgdCollateralShortfallResult;
use App\Models\LgdExpectedRecoveriesResult;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Ref: PRD Bab 9 & 10 */
#[Layout('layouts.app', ['title' => 'Ringkasan LGD'])]
class LgdSummaryIndex extends Component
{
    public string $filterPeriod = '';

    public string $filterUsageType = '';

    public string $search = '';

    public function render(): View
    {
        // Daftar periode dari LGD ER (lebih representatif karena agregat per periode)
        $periods = LgdExpectedRecoveriesResult::query()
            ->select('calculation_period')
            ->distinct()
            ->orderByDesc('calculation_period')
            ->pluck('calculation_period');

        // LGD Expected Recoveries — agregat per periode + usage_type
        $erQuery = LgdExpectedRecoveriesResult::query()
            ->select([
                'calculation_period',
                'usage_type',
                DB::raw('AVG(lgd_rate) as avg_lgd_rate_er'),
                DB::raw('SUM(total_writeoff_amount) as sum_writeoff'),
                DB::raw('SUM(total_recovery_amount) as sum_recovery'),
                DB::raw('COUNT(*) as row_count_er'),
            ])
            ->groupBy('calculation_period', 'usage_type')
            ->orderByDesc('calculation_period');

        if ($this->filterPeriod !== '') {
            $erQuery->where('calculation_period', $this->filterPeriod);
        }
        if ($this->filterUsageType !== '') {
            $erQuery->where('usage_type', $this->filterUsageType);
        }

        $erRows = $erQuery->get()->keyBy(
            fn ($r) => $r->calculation_period.'_'.$r->usage_type->value
        );

        // LGD Collateral Shortfall — agregat per periode + usage_type
        $csQuery = LgdCollateralShortfallResult::query()
            ->select([
                'calculation_period',
                'usage_type',
                DB::raw('AVG(lgd_rate) as avg_lgd_rate_cs'),
                DB::raw('SUM(shortfall) as sum_shortfall'),
                DB::raw('SUM(outstanding_balance) as sum_outstanding'),
                DB::raw('COUNT(*) as row_count_cs'),
            ])
            ->groupBy('calculation_period', 'usage_type')
            ->orderByDesc('calculation_period');

        if ($this->filterPeriod !== '') {
            $csQuery->where('calculation_period', $this->filterPeriod);
        }
        if ($this->filterUsageType !== '') {
            $csQuery->where('usage_type', $this->filterUsageType);
        }

        $csRows = $csQuery->get()->keyBy(
            fn ($r) => $r->calculation_period.'_'.$r->usage_type->value
        );

        // Gabungkan semua kunci unik (periode_usagetype)
        $keys = $erRows->keys()->merge($csRows->keys())->unique()->sort();
        $usageTypes = UsageType::cases();

        $rows = $keys->map(function (string $key) use ($erRows, $csRows) {
            $er = $erRows->get($key);
            $cs = $csRows->get($key);

            // Ambil periode dan usage_type dari salah satu yang tersedia
            $ref = $er ?? $cs;

            return (object) [
                'period' => $ref->calculation_period,
                'usage_type' => $ref->usage_type,
                'avg_lgd_rate_er' => $er ? (float) $er->avg_lgd_rate_er : null,
                'sum_writeoff' => $er ? (float) $er->sum_writeoff : null,
                'sum_recovery' => $er ? (float) $er->sum_recovery : null,
                'row_count_er' => $er ? (int) $er->row_count_er : 0,
                'avg_lgd_rate_cs' => $cs ? (float) $cs->avg_lgd_rate_cs : null,
                'sum_shortfall' => $cs ? (float) $cs->sum_shortfall : null,
                'sum_outstanding' => $cs ? (float) $cs->sum_outstanding : null,
                'row_count_cs' => $cs ? (int) $cs->row_count_cs : 0,
            ];
        });

        $rows = $rows->filter(
            fn ($r) => ! $this->search ||
                str_contains(strtolower((string) $r->period), strtolower($this->search)) ||
                str_contains(strtolower((string) ($r->usage_type->value ?? '')), strtolower($this->search))
        )->values();

        return view('livewire.reporting.lgd-summary-index', compact('rows', 'periods', 'usageTypes'));
    }
}
