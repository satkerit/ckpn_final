<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\UploadBatchStatus;
use App\Imports\FinancingOfficeUploadImport;
use App\Models\FinancingUploadBatch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

/**
 * Queued job untuk memproses upload Excel master data kantor pembiayaan secara async.
 * Ref: PRD Bab 15
 *
 * TUJUAN:
 * Mengimpor data kantor pembiayaan (financing offices) dari file Excel ke tabel
 * financing_offices. Data ini digunakan sebagai referensi hierarki kantor untuk
 * pengelompokan akun pembiayaan di seluruh modul kalkulasi.
 *
 * PROSES:
 * 1. Validasi idempotency — skip jika batch sudah berstatus Done.
 * 2. Proses import via FinancingOfficeUploadImport (Maatwebsite Excel dengan validasi baris).
 * 3. Kumpulkan error dari failures() dan custom errors dari getErrors().
 * 4. Update batch: status=Done, imported_rows, failed_rows, error_summary.
 *    Jika exception fatal → status=Failed + error_summary = pesan exception.
 *
 * FORMAT FILE EXCEL:
 *   Kolom sesuai FinancingOfficeUploadImport (lihat class import untuk mapping kolom).
 *   Header baris pertama diabaikan oleh import class.
 *
 * ERROR HANDLING:
 *   Error per baris dicatat di error_summary (format: "Row N: pesan error").
 *   Import dilanjutkan meski ada baris yang gagal (tidak stop-on-first-error).
 *   Max retry: 3x, timeout: 300 detik.
 */
class ProcessFinancingOfficeUploadJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

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
            $import = new FinancingOfficeUploadImport($batch);
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
