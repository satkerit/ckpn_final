<?php

declare(strict_types=1);

namespace App\Exports;

use App\Exports\Sheets\IndividualSheet;
use App\Exports\Sheets\KolektifSheet;
use App\Exports\Sheets\LgdCsSheet;
use App\Exports\Sheets\LgdErSheet;
use App\Exports\Sheets\PdDetailSheet;
use App\Exports\Sheets\PdNominatifSheet;
use App\Exports\Sheets\RekapitulasiSheet;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Entry point export Laporan CKPN Final — multi-sheet.
 * Sheet: Rekapitulasi | CKPN Kolektif | CKPN Individual | Detail PD | Nominatif PD | LGD ER | LGD CS
 * Ref: PRD Bab 6.1, 7, 8, 9, 10, 11
 */
class CkpnFinalReportExport implements Export, WithMultipleSheets
{
    use Exportable;

    public function __construct(
        private readonly string $filterPeriod,
        private readonly string $filterUsageType,
        private readonly string $search,
    ) {}

    /** @return array<int, mixed> */
    public function sheets(): array
    {
        return [
            new RekapitulasiSheet($this->filterPeriod, $this->filterUsageType),
            new KolektifSheet($this->filterPeriod, $this->filterUsageType, $this->search),
            new IndividualSheet($this->filterPeriod, $this->search),
            new PdDetailSheet($this->filterPeriod, $this->filterUsageType),
            new PdNominatifSheet($this->filterPeriod, $this->search),
            new LgdErSheet($this->filterPeriod, $this->filterUsageType),
            new LgdCsSheet($this->filterPeriod, $this->filterUsageType, $this->search),
        ];
    }
}
