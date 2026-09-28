<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\UploadBatchStatus;
use App\Http\Controllers\Controller;
use App\Models\FinancingUploadBatch;
use App\Traits\HasProgressTracking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * API Controller untuk progress tracking upload jobs.
 */
class UploadProgressController extends Controller
{
    use HasProgressTracking;

    /**
     * Get progress data untuk upload tertentu.
     */
    public function show(Request $request, string $uploadId): JsonResponse
    {
        $batch = null;

        // Validasi ownership jika uploadId berupa numeric batch ID
        if (is_numeric($uploadId)) {
            $batch = FinancingUploadBatch::find((int) $uploadId);
            if ($batch !== null && $batch->uploaded_by_user_id !== Auth::id() && ! Auth::user()?->hasRole('super_admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to upload progress',
                ], 403);
            }
        }

        $progress = static::getProgress($uploadId);

        // Fallback: cache belum/tidak lagi tersedia (mis. request polling tiba
        // sebelum initializeProgress) → bangun snapshot dari record batch.
        if (! $progress && $batch !== null) {
            $progress = $this->buildProgressFromBatch($batch);
        }

        if (! $progress) {
            return response()->json([
                'success' => false,
                'message' => 'Progress data not found',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $progress,
        ]);
    }

    /**
     * Bangun snapshot progress dari kolom processed_rows/total_rows pada batch
     * agar progress bar tetap bergerak walau cache progress belum terbentuk.
     *
     * @return array<string, mixed>|null
     */
    private function buildProgressFromBatch(FinancingUploadBatch $batch): ?array
    {
        if ($batch->status === UploadBatchStatus::Pending) {
            return null;
        }

        $totalRows = (int) $batch->total_rows;
        $processedRows = (int) $batch->processed_rows;
        $percentage = $totalRows > 0
            ? (int) min(100, floor(($processedRows / $totalRows) * 100))
            : 0;

        return [
            'upload_id' => (string) $batch->id,
            'percentage' => $percentage,
            'current_step' => $processedRows,
            'total_steps' => $totalRows,
            'status_title' => $batch->status === UploadBatchStatus::Processing
                ? 'Memproses data...'
                : 'Menyiapkan proses...',
            'status_text' => $totalRows > 0
                ? "{$processedRows} dari {$totalRows} baris diproses"
                : 'Menghitung total baris file...',
            'file_info' => $batch->filename,
            'speed' => 0,
            'eta' => null,
            'started_at' => $batch->created_at?->timestamp,
            'updated_at' => now()->timestamp,
        ];
    }
}
