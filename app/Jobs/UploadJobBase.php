<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\UploadBatchStatus;
use App\Models\FinancingUploadBatch;
use App\Traits\HasProgressTracking;
use App\Traits\StreamableExcelUpload;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Base class untuk semua upload job (template method pattern).
 *
 * Menyediakan lifecycle seragam: guard batch → idempotency → file check →
 * status Processing → process() → markFailed() saat exception.
 * Subclass hanya mengimplementasikan process().
 *
 * Ref: AGENTS.md Bab 11.2 — ekstrak logic yang dipakai >1 tempat.
 */
abstract class UploadJobBase implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels, StreamableExcelUpload, HasProgressTracking;

    public int $tries = 3;

    public function __construct(
        protected readonly int $batchId,
        protected readonly string $filePath,
    ) {}

    /**
     * Template method: lifecycle batch upload yang seragam untuk semua jenis upload.
     */
    final public function handle(): void
    {
        $batch = FinancingUploadBatch::find($this->batchId);
        if ($batch === null) {
            return;
        }

        // Idempotency guard (Ref: PRD Bab 16)
        if ($batch->status === UploadBatchStatus::Done) {
            return;
        }

        if (! file_exists($this->filePath)) {
            $this->markFailed($batch, ["File tidak ditemukan: {$this->filePath}"]);

            return;
        }

        $batch->update(['status' => UploadBatchStatus::Processing]);

        try {
            $this->process($batch);
        } catch (Throwable $e) {
            Log::error(static::class.': Critical failure', [
                'batch_id' => $this->batchId,
                'file_path' => $this->filePath,
                'error' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            $this->markFailed($batch, [$e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Implementasi parsing per jenis upload.
     */
    abstract protected function process(FinancingUploadBatch $batch): void;

    /**
     * Tandai batch gagal dengan ringkasan error.
     *
     * @param  array<int, string|array<string, mixed>>  $errors
     */
    protected function markFailed(FinancingUploadBatch $batch, array $errors): void
    {
        $batch->update([
            'status' => UploadBatchStatus::Failed,
            'error_summary' => $errors,
        ]);

        $this->failProgress(is_string($errors[0] ?? null) ? $errors[0] : 'Upload gagal');
    }
}
