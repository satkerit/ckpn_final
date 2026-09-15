<?php

declare(strict_types=1);

namespace App\Imports;

use App\Models\FinancingOffice;
use App\Models\FinancingUploadBatch;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

/**
 * Import master data kantor pembiayaan dari Excel.
 * Ref: PRD Bab 15 — kolom financing_offices
 *
 * Kolom yang diharapkan (heading row):
 *   code | name | is_active
 */
class FinancingOfficeUploadImport implements SkipsOnFailure, ToCollection, WithHeadingRow, WithValidation
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
     * Process a single row — updateOrCreate FinancingOffice.
     *
     * @param  array<string, mixed>  $row
     */
    private function processRow(array $row): void
    {
        $code = trim((string) ($row['code'] ?? ''));

        if ($code === '') {
            return;
        }

        FinancingOffice::updateOrCreate(
            ['code' => $code],
            [
                'name' => trim((string) ($row['name'] ?? '')),
                'is_active' => $this->parseBoolean($row['is_active'] ?? 1),
            ]
        );
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:255'],
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

    private function parseBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array((string) $value, ['1', 'true', 'yes', 'aktif'], true);
    }
}
