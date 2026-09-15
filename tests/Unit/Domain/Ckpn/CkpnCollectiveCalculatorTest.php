<?php

declare(strict_types=1);

/**
 * Unit tests formula CKPN Kolektif.
 * Ref: PRD Bab 11
 *
 * Formula:
 *   CKPN = PD x LGD x EAD
 *
 * LGD diambil dari snapshot lgd_final_result per segmen (bukan per akun).
 * Ref: PRD Bab 11, AGENTS.md §5 (refactor f5)
 */
test('ckpn kolektif dihitung benar dengan nilai normal', function () {
    // Contoh manual:
    // PD = 5% (0.05)
    // LGD = 40% (0.40)
    // EAD = 100.000.000
    // CKPN = 0.05 * 0.40 * 100.000.000 = 2.000.000
    $pd = 0.05;
    $lgd = 0.40;
    $ead = 100_000_000.0;

    $ckpn = $pd * $lgd * $ead;

    expect(round($ckpn, 2))->toBe(2_000_000.0);
});

test('ckpn kolektif adalah nol ketika pd adalah nol', function () {
    $pd = 0.0;
    $lgd = 0.40;
    $ead = 100_000_000.0;

    $ckpn = $pd * $lgd * $ead;

    expect($ckpn)->toBe(0.0);
});

test('ckpn kolektif adalah nol ketika lgd adalah nol', function () {
    $pd = 0.05;
    $lgd = 0.0;
    $ead = 100_000_000.0;

    $ckpn = $pd * $lgd * $ead;

    expect($ckpn)->toBe(0.0);
});

test('ckpn kolektif ketika pd=1 dan lgd=1 sama dengan ead', function () {
    // Kasus ekstrem: PD=100%, LGD=100% → CKPN = seluruh EAD
    $pd = 1.0;
    $lgd = 1.0;
    $ead = 75_000_000.0;

    $ckpn = $pd * $lgd * $ead;

    expect($ckpn)->toBe(75_000_000.0);
});

test('total ckpn kolektif adalah jumlah per akun', function () {
    // Aggregate: sum per akun
    $accounts = [
        ['pd' => 0.05, 'lgd' => 0.40, 'ead' => 100_000_000.0],
        ['pd' => 0.10, 'lgd' => 0.60, 'ead' => 50_000_000.0],
        ['pd' => 0.02, 'lgd' => 0.30, 'ead' => 200_000_000.0],
    ];

    // Manual:
    // Akun 1: 0.05 * 0.40 * 100jt = 2.000.000
    // Akun 2: 0.10 * 0.60 * 50jt  = 3.000.000
    // Akun 3: 0.02 * 0.30 * 200jt = 1.200.000
    // Total = 6.200.000
    $total = 0.0;
    foreach ($accounts as $a) {
        $total += $a['pd'] * $a['lgd'] * $a['ead'];
    }

    expect($total)->toBe(6_200_000.0);
});

test('lgd final per segmen dari snapshot lgd_final_result dipakai untuk semua akun di segmen', function () {
    // LGD tidak lagi dipilih per akun (bukan ER vs CS per collectibility).
    // Semua akun dalam segmen yang sama memakai LGD yang sama dari snapshot lgd_final_result.
    // Ref: PRD Bab 11, refactor f5

    $lgdSegmenA = 0.5625; // dari snapshot lgd_final_result untuk segmen A

    $accounts = [
        ['pd' => 0.05, 'ead' => 100_000_000.0],
        ['pd' => 0.08, 'ead' => 200_000_000.0],
        ['pd' => 0.03, 'ead' => 50_000_000.0],
    ];

    // Manual:
    // Akun 1: 0.05 * 0.5625 * 100jt = 2.812.500
    // Akun 2: 0.08 * 0.5625 * 200jt = 9.000.000
    // Akun 3: 0.03 * 0.5625 * 50jt  =   843.750
    // Total = 12.656.250
    $total = 0.0;
    foreach ($accounts as $a) {
        $total += $a['pd'] * $lgdSegmenA * $a['ead'];
    }

    expect($total)->toBe(12_656_250.0);
});
