<?php

declare(strict_types=1);

namespace App\Imports;

use App\Models\CollateralType;
use App\Models\FinancingUploadBatch;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

/**
 * Import master data jenis jaminan dari Excel.
 * Ref: PRD Bab 10 & 15 — kolom collateral_types
 *
 * Kolom yang diharapkan (heading row):
 *   code | name | liquidation_discount_rate | is_active
 */
class CollateralTypeUploadImport implements SkipsOnFailure, ToCollection, WithHeadingRow, WithValidation
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
     * Process a single row — updateOrCreate CollateralType.
     *
     * @param  array<string, mixed>  $row
     */
    private function processRow(array $row): void
    {
        $code = trim((string) ($row['code'] ?? ''));

        if ($code === '') {
            return;
        }

        CollateralType::updateOrCreate(
            ['code' => $code],
            [
                'name' => trim((string) ($row['name'] ?? '')),
                'liquidation_discount_rate' => $this->parseRate($row['liquidation_discount_rate'] ?? null),
                'is_active' => $this->parseBoolean($row['is_active'] ?? 1),
            ]
        );
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:255'],
            'liquidation_discount_rate' => ['nullable', 'numeric', 'min:0', 'max:1'],
        ];
    }

    public function getImportedRows(): int
    {
        return $this->importedRows;
    }

    /** @return array<string> */
    public function getErrors(): array
    {
        return $this->errors;
    }

    private function parseRate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $cleaned = str_replace([',', ' '], ['', ''], (string) $value);

        return is_numeric($cleaned) ? $cleaned : null;
    }

    private function parseBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array((string) $value, ['1', 'true', 'yes', 'aktif'], true);
    }
}
