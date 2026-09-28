<?php

declare(strict_types=1);

namespace App\Exports;

use App\Domain\Ckpn\Services\PeriodHelper;
use App\Enums\CalculationMethodKey;
use App\Enums\UsageType;
use App\Models\CalculationDataRange;
use App\Models\FinancingAccountPeriod;
use Illuminate\Database\Eloquent\Builder;

/** Export data historis pembiayaan yang menjadi observasi PD Netflow. Ref: PRD Bab 7 */
final class FinancingHistoryExport
{
    public function __construct(
        private readonly string $calculationPeriod,
        private readonly string $usageType = '',
    ) {}

    public function query(): Builder
    {
        $usageTypeValue = $this->usageType !== '' ? (int) $this->usageType : null;
        $window = (int) CalculationDataRange::resolveValue(
            CalculationMethodKey::PdNetflow,
            'pd_netflow_rolling_window_months',
            usageType: $usageTypeValue,
            default: 36,
        );
        $start = PeriodHelper::shiftBack($this->calculationPeriod, $window);

        return FinancingAccountPeriod::query()
            ->select([
                'financing_account_periods.id',
                'financing_account_periods.financing_account_id',
                'financing_account_periods.period',
                'financing_account_periods.outstanding_balance',
                'financing_account_periods.collectibility',
                'financing_account_periods.tgkhari',
                'financing_account_periods.tgkmdl',
                'financing_accounts.account_number',
                'financing_accounts.customer_name',
                'financing_accounts.usage_type',
            ])
            ->join('financing_accounts', 'financing_accounts.id', '=', 'financing_account_periods.financing_account_id')
            ->whereBetween('financing_account_periods.period', [$start, $this->calculationPeriod])
            ->when($this->usageType !== '', fn ($query) => $query->where('financing_accounts.usage_type', $this->usageType))
            ->orderBy('financing_account_periods.id');
    }

    public function headings(): array
    {
        return ['No. Rekening', 'Nama Nasabah', 'Jenis Penggunaan', 'Periode', 'Outstanding (Rp)', 'Kolektibilitas', 'TGK Hari', 'TGK Modal'];
    }

    public function map(object $row): array
    {
        return [
            $row->account_number,
            $row->customer_name,
            $row->usage_type instanceof UsageType ? $row->usage_type->label() : (string) $row->usage_type,
            $row->period,
            number_format((float) $row->outstanding_balance, 2, '.', ','),
            $row->collectibility,
            $row->tgkhari,
            $row->tgkmdl,
        ];
    }
}
