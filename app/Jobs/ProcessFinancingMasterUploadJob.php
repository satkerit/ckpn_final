<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\UploadBatchStatus;
use App\Imports\FinancingMasterUploadImport;
use App\Models\FinancingUploadBatch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

/**
 * Queued job untuk memproses upload Excel master data pembiayaan secara async.
 * Ref: PRD Bab 15
 *
 * TUJUAN:
 * Mengimpor master data akun pembiayaan dari file Excel ke tabel financing_accounts.
 * Data ini adalah data induk (master) yang menjadi referensi untuk seluruh data historis
 * per periode (financing_account_periods). Wajib tersedia sebelum upload data periode.
 *
 * PROSES:
 * 1. Validasi idempotency — skip jika batch sudah berstatus Done.
 * 2. Proses import via FinancingMasterUploadImport (Maatwebsite Excel dengan validasi baris).
 *    Import menggunakan upsert berdasarkan account_number (update jika sudah ada).
 * 3. Kumpulkan error dari failures() dan custom errors dari getErrors().
 * 4. Update batch: status=Done, imported_rows, failed_rows, error_summary.
 *    Jika exception fatal → status=Failed + error_summary = pesan exception.
 *
 * FORMAT FILE EXCEL:
 *   Kolom sesuai FinancingMasterUploadImport (lihat class import untuk mapping kolom).
 *   Kolom penting: account_number (PK), usage_type, akad_code, office_code, dst.
 *
 * ERROR HANDLING:
 *   Error per baris dicatat di error_summary (format: "Row N: pesan error").
 *   Import dilanjutkan meski ada baris yang gagal (tidak stop-on-first-error).
 *   Max retry: 3x, timeout: 600 detik.
 */
class ProcessFinancingMasterUploadJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 600;

    public function __construct(
        private readonly int $batchId,
        private readonly string $filePath,
    ) {}

    public function handle(): void
    {
        $batch = FinancingUploadBatch::findOrFail($this->batchId);

        // Idempotency guard
        if ($batch->status === UploadBatchStatus::Done) {
            return;
        }

        $batch->update(['status' => UploadBatchStatus::Processing]);

        try {
            $import = new FinancingMasterUploadImport($batch);
            Excel::import($import, $this->filePath, null, \Maatwebsite\Excel\Excel::XLSX);

            $failures = $import->failures();
            $failureMessages = $failures->map(fn ($f) => "Row {$f->row()}: ".implode(', ', $f->errors()))->all();
            $allErrors = array_merge($import->getErrors(), $failureMessages);

            $batch->update([
                'status' => UploadBatchStatus::Done,
                'imported_rows' => $import->getImportedRows(),
                'failed_rows' => count($allErrors),
                'error_summary' => $allErrors ?: null,
            ]);
        } catch (Throwable $e) {
            $batch->update([
                'status' => UploadBatchStatus::Failed,
                'error_summary' => [$e->getMessage()],
            ]);
            throw $e;
        }
    }
}
