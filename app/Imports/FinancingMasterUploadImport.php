<?php

declare(strict_types=1);

namespace App\Imports;

use App\Enums\UsageType;
use App\Models\FinancingAccount;
use App\Models\FinancingUploadBatch;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

/**
 * Import master data pembiayaan dari Excel.
 * Ref: PRD Bab 15 — kolom financing_accounts
 *
 * Kolom yang diharapkan (heading row):
 *   account_number | customer_name | product_code | akad_code | office_code |
 *   economic_sector | usage_type
 * Tanggal akad/jatuh tempo diisi lewat upload historis periode (tgleff/tglexp).
 */
class FinancingMasterUploadImport implements SkipsOnFailure, ToCollection, WithHeadingRow, WithValidation
{
    use SkipsFailures;

    private int $importedRows = 0;

    private array $errors = [];

    public function __construct(
        private readonly FinancingUploadBatch $batch,
    ) {}

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            try {
                $this->processRow($row->toArray());
                $this->importedRows++;
            } catch (\Throwable $e) {
                $this->errors[] = "Row {$this->importedRows}: {$e->getMessage()}";
            }
        }
    }

    /**
     * Process a single row — updateOrCreate FinancingAccount.
     *
     * @param  array<string, mixed>  $row
     */
    private function processRow(array $row): void
    {
        $accountNumber = trim((string) ($row['account_number'] ?? ''));

        if ($accountNumber === '') {
            return;
        }

        $usageType = $this->parseUsageType($row['usage_type'] ?? null);

        FinancingAccount::updateOrCreate(
            ['account_number' => $accountNumber],
            [
                'customer_name' => $row['customer_name'] ?? null,
                'product_code' => $row['product_code'] ?? null,
                'akad_code' => $row['akad_code'] ?? null,
                'office_code' => $row['office_code'] ?? null,
                'economic_sector' => $row['economic_sector'] ?? null,
                'usage_type' => $usageType,
                'is_active' => true,
            ]
        );
    }

    public function rules(): array
    {
        return [
            'account_number' => ['required'],
        ];
    }

    public function getImportedRows(): int
    {
        return $this->importedRows;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Map nilai usage_type: 1=ModalKerja, 2=Investasi, 3=Konsumsi.
     */
    private function parseUsageType(mixed $value): ?UsageType
    {
        if (empty($value)) {
            return null;
        }

        $int = (int) $value;

        return UsageType::tryFrom($int);
    }
}
