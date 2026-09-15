<?php

declare(strict_types=1);

/**
 * Unit tests formula LGD Final per segmen (tanpa DB).
 * Formula: LGD = 1 - (Recover / OS)
 *   Recover = er_recovery + cs_net_value
 *   OS      = er_writeoff + cs_outstanding
 * Ref: PRD Bab 9, 10, 11
 */

// ── Formula murni (tanpa DB) ─────────────────────────────────────────────────

test('lgd final dihitung benar dengan nilai normal', function (): void {
    // ER: writeoff=500jt, recovery=200jt
    // CS: outstanding=300jt, net_value=150jt
    // Recover = 200jt + 150jt = 350jt
    // OS      = 500jt + 300jt = 800jt
    // LGD     = 1 - (350 / 800) = 0.5625
    $totalRecover = 200_000_000.0 + 150_000_000.0;
    $totalOs = 500_000_000.0 + 300_000_000.0;
    $lgd = 1.0 - ($totalRecover / $totalOs);

    expect($lgd)->toBe(0.5625);
});

test('lgd final adalah nol ketika recover sama dengan OS', function (): void {
    $totalOs = 400_000_000.0;
    $totalRecover = 400_000_000.0;
    $lgd = 1.0 - ($totalRecover / $totalOs);

    expect($lgd)->toBe(0.0);
});

test('lgd final di-floor ke 0 ketika recover melebihi OS', function (): void {
    // recover > os → LGD negatif, max(0.0, ...) → 0
    $totalRecover = 900_000_000.0;
    $totalOs = 700_000_000.0;
    $lgd = max(0.0, 1.0 - ($totalRecover / $totalOs));

    expect($lgd)->toBe(0.0);
});

test('lgd final adalah 1 ketika recovery adalah nol', function (): void {
    $totalRecover = 0.0;
    $totalOs = 800_000_000.0;
    $lgd = max(0.0, 1.0 - ($totalRecover / $totalOs));

    expect($lgd)->toBe(1.0);
});

test('lgd final adalah nol ketika OS adalah nol', function (): void {
    $totalOs = 0.0;
    $totalRecover = 0.0;
    $lgd = $totalOs > 0.0 ? max(0.0, 1.0 - ($totalRecover / $totalOs)) : 0.0;

    expect($lgd)->toBe(0.0);
});
