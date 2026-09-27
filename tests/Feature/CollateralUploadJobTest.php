<?php

declare(strict_types=1);

use App\Enums\UploadBatchStatus;
use App\Jobs\ProcessCollateralUploadJob;
use App\Models\Collateral;
use App\Models\CollateralType;
use App\Models\FinancingAccount;
use App\Models\FinancingUploadBatch;
use App\Models\User;
use App\Services\UploadProcessorService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

it('dapat memproses file excel collateral secara memory-efficient dan melakukan upsert', function () {
    $user = User::factory()->create();
    $account = FinancingAccount::factory()->create([
        'account_number' => 'ACC-001',
    ]);

    $collateralType = CollateralType::create([
        'code' => 'SHM',
        'name' => 'Sertifikat Hak Milik',
        'liquidation_discount_rate' => 0.20,
        'is_active' => true,
    ]);

    // Buat spreadsheet sementara
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();

    $sheet->fromArray([
        ['account_number', 'collateral_code', 'sequence_number', 'collateral_type_code', 'description', 'appraisal_value', 'estimated_sale_value', 'appraised_at', 'is_active'],
        ['ACC-001', 'COL-001', 1, 'SHM', 'Tanah dan Bangunan', '500,000,000', '400,000,000', '2025-01-15', 1],
    ]);

    $tempPath = tempnam(sys_get_temp_dir(), 'test_col_').'.xlsx';
    $writer = new Xlsx($spreadsheet);
    $writer->save($tempPath);

    $batch = FinancingUploadBatch::create([
        'upload_type' => 'collateral',
        'filename' => basename($tempPath),
        'uploaded_by_user_id' => $user->id,
        'uploaded_at' => now(),
        'status' => UploadBatchStatus::Pending,
    ]);

    // Pakai service synchronous (dispatchSync) — mengganti mekanisme queue
    $processor = app(UploadProcessorService::class);
    $processor->process($batch->id, $tempPath, 'collateral');

    $batch->refresh();
    expect($batch->status)->toBe(UploadBatchStatus::Done)
        ->and($batch->imported_rows)->toBe(1)
        ->and(Collateral::count())->toBe(1);

    $collateral = Collateral::first();
    expect($collateral->financing_account_id)->toBe($account->id)
        ->and($collateral->collateral_code)->toBe('COL-001')
        ->and($collateral->collateral_type_id)->toBe($collateralType->id)
        ->and((float) $collateral->appraisal_value)->toBe(500000000.0)
        ->and((float) $collateral->estimated_sale_value)->toBe(400000000.0);

    @unlink($tempPath);
});

it('bersifat idempoten jika status batch sudah Done', function () {
    $user = User::factory()->create();
    $batch = FinancingUploadBatch::create([
        'upload_type' => 'collateral',
        'filename' => 'dummy.xlsx',
        'uploaded_by_user_id' => $user->id,
        'uploaded_at' => now(),
        'status' => UploadBatchStatus::Done,
    ]);

    // dispatchSync tetap aman karena job guard idempotency-nya
    ProcessCollateralUploadJob::dispatchSync($batch->id, 'dummy.xlsx');

    expect($batch->fresh()->status)->toBe(UploadBatchStatus::Done);
});
