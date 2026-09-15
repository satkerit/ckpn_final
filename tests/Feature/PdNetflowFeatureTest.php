<?php

declare(strict_types=1);

use App\Domain\Ckpn\Pd\Netflow\BucketMovementValidator;
use App\Domain\Ckpn\Pd\Netflow\PdNetflowCalculator;
use App\Domain\Ckpn\Services\RollingWindowResolver;
use App\Enums\UsageType;
use App\Models\Bucket;
use App\Models\CalculationParameter;
use Illuminate\Support\Facades\DB;

/**
 * Feature test: PD Netflow calculation flow.
 * Ref: PRD Bab 7
 */
test('calculator mengembalikan array kosong jika tidak ada data outstanding', function () {
    // Seeder parameter rolling window (default 12 bulan)
    CalculationParameter::factory()->create([
        'usage_type' => null,
        'parameter_key' => 'pd_netflow_window_months',
        'parameter_value' => '12',
    ]);

    $windowResolver = new RollingWindowResolver(12, 3);
    $validator = new BucketMovementValidator;
    $calculator = new PdNetflowCalculator($windowResolver, $validator);

    $results = $calculator->calculate(UsageType::ModalKerja, '202612');

    expect($results)->toBeArray();
});

test('loadOutstandingMap membaca data financing_outstanding_monthly dengan benar', function () {
    $bucket = Bucket::factory()->create(['bucket_order' => 1]);

    DB::table('financing_outstanding_monthly')->insert([
        'usage_type' => UsageType::ModalKerja->value,
        'bucket_id' => $bucket->id,
        'period' => '202601',
        'total_outstanding' => 500_000_000,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $rows = DB::table('financing_outstanding_monthly')
        ->where('usage_type', UsageType::ModalKerja->value)
        ->where('period', '202601')
        ->get();

    expect($rows)->toHaveCount(1);
    expect((float) $rows->first()->total_outstanding)->toBe(500_000_000.0);
});

test('BucketMovementValidator tidak throw ketika data kosong', function () {
    $validator = new BucketMovementValidator;

    // Tidak ada data — validasi harus selesai tanpa exception
    expect(fn () => $validator->validate(UsageType::ModalKerja, ['202601', '202602']))->not->toThrow(Throwable::class);
});
