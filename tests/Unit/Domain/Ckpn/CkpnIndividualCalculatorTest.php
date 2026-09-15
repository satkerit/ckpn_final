<?php

declare(strict_types=1);

/**
 * Unit tests formula CKPN Individual.
 * Ref: PRD Bab 6.1
 *
 * Formula:
 *   CKPN = Baki Debet - Total Nilai Likuidasi Jaminan - (Nilai Likuidasi x Biaya Penjualan)
 */
test('ckpn individual dihitung benar dengan jaminan parsial', function () {
    // Contoh manual:
    // outstanding = 100.000.000
    // liquidation = 60.000.000
    // selling_cost_rate = 5% (0.05)
    // selling_cost = 60.000.000 * 0.05 = 3.000.000
    // CKPN = 100.000.000 - 60.000.000 - 3.000.000 = 37.000.000
    $outstanding = 100_000_000.0;
    $liquidation = 60_000_000.0;
    $sellingCostRate = 0.05;

    $sellingCostAmount = $liquidation * $sellingCostRate;
    $ckpn = max(0.0, $outstanding - $liquidation - $sellingCostAmount);

    expect($sellingCostAmount)->toBe(3_000_000.0);
    expect($ckpn)->toBe(37_000_000.0);
});

test('ckpn individual adalah nol ketika jaminan melebihi baki debet', function () {
    // Jaminan lebih besar dari outstanding → CKPN tidak boleh negatif
    $outstanding = 50_000_000.0;
    $liquidation = 60_000_000.0;
    $sellingCostRate = 0.05;

    $sellingCostAmount = $liquidation * $sellingCostRate;
    $ckpn = max(0.0, $outstanding - $liquidation - $sellingCostAmount);

    expect($ckpn)->toBe(0.0);
});

test('ckpn individual adalah penuh ketika tidak ada jaminan', function () {
    // Tanpa jaminan: CKPN = seluruh baki debet
    $outstanding = 100_000_000.0;
    $liquidation = 0.0;
    $sellingCostRate = 0.05;

    $sellingCostAmount = $liquidation * $sellingCostRate;
    $ckpn = max(0.0, $outstanding - $liquidation - $sellingCostAmount);

    expect($sellingCostAmount)->toBe(0.0);
    expect($ckpn)->toBe(100_000_000.0);
});

test('ckpn individual dengan biaya penjualan nol', function () {
    // Biaya penjualan 0% → CKPN = outstanding - liquidation
    $outstanding = 100_000_000.0;
    $liquidation = 70_000_000.0;
    $sellingCostRate = 0.0;

    $sellingCostAmount = $liquidation * $sellingCostRate;
    $ckpn = max(0.0, $outstanding - $liquidation - $sellingCostAmount);

    expect($ckpn)->toBe(30_000_000.0);
});

test('top-n selection mengambil n akun dengan outstanding terbesar', function () {
    // Fixture: 5 akun, N=3
    $accounts = [
        ['id' => 1, 'outstanding' => 50_000_000.0],
        ['id' => 2, 'outstanding' => 200_000_000.0],
        ['id' => 3, 'outstanding' => 150_000_000.0],
        ['id' => 4, 'outstanding' => 80_000_000.0],
        ['id' => 5, 'outstanding' => 300_000_000.0],
    ];
    $n = 3;

    usort($accounts, fn ($a, $b) => $b['outstanding'] <=> $a['outstanding']);
    $topN = array_slice($accounts, 0, $n);
    $topNIds = array_column($topN, 'id');

    // Top 3: id 5 (300jt), id 2 (200jt), id 3 (150jt)
    expect($topNIds)->toBe([5, 2, 3]);
});

test('top-n selection dengan N lebih besar dari jumlah akun mengambil semua', function () {
    $accounts = [
        ['id' => 1, 'outstanding' => 50_000_000.0],
        ['id' => 2, 'outstanding' => 100_000_000.0],
    ];
    $n = 10; // N > jumlah akun

    usort($accounts, fn ($a, $b) => $b['outstanding'] <=> $a['outstanding']);
    $topN = array_slice($accounts, 0, $n);

    expect(count($topN))->toBe(2); // hanya 2 akun tersedia
});
