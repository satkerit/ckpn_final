<?php

declare(strict_types=1);

use App\Enums\UploadBatchStatus;
use App\Imports\FinancingPeriodUploadImport;
use App\Models\FinancingAccount;
use App\Models\FinancingUploadBatch;

/**
 * Feature test: import Excel data pembiayaan.
 * Ref: PRD Bab 15
 *
 * FinancingPeriodUploadImport mengimplementasikan OnEachRow + WithHeadingRow.
 * Test memanggil buildRowData() + flushBuffer() via reflection karena
 * construct Maatwebsite\Excel\Row membutuhkan PhpSpreadsheet worksheet object
 * yang API-nya berubah di versi 5.x.
 */

/**
 * Helper: panggil buildRowData() via reflection.
 */
function callBuildRowData(FinancingPeriodUploadImport $import, array $row): ?array
{
    $ref = new ReflectionClass($import);
    $method = $ref->getMethod('buildRowData');
    $method->setAccessible(true);

    return $method->invoke($import, $row);
}

/**
 * Helper: panggil flushBuffer() via reflection, lalu return jumlah baris yang di-flush.
 */
function callFlushBuffer(FinancingPeriodUploadImport $import): void
{
    $ref = new ReflectionClass($import);
    $method = $ref->getMethod('flushBuffer');
    $method->setAccessible(true);
    $method->invoke($import);
}

/**
 * Helper: inject rows ke buffer via reflection dan flush.
 */
function pushRowsAndFlush(FinancingPeriodUploadImport $import, array $rows): void
{
    $ref = new ReflectionClass($import);
    $buffer = $ref->getProperty('buffer');
    $buffer->setAccessible(true);

    foreach ($rows as $row) {
        $data = callBuildRowData($import, $row);
        if ($data !== null) {
            $buffer->setValue($import, array_merge($buffer->getValue($import), [$data]));
        }
    }

    callFlushBuffer($import);
}

// ---------------------------------------------------------------------------

test('buildRowData mengembalikan null jika nokontrak kosong', function () {
    $batch = FinancingUploadBatch::factory()->create(['status' => UploadBatchStatus::Pending]);
    $import = new FinancingPeriodUploadImport($batch);

    $result = callBuildRowData($import, [
        'nokontrak' => '',
        'periode' => '202612',
        'osmdlc' => '50000000',
        'colbaru' => '1',
        'stsrec' => 'A',
    ]);

    expect($result)->toBeNull();
    $this->assertDatabaseCount('financing_account_periods', 0);
});

test('buildRowData mengembalikan array data jika nokontrak valid', function () {
    $batch = FinancingUploadBatch::factory()->create(['status' => UploadBatchStatus::Pending]);
    $account = FinancingAccount::factory()->create(['account_number' => 'KTR-TEST-001']);
    $import = new FinancingPeriodUploadImport($batch);

    $result = callBuildRowData($import, [
        'nokontrak' => 'KTR-TEST-001',
        'periode' => '202612',
        'osmdlc' => '75000000',
        'colbaru' => '3',
        'tgkhari' => '90',
        'tgkmdl' => '',
        'tglwo' => '',
        'stsrec' => 'A',
        'stsacc' => '',
    ]);

    expect($result)->not->toBeNull();
    expect($result['financing_account_id'])->toBe($account->id);
    expect($result['collectibility'])->toBe(3);
    expect($result['period'])->toBe('202612');
});

test('flushBuffer menyimpan data ke financing_account_periods', function () {
    $batch = FinancingUploadBatch::factory()->create(['status' => UploadBatchStatus::Pending]);
    $account = FinancingAccount::factory()->create(['account_number' => 'KTR-TEST-002']);
    $import = new FinancingPeriodUploadImport($batch);

    pushRowsAndFlush($import, [
        [
            'nokontrak' => 'KTR-TEST-002',
            'periode' => '202612',
            'osmdlc' => '100000000',
            'colbaru' => '2',
            'tgkhari' => '30',
            'tgkmdl' => '',
            'tglwo' => '',
            'stsrec' => 'A',
            'stsacc' => '',
        ],
    ]);

    $this->assertDatabaseHas('financing_account_periods', [
        'period' => '202612',
        'collectibility' => 2,
    ]);
    expect($import->getErrors())->toBeEmpty();
});

test('flushBuffer upsert tidak duplikasi baris yang sama', function () {
    $batch = FinancingUploadBatch::factory()->create(['status' => UploadBatchStatus::Pending]);
    $account = FinancingAccount::factory()->create(['account_number' => 'KTR-TEST-003']);
    $import = new FinancingPeriodUploadImport($batch);

    $row = [
        'nokontrak' => 'KTR-TEST-003',
        'periode' => '202612',
        'osmdlc' => '100000000',
        'colbaru' => '2',
        'tgkhari' => '30',
        'tgkmdl' => '',
        'tglwo' => '',
        'stsrec' => 'A',
        'stsacc' => '',
    ];

    pushRowsAndFlush($import, [$row]);
    pushRowsAndFlush($import, [$row]); // upsert — tidak duplikasi

    // Hanya 1 record di financing_account_periods
    $this->assertDatabaseCount('financing_account_periods', 1);
});

test('parse tanggal format yyyymmdd', function () {
    $reflection = new ReflectionClass(FinancingPeriodUploadImport::class);
    $method = $reflection->getMethod('parseDate');
    $method->setAccessible(true);

    $batch = FinancingUploadBatch::factory()->create();
    $import = new FinancingPeriodUploadImport($batch);

    expect($method->invoke($import, '20200115'))->toBe('2020-01-15');
    expect($method->invoke($import, ''))->toBeNull();
    expect($method->invoke($import, null))->toBeNull();
});
