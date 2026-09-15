<?php

declare(strict_types=1);

namespace App\Livewire\Reporting;

use App\Enums\UsageType;
use App\Exports\CkpnFinalReportExport;
use App\Models\CkpnCollectiveResult;
use App\Models\CkpnIndividualResult;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Ref: PRD Bab 6.1 & 11 — Laporan CKPN Final per nasabah */
#[Layout('layouts.app', ['title' => 'Laporan CKPN Final'])]
class CkpnFinalReportIndex extends Component
{
    use WithPagination;

    public string $filterPeriod = '';

    public string $filterUsageType = '';

    public string $search = '';

    /** Export Laporan CKPN Final ke Excel (3 sheet). Ref: PRD Bab 6.1 & 11 */
    public function exportExcel(): BinaryFileResponse
    {
        $filename = 'laporan-ckpn-final'
            .($this->filterPeriod !== '' ? '-'.$this->filterPeriod : '')
            .'-'.now()->format('Ymd_His')
            .'.xlsx';

        return Excel::download(
            new CkpnFinalReportExport($this->filterPeriod, $this->filterUsageType, $this->search),
            $filename,
        );
    }

    /** Reset halaman saat filter berubah */
    public function updatingFilterPeriod(): void
    {
        $this->resetPage();
    }

    public function updatingFilterUsageType(): void
    {
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        // Daftar periode unik dari kedua tabel hasil
        $periods = CkpnCollectiveResult::query()
            ->select('calculation_period')
            ->distinct()
            ->orderByDesc('calculation_period')
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

        $usageTypes = UsageType::cases();

        // ── CKPN Kolektif — detail per nasabah ─────────────────────────────
        $collectiveQuery = CkpnCollectiveResult::query()
            ->select([
                'ckpn_collective_result.id',
                'ckpn_collective_result.calculation_period',
                'ckpn_collective_result.financing_account_id',
                'ckpn_collective_result.usage_type',
                'ckpn_collective_result.pd_method_used',
                'ckpn_collective_result.pd_rate',
                'ckpn_collective_result.lgd_method_used',
                'ckpn_collective_result.lgd_rate',
                'ckpn_collective_result.ead',
                'ckpn_collective_result.ckpn_amount',
                'financing_accounts.account_number',
                'financing_accounts.customer_name',
                // Total nilai agunan aktif per nasabah (SUM estimated_sale_value)
                DB::raw('(SELECT COALESCE(SUM(c.estimated_sale_value), 0)
                          FROM collaterals c
                          WHERE c.financing_account_id = ckpn_collective_result.financing_account_id
                            AND c.is_active = 1) as total_collateral_value'),
            ])
            ->join('financing_accounts', 'financing_accounts.id', '=', 'ckpn_collective_result.financing_account_id');

        if ($this->filterPeriod !== '') {
            $collectiveQuery->where('ckpn_collective_result.calculation_period', $this->filterPeriod);
        }
        if ($this->filterUsageType !== '') {
            $collectiveQuery->where('ckpn_collective_result.usage_type', $this->filterUsageType);
        }
        if ($this->search !== '') {
            $collectiveQuery->where(function ($q): void {
                $q->where('financing_accounts.account_number', 'like', '%'.$this->search.'%')
                    ->orWhere('financing_accounts.customer_name', 'like', '%'.$this->search.'%');
            });
        }

        $collectiveRows = $collectiveQuery
            ->orderByDesc('ckpn_collective_result.calculation_period')
            ->orderBy('financing_accounts.account_number')
            ->paginate(50, ['*'], 'page_col');

        // ── CKPN Individual — detail per nasabah ───────────────────────────
        $individualQuery = CkpnIndividualResult::query()
            ->select([
                'ckpn_individual_result.id',
                'ckpn_individual_result.calculation_period',
                'ckpn_individual_result.financing_account_id',
                'ckpn_individual_result.outstanding_balance',
                'ckpn_individual_result.total_collateral_liquidation_value',
                'ckpn_individual_result.ckpn_amount',
                'ckpn_individual_result.collectibility',
                'financing_accounts.account_number',
                'financing_accounts.customer_name',
            ])
            ->join('financing_accounts', 'financing_accounts.id', '=', 'ckpn_individual_result.financing_account_id');

        if ($this->filterPeriod !== '') {
            $individualQuery->where('ckpn_individual_result.calculation_period', $this->filterPeriod);
        }
        if ($this->search !== '') {
            $individualQuery->where(function ($q): void {
                $q->where('financing_accounts.account_number', 'like', '%'.$this->search.'%')
                    ->orWhere('financing_accounts.customer_name', 'like', '%'.$this->search.'%');
            });
        }

        $individualRows = $individualQuery
            ->orderByDesc('ckpn_individual_result.calculation_period')
            ->orderBy('financing_accounts.account_number')
            ->paginate(50, ['*'], 'page_ind');

        // ── Totals (query terpisah tanpa pagination, ikuti filter) ──────────
        $colTotals = $this->buildCollectiveTotalsQuery();
        $indTotals = $this->buildIndividualTotalsQuery();

        // ── Rangkuman CKPN Kolektif per Bucket per Segmen ──────────────────
        $bucketSummary = $this->buildCollectiveBucketSummary();

        return view('livewire.reporting.ckpn-final-report-index', compact(
            'collectiveRows',
            'individualRows',
            'colTotals',
            'indTotals',
            'bucketSummary',
            'periods',
            'usageTypes',
        ));
    }

    /**
     * Agregasi CKPN Kolektif per bucket per segmen (usage_type) untuk ringkasan.
     * Bucket ditentukan dari tgkhari akun via join range buckets (Ref: PRD Bab 7.2).
     * Rate PD/LGD = rata-rata berbobot EAD per bucket.
     */
    private function buildCollectiveBucketSummary(): array
    {
        $query = DB::table('ckpn_collective_result as c')
            ->join('financing_account_periods as fap', function ($join): void {
                $join->on('fap.financing_account_id', '=', 'c.financing_account_id')
                    ->on('fap.period', '=', 'c.calculation_period');
            })
            ->join('buckets as b', function ($join): void {
                $join->whereRaw('fap.tgkhari >= b.min_days_overdue')
                    ->whereRaw('fap.tgkhari <= b.max_days_overdue');
            })
            ->join('financing_accounts as fa', 'fa.id', '=', 'c.financing_account_id')
            ->select([
                'c.usage_type',
                'b.id as bucket_id',
                'b.code as bucket_code',
                'b.label as bucket_label',
                'b.bucket_order',
                DB::raw('SUM(c.ead) as total_ead'),
                DB::raw('SUM(c.ckpn_amount) as total_ckpn'),
                DB::raw('SUM(c.pd_rate * c.ead) / NULLIF(SUM(c.ead), 0) as wavg_pd'),
                DB::raw('SUM(c.lgd_rate * c.ead) / NULLIF(SUM(c.ead), 0) as wavg_lgd'),
            ])
            ->groupBy('c.usage_type', 'b.id', 'b.code', 'b.label', 'b.bucket_order')
            ->orderBy('c.usage_type')
            ->orderBy('b.bucket_order');

        if ($this->filterPeriod !== '') {
            $query->where('c.calculation_period', $this->filterPeriod);
        }
        if ($this->filterUsageType !== '') {
            $query->where('c.usage_type', $this->filterUsageType);
        }
        if ($this->search !== '') {
            $query->where(function ($q): void {
                $q->where('fa.account_number', 'like', '%'.$this->search.'%')
                    ->orWhere('fa.customer_name', 'like', '%'.$this->search.'%');
            });
        }

        $rows = $query->get();

        // Kelompokkan per usage_type dengan sub-total
        return collect(UsageType::cases())
            ->mapWithKeys(function (UsageType $type) use ($rows): array {
                $segmentRows = $rows
                    ->where('usage_type', $type->value)
                    ->map(fn ($r) => (object) [
                        'bucket_code' => $r->bucket_code,
                        'bucket_label' => $r->bucket_label,
                        'total_ead' => (float) $r->total_ead,
                        'total_ckpn' => (float) $r->total_ckpn,
                        'wavg_pd' => (float) ($r->wavg_pd ?? 0),
                        'wavg_lgd' => (float) ($r->wavg_lgd ?? 0),
                    ])
                    ->values();

                return [$type->value => [
                    'type' => $type,
                    'rows' => $segmentRows,
                    'total_ead' => $segmentRows->sum('total_ead'),
                    'total_ckpn' => $segmentRows->sum('total_ckpn'),
                ]];
            })
            ->all();
    }

    /** Agregasi total CKPN Kolektif sesuai filter aktif */
    private function buildCollectiveTotalsQuery(): object
    {
        $q = CkpnCollectiveResult::query()
            ->select([
                DB::raw('COUNT(DISTINCT financing_account_id) as noa'),
                DB::raw('SUM(ead) as total_ead'),
                DB::raw('SUM(ckpn_amount) as total_ckpn'),
            ]);

        if ($this->filterPeriod !== '') {
            $q->where('calculation_period', $this->filterPeriod);
        }
        if ($this->filterUsageType !== '') {
            $q->where('usage_type', $this->filterUsageType);
        }
        if ($this->search !== '') {
            $q->whereHas('financingAccount', function ($fa): void {
                $fa->where('account_number', 'like', '%'.$this->search.'%')
                    ->orWhere('customer_name', 'like', '%'.$this->search.'%');
            });
        }

        $row = $q->first();

        return (object) [
            'noa' => (int) ($row->noa ?? 0),
            'total_ead' => (float) ($row->total_ead ?? 0),
            'total_ckpn' => (float) ($row->total_ckpn ?? 0),
        ];
    }

    /** Agregasi total CKPN Individual sesuai filter aktif */
    private function buildIndividualTotalsQuery(): object
    {
        $q = CkpnIndividualResult::query()
            ->select([
                DB::raw('COUNT(DISTINCT financing_account_id) as noa'),
                DB::raw('SUM(outstanding_balance) as total_ead'),
                DB::raw('SUM(ckpn_amount) as total_ckpn'),
            ]);

        if ($this->filterPeriod !== '') {
            $q->where('calculation_period', $this->filterPeriod);
        }
        if ($this->search !== '') {
            $q->whereHas('financingAccount', function ($fa): void {
                $fa->where('account_number', 'like', '%'.$this->search.'%')
                    ->orWhere('customer_name', 'like', '%'.$this->search.'%');
            });
        }

        $row = $q->first();

        return (object) [
            'noa' => (int) ($row->noa ?? 0),
            'total_ead' => (float) ($row->total_ead ?? 0),
            'total_ckpn' => (float) ($row->total_ckpn ?? 0),
        ];
    }
}
