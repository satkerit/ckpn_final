<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UploadBatchStatus;
use App\Jobs\ProcessCollateralTypeUploadJob;
use App\Jobs\ProcessCollateralUploadJob;
use App\Jobs\ProcessFinancingMasterUploadJob;
use App\Jobs\ProcessFinancingOfficeUploadJob;
use App\Jobs\ProcessFinancingPeriodUploadJob;
use App\Models\FinancingUploadBatch;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Service untuk memproses upload data secara asynchronous via queue.
 *
 * REFACTOR NOTES:
 *   - Diubah dari dispatchSync() ke dispatch() untuk mendukung file besar (300k+ baris).
 *   - Proses berjalan di background via queue worker, tidak blocking HTTP request.
 *   - Pastikan queue worker berjalan: php artisan queue:work --queue=ckpn-calculation
 *
 * Ref: PRD Bab 3 — Upload Data Pembiayaan
 */
class UploadProcessorService
{
    /**
     * Dispatch job upload ke queue berdasarkan upload_type.
     */
    public function process(int $batchId, string $filePath, string $uploadType): void
    {
        $this->resolveAndDispatch($batchId, $filePath, $uploadType);
    }

    /**
     * Dispatch job ke queue (async).
     * Jika job melempar exception, akan ditangani oleh job's failed handler.
     */
    private function resolveAndDispatch(int $batchId, string $filePath, string $uploadType): void
    {
        try {
            match ($uploadType) {
                'active_financing' => ProcessFinancingMasterUploadJob::dispatch($batchId, $filePath),
                'historical_financing' => ProcessFinancingPeriodUploadJob::dispatch($batchId, $filePath),
                'collateral' => ProcessCollateralUploadJob::dispatch($batchId, $filePath),
                'financing_office' => ProcessFinancingOfficeUploadJob::dispatch($batchId, $filePath),
                'collateral_type' => ProcessCollateralTypeUploadJob::dispatch($batchId, $filePath),
                default => throw new \InvalidArgumentException("Unknown upload_type: {$uploadType}"),
            };
        } catch (Throwable $e) {
            Log::error('Upload dispatch failed', [
                'batch_id' => $batchId,
                'upload_type' => $uploadType,
                'error' => $e->getMessage(),
            ]);

            // Pastikan batch ditandai gagal
            FinancingUploadBatch::where('id', $batchId)->update([
                'status' => UploadBatchStatus::Failed,
                'error_summary' => [$e->getMessage()],
            ]);

            throw $e;
        }
    }
}
