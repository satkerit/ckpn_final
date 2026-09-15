<?php

declare(strict_types=1);

namespace App\Imports;

use App\Models\Collateral;
use App\Models\CollateralType;
use App\Models\FinancingAccount;
use App\Models\FinancingUploadBatch;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

/**
 * Import data jaminan dari Excel.
 * Ref: PRD Bab 10 & 15 — kolom collaterals
 *
 * Kolom yang diharapkan (heading row):
 *   account_number | collateral_code | sequence_number | collateral_type_code | description |
 *   appraisal_value | estimated_sale_value | appraised_at | is_active
 */
class CollateralUploadImport implements SkipsOnFailure, ToCollection, WithHeadingRow, WithValidation
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
     * Process a single row — updateOrCreate Collateral.
     *
     * @param  array<string, mixed>  $row
     */
    private function processRow(array $row): void
    {
        $accountNumber = trim((string) ($row['account_number'] ?? ''));
        $collateralCode = trim((string) ($row['collateral_code'] ?? ''));
        $sequenceNumber = isset($row['sequence_number']) && $row['sequence_number'] !== '' ? (int) $row['sequence_number'] : 1;
        $collateralTypeCode = trim((string) ($row['collateral_type_code'] ?? ''));

        if ($accountNumber === '' || $collateralCode === '') {
            return;
        }

        // Resolve FinancingAccount — coba exact match dulu, lalu suffix match
        $account = FinancingAccount::where('account_number', $accountNumber)->first()
            ?? FinancingAccount::where('account_number', 'like', '%'.$accountNumber)->first();

        if ($account === null) {
            $this->errors[] = "account_number '{$accountNumber}' tidak ditemukan di sistem.";

            return;
        }

        // Resolve CollateralType — skip jika tidak ditemukan
        $collateralType = CollateralType::where('code', $collateralTypeCode)->first();
        if ($collateralType === null) {
            $this->errors[] = "collateral_type_code '{$collateralTypeCode}' tidak ditemukan di master jenis jaminan.";

            return;
        }

        $isActive = isset($row['is_active']) && (string) $row['is_active'] !== ''
            ? (bool) (int) $row['is_active']
            : true;

        Collateral::updateOrCreate(
            [
                'financing_account_id' => $account->id,
                'collateral_code' => $collateralCode,
                'sequence_number' => $sequenceNumber,
            ],
            [
                'collateral_type_id' => $collateralType->id,
                'description' => $row['description'] ?? null,
                'appraisal_value' => $this->parseDecimal($row['appraisal_value'] ?? null),
                'estimated_sale_value' => $this->parseDecimal($row['estimated_sale_value'] ?? null),
                'appraised_at' => $this->parseDate($row['appraised_at'] ?? null),
                'is_active' => $isActive,
            ]
        );
    }

    public function rules(): array
    {
        return [
            'account_number' => ['required'],
            'collateral_type_code' => ['required'],
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

    private function parseDecimal(mixed $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        $cleaned = str_replace([',', ' '], ['', ''], (string) $value);

        return is_numeric($cleaned) ? $cleaned : null;
    }

    /**
     * Parse tanggal dari format yyyymmdd (string int) atau format lain Carbon mengenali.
     */
    private function parseDate(mixed $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        $str = trim((string) $value);

        // Format yyyymmdd
        if (preg_match('/^\d{8}$/', $str)) {
            return Carbon::createFromFormat('Ymd', $str)?->toDateString();
        }

        // Format Excel serial number (integer)
        if (is_numeric($str) && strlen($str) <= 5) {
            return Carbon::createFromTimestamp(($str - 25569) * 86400)?->toDateString();
        }

        try {
            return Carbon::parse($str)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
