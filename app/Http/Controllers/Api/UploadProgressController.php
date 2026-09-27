<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

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
}
