<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\FinancingUploadBatch;
use Illuminate\Support\Facades\Cache;

/**
 * Trait untuk menambahkan fitur progress tracking real-time pada upload jobs
 */
trait HasProgressTracking
{
    protected string $progressKey = '';

    protected int $totalSteps = 0;

    protected int $currentStep = 0;

    protected array $progressData = [];

    /**
     * Initialize progress tracking
     */
    protected function initializeProgress(string $uploadId, int $totalRows = 0): void
    {
        $this->progressKey = "upload_progress_{$uploadId}";
        $this->totalSteps = $totalRows;
        $this->currentStep = 0;

        $this->progressData = [
            'upload_id' => $uploadId,
            'percentage' => 0,
            'current_step' => 0,
            'total_steps' => $totalRows,
            'status_title' => 'Memulai upload...',
            'status_text' => 'Menyiapkan file untuk diproses',
            'file_info' => '',
            'speed' => 0,
            'eta' => null,
            'started_at' => now()->timestamp,
            'updated_at' => now()->timestamp,
        ];

        $this->updateProgress();
    }

    /**
     * Update progress with new data
     */
    protected function updateProgress(array $data = []): void
    {
        // Guard: initializeProgress() belum dipanggil (mis. failProgress dari base job)
        if ($this->progressKey === '') {
            return;
        }

        $this->progressData = array_merge($this->progressData, $data);
        $this->progressData['updated_at'] = now()->timestamp;

        // Calculate percentage
        if ($this->totalSteps > 0) {
            $this->progressData['percentage'] = min(100, ($this->currentStep / $this->totalSteps) * 100);
        } elseif (! isset($data['percentage'])) {
            $this->progressData['percentage'] = 0;
        }

        // Calculate speed and ETA
        $elapsed = now()->timestamp - $this->progressData['started_at'];
        if ($elapsed > 0 && $this->currentStep > 0) {
            $this->progressData['speed'] = $this->currentStep / $elapsed; // rows per second

            if ($this->progressData['speed'] > 0) {
                $remainingSteps = $this->totalSteps - $this->currentStep;
                $this->progressData['eta'] = ceil($remainingSteps / $this->progressData['speed']);
            }
        }

        // Cache progress data for frontend polling
        $this->cacheProgress(30);
    }

    /**
     * Simpan data progress ke cache dengan proteksi error.
     *
     * Kegagalan progress tracking TIDAK boleh menghentikan proses upload —
     * baris tetap diproses walau penulisan progress gagal.
     */
    private function cacheProgress(int $minutes): void
    {
        try {
            Cache::put($this->progressKey, $this->progressData, now()->addMinutes($minutes));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Increment progress step
     */
    protected function incrementProgress(int $steps = 1, array $data = []): void
    {
        $this->currentStep += $steps;
        $this->progressData['current_step'] = $this->currentStep;

        $this->updateProgress($data);
    }

    /**
     * Set progress step
     */
    protected function setProgress(int $step, array $data = []): void
    {
        $this->currentStep = $step;
        $this->progressData['current_step'] = $this->currentStep;

        $this->updateProgress($data);
    }

    /**
     * Update progress with batch information
     */
    protected function updateBatchProgress(
        int $processedRows,
        int $importedRows,
        int $skippedRows,
        int $failedRows = 0,
        string $currentFile = ''
    ): void {
        $this->setProgress($processedRows, [
            'processed_rows' => $processedRows,
            'imported_rows' => $importedRows,
            'skipped_rows' => $skippedRows,
            'failed_rows' => $failedRows,
            'status_title' => 'Memproses data...',
            'status_text' => "Diproses: {$processedRows} | Berhasil: {$importedRows} | Dilewati: {$skippedRows}".
                           ($failedRows > 0 ? " | Gagal: {$failedRows}" : ''),
            'file_info' => $currentFile,
        ]);

        // Also update the batch record if available
        if (property_exists($this, 'batchId') && $this->batchId) {
            try {
                $batch = FinancingUploadBatch::find($this->batchId);
                if ($batch) {
                    $batch->update([
                        'processed_rows' => $processedRows,
                        'imported_rows' => $importedRows,
                        'skipped_rows' => $skippedRows,
                        'failed_rows' => $failedRows,
                        'progress_log' => array_merge($batch->progress_log ?? [], [
                            'last_update' => now()->toDateTimeString(),
                            'percentage' => $this->progressData['percentage'],
                            'speed' => $this->progressData['speed'],
                            'eta' => $this->progressData['eta'],
                        ]),
                    ]);
                }
            } catch (\Throwable $e) {
                // Jangan gagalkan loop upload hanya karena update snapshot progress gagal
                report($e);
            }
        }
    }

    /**
     * Mark progress as completed
     */
    protected function completeProgress(string $message = 'Upload selesai!'): void
    {
        $this->currentStep = 0;
        $this->totalSteps = 0;

        $this->updateProgress([
            'percentage' => 100,
            'status_title' => 'Selesai!',
            'status_text' => $message,
            'completed_at' => now()->timestamp,
        ]);

        // Keep cache for a bit longer to show completion
        $this->cacheProgress(60);
    }

    /**
     * Mark progress as failed
     */
    protected function failProgress(string $error): void
    {
        $this->updateProgress([
            'status_title' => 'Upload Gagal',
            'status_text' => $error,
            'failed_at' => now()->timestamp,
            'error' => $error,
        ]);

        // Keep cache for debugging
        $this->cacheProgress(120);
    }

    /**
     * Get current progress data
     */
    public static function getProgress(string $uploadId): ?array
    {
        return Cache::get("upload_progress_{$uploadId}");
    }

    /**
     * Clear progress data
     */
    protected function clearProgress(): void
    {
        if ($this->progressKey) {
            Cache::forget($this->progressKey);
        }
    }

    /**
     * Format file size for display
     */
    protected function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 2).' '.$units[$pow];
    }
}
