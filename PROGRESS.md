## [2026-09-29] Kebijakan Agent — Konfirmasi Operasi Destruktif

**Status**: ✅ Done

**Modul**: Dokumentasi agent (AGENTS.md / CLAUDE.md)

**Ref PRD**: —

**Perubahan**:

- Added AGENTS.md Bab 13: kebijakan WAJIB konfirmasi user sebelum operasi berisiko kehilangan data (DDL/DML destruktif, artisan destruktif, snapshot CKPN, queue, file, git) + format konfirmasi & mitigasi
- Added prinsip #9 di AGENTS.md Bab 1 + larangan baru di Bab 9 yang merujuk Bab 13
- Created CLAUDE.md sebagai pointer ke AGENTS.md dengan aturan konfirmasi ditonjolkan di atas

**File utama berubah**:

- `AGENTS.md` (updated)
- `CLAUDE.md` (NEW)

---

## [2026-09-29] Phase 1 — Schema & Akad Rules

**Status**: ✅ Done

**Modul**: PD Netflow - Schema & Field Selection per Akad

**Ref PRD**: Bab 5, 7, 8; User requirement #5

**Perubahan**:

- Added columns `office_code` + `akad_code` to `ckpn_period_classifications` (migration 2026_09_29_140000)
- Created `AkadCalculationRule` model + `AkadCalculationRulesRepository` for dynamic field resolution
- Updated `OutstandingMapLoader` to use dynamic field selection (outstanding_balance vs tgkmdl per akad)
- Updated `PdNetflowDetailService` to use dynamic field selection
- Seeded default akad rules (all default to outstanding_balance; configure exceptions later)
- Added composite index on `(period, office_code, akad_code, usage_type)` for query performance

**File utama berubah**:

- `database/migrations/2026_09_29_140000_add_segmentation_index_to_ckpn_period_classifications.php` (NEW)
- `app/Models/AkadCalculationRule.php` (NEW)
- `app/Repositories/AkadCalculationRulesRepository.php` (NEW)
- `app/Domain/Ckpn/Pd/Netflow/OutstandingMapLoader.php` (updated)
- `app/Services/PdNetflowDetailService.php` (updated)
- `app/Models/CkpnPeriodClassification.php` (updated fillable)
- `database/seeders/AkadCalculationRuleSeeder.php` (NEW)

**Test**: ✅ PdNetflow tests pass (14 passed)

**Next step**:

- Phase 2: Update ClassifyPeriodDataJob to populate office_code + akad_code (already done)
- Phase 3: Update UI filters (CkpnClassificationIndex, PdNetflowPivotIndex, PdNetflowResultIndex) — ✅ Done
- Phase 4: Update PD Migration & LGD calculators for dynamic field selection — ✅ Done

---

## [2026-09-29] Phase 4 — PD Migration & LGD Dynamic Field Selection

**Status**: ✅ Done

**Modul**: PD Migration Calculator, LGD Expected Recoveries, LGD Collateral Shortfall

**Ref PRD**: Bab 8, 9, 10; Akad Segmentation (Level 2)

**Perubahan**:

- Injected `AkadCalculationRulesRepository` into `MigrationMatrixBuilder`, `LgdExpectedRecoveriesCalculator`, `LgdCollateralShortfallCalculator`
- Updated `gradeOutstandingFromPeriods()` (MigrationMatrixBuilder) to resolve field dynamically before building subquery
- Updated `aggregateWriteoffByYear()` + `aggregateRecoveryByYear()` (LgdExpectedRecoveriesCalculator) to use dynamic field in selectRaw
- Updated `calculatePerAccount()` (LgdCollateralShortfallCalculator) to read from `$upload->{$field}` instead of hardcoded `outstanding_balance`
- Fixed test dependencies: replaced Mockery (cannot mock final classes) with `app(AkadCalculationRulesRepository::class)` in unit/feature tests
- Updated 3 test files: `PdMigrationCalculatorTest`, `LgdExpectedRecoveriesTest`, `LgdCollateralShortfallTest`

**File utama berubah**:

- `app/Domain/Ckpn/Pd/Migration/MigrationMatrixBuilder.php`
- `app/Domain/Ckpn/Lgd/ExpectedRecoveries/LgdExpectedRecoveriesCalculator.php`
- `app/Domain/Ckpn/Lgd/CollateralShortfall/LgdCollateralShortfallCalculator.php`
- `tests/Unit/Domain/PdMigration/PdMigrationCalculatorTest.php`
- `tests/Unit/Domain/Lgd/LgdExpectedRecoveriesTest.php`
- `tests/Unit/Domain/Lgd/LgdCollateralShortfallTest.php`
- `tests/Feature/PdMigrationFeatureTest.php`

**Test**: ✅ All 18 tests pass (1 skipped awaiting seeding infrastructure)

**Next step**:

- Verify job processors (ClassifyPeriodDataJob, calculation jobs) work with segmented calculations
- Test end-to-end flow with different akad codes
- Confirm outstanding field selection correct in production scenarios

---

## [2026-09-29] Phase 5 — Bug Fix & Test Validation

**Status**: ✅ Done

**Modul**: LGD Expected Recoveries, LGD CS Job, Dynamic Field Selection

**Ref PRD**: Bab 9, 10

**Perubahan**:

- Fixed missing closing brace in `aggregateRecoveryByYear()` (syntax error)
- Fixed `LgdCsCalculationJob` missing dependency injection (use `app()` instead of `new`)
- Fixed `aggregateWriteoffByYear()` to filter only writeoff BEFORE calculation period (`where('fap_wo.period', '<', $calculationPeriod)`)
- Fixed `aggregateRecoveryByYear()` to join writeoff period data correctly (leftJoin ke fap_wo by writeoff_date period)
- Fixed recovery calculation: separated `expected_recovery_rate` (recovery/writeoff) from `lgd_rate` (1 - recovery/writeoff)
- Updated test expectations to match correct math (130M recovery, 0.72 recovery rate, 0.28 LGD rate)
- Added `writeoff_date` to test data recovery period rows

**File berubah**:

- `app/Domain/Ckpn/Lgd/ExpectedRecoveries/LgdExpectedRecoveriesCalculator.php`
- `app/Jobs/LgdCsCalculationJob.php`
- `tests/Feature/LgdExpectedRecoveriesDynamicFieldTest.php`

**Test**: ✅ All 87 tests pass, 1 skipped (265 assertions)

**Next step**:

- Verify job processors handle segmented calculations end-to-end
- Test with different office_code + akad_code combinations
- Ready for production integration

---

## [2026-09-29] Bugfix — PropertyNotFoundException di PdNetflowResultIndex

**Status**: ✅ Done

**Modul**: PD Netflow - Halaman Hasil (Livewire)

**Ref PRD**: Bab 7

**Perubahan**:

- Fixed `PropertyNotFoundException [$filterPeriode]`: deklarasi properti tergantikan oleh `$filterOfficeCode`/`$filterAkadCode` saat commit segmentasi — dikembalikan dengan `#[Url(as: 'periode')]`
- Fixed QueryException `Unknown column 'office_code'` di render(): dropdown kantor/akad kini query `financing_accounts` via `whereHas('accountPeriods')` (kolom office_code/akad_code memang berada di financing_accounts, konsisten dengan ClassifyPeriodDataJob)
- Verified render OK via tinker + 14 PdNetflow tests pass

**File utama berubah**:

- `app/Livewire/PdNetflow/PdNetflowResultIndex.php`

---

## [2026-09-29] Phase 6 — UI Column Name Fix & Parameter Save Bug

**Status**: ✅ Done

**Modul**: CkpnClassificationIndex Livewire Component, CalculationParameterIndex

**Ref PRD**: Bab 6.1, 15

**Perubahan**:

- Fixed FinancingOffice query in CkpnClassificationIndex: select `code as office_code`, `name as office_name` (model columns vs query expectations)
- Fixed `simpanColumnConfig()` bug: edit mode malah insert baru daripada update (gunakan `findOrFail($id)->update()` untuk edit, updateOrCreate hanya untuk tambah baru dengan duplicate prevention)

**File berubah**:

- `app/Livewire/Ckpn/CkpnClassificationIndex.php` (line 245)
- `app/Livewire/Ckpn/CalculationParameterIndex.php` (line 196-239)

**Test**: ✅ All 87 tests pass, 1 skipped (265 assertions)

**Next step**:

- Ready for UI testing with office_code filtering & column config edit
- Integration testing with job processors

---

## [2026-09-29] Audit Component Livewire Pasca Commit Segmentasi (6413e93)

**Status**: ✅ Done

**Modul**: PD Netflow Pivot, CKPN Classification

**Ref PRD**: Bab 5, 7

**Perubahan**:

- `PdNetflowPivotIndex`: fixed query dropdown kantor/akad yang pluck office_code/akad_code dari financing_account_periods (kolom tidak ada → 500) — kini via `FinancingAccount::whereHas('accountPeriods')`
- `CkpnClassificationIndex` blade: fixed dropdown kantor yang merender objek FinancingOffice sebagai value option (kini `$office->office_code` + nama), fixed `<th>` rusak (Nama Debitur), hapus modal dead-code `hitungCkpnIndividual` (method tidak ada di component)
- Render-verified 10 component yang tersentuh commit: semua OK

**File utama berubah**:

- `app/Livewire/PdNetflow/PdNetflowPivotIndex.php`
- `resources/views/livewire/ckpn/ckpn-classification-index.blade.php`

**Test**: ✅ 14 PdNetflow tests pass; pint clean

---

## [2026-09-29] Phase 8 — Named Parameter Fix (Correct camelCase)

**Status**: ✅ Done

**Modul**: PdNetflowCalculationJob, LgdErCalculationJob, PdMigrationCalculationJob

**Ref PRD**: Bab 7, 8, 9

**Perubahan**:

- Fixed `resolveValue()` calls: parameter names camelCase yang benar (`officeCode`, `usageType`, `akadCode`) — sebelumnya salah jadi snake_case

**File berubah**:

- `app/Jobs/PdNetflowCalculationJob.php` (line 105-107, 114-116)
- `app/Jobs/LgdErCalculationJob.php` (line 115-117, 124-126)
- `app/Jobs/PdMigrationCalculationJob.php` (line 100-102, 113-116)

**Test**: ✅ All 87 tests pass, 1 skipped (265 assertions)

**Next step**:

- Ready for job execution integration testing dengan queue worker
- Test perhitungan PD/LGD end-to-end dengan data real

---

## Open Item

**TODO**: Confirm with user which akad codes require `tgkmdl` (tunggakan pokok) vs `outstanding_balance`. Currently: akad 01 → outstanding_balance, akad 05 → tgkmdl, others → outstanding_balance.
