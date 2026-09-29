<?php

declare(strict_types=1);

namespace App\Filament\Actions;

use App\Models\LgdCollateralShortfallResult;
use App\Models\LgdExpectedRecoveriesResult;
use Filament\Tables\Actions\Action;
use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportSnapshotAction extends Action
{
    public static function makeForLgdEr(?string $name = null): static
    {
        return parent::make($name ?? 'export_lgd_er')
            ->label('Export ke Excel')
            ->icon('heroicon-o-arrow-down-tray')
            ->action(function () {
                return self::exportLgdEr();
            });
    }

    public static function makeForLgdCs(?string $name = null): static
    {
        return parent::make($name ?? 'export_lgd_cs')
            ->label('Export ke Excel')
            ->icon('heroicon-o-arrow-down-tray')
            ->action(function () {
                return self::exportLgdCs();
            });
    }

    private static function exportLgdEr(): StreamedResponse
    {
        $results = LgdExpectedRecoveriesResult::query()
            ->with('calculationRunLog')
            ->orderBy('created_at', 'desc')
            ->get();

        $data = $results->map(function (LgdExpectedRecoveriesResult $result) {
            return [
                'Periode Kalkulasi' => $result->calculation_period,
                'Jenis Penggunaan' => $result->usage_type->label(),
                'Kode Kantor' => $result->office_code ?? '(Global)',
                'Window (Thn)' => $result->window_years,
                'LGD Rate' => number_format((float) $result->lgd_rate, 8),
                'Expected Recovery Rate' => number_format((float) $result->expected_recovery_rate, 8),
                'Total Writeoff' => number_format((float) $result->total_writeoff_amount, 2),
                'Total Recovery' => number_format((float) $result->total_recovery_amount, 2),
                'All Account' => $result->is_all_account ? 'Ya' : 'Tidak',
                'Job Status' => $result->calculationRunLog?->status->name ?? '—',
                'Data Periode Start' => $result->data_period_start,
                'Data Periode End' => $result->data_period_end,
                'Dibuat' => $result->created_at?->format('Y-m-d H:i:s') ?? '—',
            ];
        })->toArray();

        return Excel::download(
            new LgdErExport($data),
            'lgd_expected_recoveries_' . now()->format('Ymd_His') . '.xlsx'
        );
    }

    private static function exportLgdCs(): StreamedResponse
    {
        $results = LgdCollateralShortfallResult::query()
            ->with('calculationRunLog')
            ->orderBy('created_at', 'desc')
            ->get();

        $data = $results->map(function (LgdCollateralShortfallResult $result) {
            return [
                'Periode Kalkulasi' => $result->calculation_period,
                'Jenis Penggunaan' => $result->usage_type->label(),
                'Kode Kantor' => $result->office_code ?? '(Global)',
                'Jumlah Akun' => $result->account_count,
                'LGD Rate' => number_format((float) $result->lgd_rate, 8),
                'Total Outstanding' => number_format((float) $result->total_outstanding, 2),
                'Total Shortfall' => number_format((float) $result->total_shortfall, 2),
                'Total Collateral Value' => number_format((float) $result->total_collateral_value, 2),
                'Job Status' => $result->calculationRunLog?->status->name ?? '—',
                'Dibuat' => $result->created_at?->format('Y-m-d H:i:s') ?? '—',
            ];
        })->toArray();

        return Excel::download(
            new LgdCsExport($data),
            'lgd_collateral_shortfall_' . now()->format('Ymd_His') . '.xlsx'
        );
    }
}
