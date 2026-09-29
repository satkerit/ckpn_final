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

## [2026-09-29] Phase 20 — Data Quality Validation Dashboard

**Status**: ✅ Done

**Modul**: Validation service, Filament dashboard page

**Ref PRD**: Bab 7.3 (data quality checks)

**Summary**: Created data quality validation dashboard to detect anomalies in LGD-ER & LGD-CS snapshots. Checks for out-of-bounds rates, zero data, calculation mismatches, fallback flags.

**Changes**:

1. **DataQualityValidationService** (`app/Domain/Ckpn/Services/`)
   - `validateLgdEr()` — 5 checks:
     * LGD rate ∈ [0, 1]
     * Recovery rate ∈ [0, 1]
     * LGD + Recovery ≈ 1.0
     * Zero/negative recovery handling
     * All-account fallback flag
   - `validateLgdCs()` — 5 checks:
     * LGD rate ∈ [0, 1]
     * Account count > 0
     * Shortfall ≥ 0, ≤ outstanding
     * Collateral value ≥ 0
     * LGD = Shortfall / Outstanding consistency
   - `getPeriodValidationSummary()` — aggregate anomalies for period/usage_type, count by severity (critical/warning/info)

2. **DataQualityValidationPage** (`app/Filament/Pages/`)
   - Form: period selector, usage_type selector (live update)
   - Shows 4 cards: total snapshots, total anomalies, critical count, warning count
   - Lists all anomalies by severity with type badge (LGD-ER/LGD-CS), office_code, message
   - Green banner if no anomalies

3. **View** (`resources/views/filament/pages/`)
   - Tailwind grid layout (4 columns for metrics)
   - Color-coded severity: red=critical, orange=warning, blue=info
   - Anomaly list with badge, office code, detailed message

**Test Results**:
- ✅ All 97 tests pass (1 skipped)
- ✅ Zero regressions

**Files Created**:
- `app/Domain/Ckpn/Services/DataQualityValidationService.php` (NEW)
- `app/Filament/Pages/DataQualityValidationPage.php` (NEW)
- `resources/views/filament/pages/data-quality-validation-page.blade.php` (NEW)

**UI Workflow** (Admin/Analyst):
1. Navigate to "Data Quality" in Filament sidebar
2. Select period (YYYYMM) and usage type
3. Form auto-triggers validation
4. See summary: X snapshots, Y anomalies, Z critical, W warnings
5. If anomalies: view detailed list by type, office, severity
6. If clean: green "✅ No Anomalies Detected" banner

**Validation Rules**:
- **Critical** (must fix): LGD/recovery out of bounds, negative amounts, calculation mismatch
- **Warning** (investigate): zero accounts/writeoff, shortfall > outstanding, LGD+recovery ≠ 1.0
- **Info** (notice): all-account fallback, zero data edge cases

**Next steps**:

1. Create CKPN Individual calculation (PRD Bab 6)
2. Create CKPN Collective calculation (PRD Bab 11)
3. Add PD Netflow & PD Migration resource views (readonly)
4. Add approval workflow for snapshots (mark as Approved after review)

---

## [2026-09-29] Phase 19 — Export Snapshot Results to Excel

**Status**: ✅ Done

**Modul**: Excel export, Filament bulk actions

**Ref PRD**: Bab 5, 9, 10

**Summary**: Added export-to-Excel functionality for LGD-ER & LGD-CS snapshot results. Users can bulk export filtered/all results via button in resource table.

**Changes**:

1. **ExportSnapshotAction** (`app/Filament/Actions/`)
   - `makeForLgdEr()` / `makeForLgdCs()` — factory methods for bulk export button
   - Queries all results (filtered by current filters in table)
   - Formats data: period, usage_type label, office_code, rates as percentages, amounts formatted with thousands separator
   - Downloads as `lgd_expected_recoveries_YYYYmmdd_HHmmss.xlsx` / `lgd_collateral_shortfall_YYYYmmdd_HHmmss.xlsx`

2. **LgdErExport & LgdCsExport** (`app/Exports/`)
   - Implements FromArray, WithHeadings, WithStyles (Maatwebsite/Excel)
   - Headers with blue background, white text
   - Data rows with proper formatting (2 decimal places for amounts, 8 decimals for rates)

3. **Resource Updates**
   - LgdExpectedRecoveriesResultResource: added bulk action → Export button
   - LgdCollateralShortfallResultResource: added bulk action → Export button

**Test Results**:
- ✅ All 97 tests pass (1 skipped)
- ✅ Zero regressions

**Files Created**:
- `app/Filament/Actions/ExportSnapshotAction.php` (NEW)
- `app/Exports/LgdErExport.php` (NEW)
- `app/Exports/LgdCsExport.php` (NEW)

**Files Updated**:
- `app/Filament/Resources/LgdExpectedRecoveriesResultResource.php` (added bulk export action)
- `app/Filament/Resources/LgdCollateralShortfallResultResource.php` (added bulk export action)

**UI Workflow** (Admin):
1. Navigate to "LGD Expected Recoveries" or "LGD Collateral Shortfall"
2. Apply filters (period, usage_type, office_code) if needed
3. Select rows (or leave unselected for all)
4. Click "Export to Excel" bulk action
5. Download starts: `lgd_expected_recoveries_20260929_142829.xlsx`
6. Open in Excel — view formatted results with headers

**Export Format**:
- Excel workbook (.xlsx)
- Headers: bold, blue bg, white text, centered
- Data rows: formatted amounts (thousand separator), rates (8 decimals)
- Columns: period, usage_type, office_code, metrics (LGD rate, recovery rate, amounts, etc.), job status, created_at

**Next steps**:

1. Create data quality validation dashboard (anomaly detection per job)
2. Create CKPN Individual calculation (PRD Bab 6)
3. Create CKPN Collective calculation (PRD Bab 11)
4. Add PD Netflow & PD Migration resource views (readonly)

---

## [2026-09-29] Phase 18 — Batch Calculation Trigger

**Status**: ✅ Done

**Modul**: Batch job dispatch, Filament page

**Ref PRD**: Bab 13.2

**Summary**: Created batch action to dispatch multiple calculation methods (PD Netflow, PD Migration, LGD-ER, LGD-CS) paralel for single period/usage_type. Jobs run async via queue.

**Changes**:

1. **BatchCalculationAction** (`app/Filament/Actions/`)
   - Form: period, usage_type, methods (checkbox: PD Netflow, PD Migration, LGD-ER, LGD-CS), segment_dimensions
   - Dispatch all selected methods to queue — each creates run_log + job
   - Notification: X jobs dispatched, monitor at Job Monitor

2. **CalculationBatchPage** (`app/Filament/Pages/`)
   - Page with BatchCalculationAction in header
   - Shows info: parallel execution, async via queue, monitor progress in Job Monitor

3. **View** (`resources/views/filament/pages/`)
   - Blade template with form + info box

**Pattern**: User selects period/type/methods → click action → all jobs dispatch async → notification sent → user goes to Job Monitor to track progress.

**Test Results**:
- ✅ All 97 tests pass (1 skipped)
- ✅ Zero regressions

**Files Created**:
- `app/Filament/Actions/BatchCalculationAction.php` (NEW)
- `app/Filament/Pages/CalculationBatchPage.php` (NEW)
- `resources/views/filament/pages/calculation-batch-page.blade.php` (NEW)

**UI Workflow** (Admin):
1. Navigate to "Batch Calculation" in Filament sidebar
2. Select period (YYYYMM), usage type
3. Check which methods to run (default: all)
4. Optional: select segmentation dimensions
5. Click "Jalankan Batch Perhitungan"
6. Notification shows N jobs dispatched
7. Go to "Job Monitor" to track execution

**Parallel Execution**:
- All selected methods run in parallel via queue worker
- Each method = separate run_log + job
- No blocking — user continues immediately after dispatch

**Next steps**:

1. Export snapshot results to Excel/CSV format
2. Add data quality validation dashboard (anomaly detection)
3. Create CKPN Individual calculation (PRD Bab 6)
4. Create CKPN Collective calculation (PRD Bab 11)

---

## [2026-09-29] Phase 17 — Snapshot Preview UI (LGD Results)

**Status**: ✅ Done

**Modul**: Filament Resources for Snapshot Results

**Ref PRD**: Bab 9, 10

**Summary**: Created read-only Filament Resources to view LGD-ER & LGD-CS snapshot results. Admin can filter, sort, inspect details per period/segment.

**Changes**:

1. **LgdExpectedRecoveriesResultResource** (`app/Filament/Resources/`)
   - Table: calculation_period, usage_type (badge), office_code, lgd_rate, recovery_rate, window_years, job status
   - View: full details (parameters, results, data range, notes, error trace)
   - Filters: period, usage_type, office_code (has/global)
   - Sort: created_at DESC (newest first)

2. **LgdCollateralShortfallResultResource** (`app/Filament/Resources/`)
   - Table: calculation_period, usage_type, office_code, account_count, lgd_rate, total_shortfall, job status
   - View: full details (results, collateral value, notes, error trace)
   - Filters: period, usage_type

3. **Pages** (List & View per resource)
   - ListLgdExpectedRecoveriesResults, ViewLgdExpectedRecoveriesResults
   - ListLgdCollateralShortfallResults, ViewLgdCollateralShortfallResults

**Pattern**: Read-only resources (no create/edit/delete). Linked to CalculationRunLog for job status visibility.

**Test Results**:
- ✅ All 97 tests pass (1 skipped)
- ✅ Zero regressions

**Files Created**:
- `app/Filament/Resources/LgdExpectedRecoveriesResultResource.php` (NEW)
- `app/Filament/Resources/LgdExpectedRecoveriesResultResource/Pages/ListLgdExpectedRecoveriesResults.php` (NEW)
- `app/Filament/Resources/LgdExpectedRecoveriesResultResource/Pages/ViewLgdExpectedRecoveriesResults.php` (NEW)
- `app/Filament/Resources/LgdCollateralShortfallResultResource.php` (NEW)
- `app/Filament/Resources/LgdCollateralShortfallResultResource/Pages/ListLgdCollateralShortfallResults.php` (NEW)
- `app/Filament/Resources/LgdCollateralShortfallResultResource/Pages/ViewLgdCollateralShortfallResults.php` (NEW)

**UI Workflow** (Admin):
1. Navigate to "LGD Expected Recoveries" or "LGD Collateral Shortfall" in sidebar
2. Filter by period, usage type, office code
3. View list sorted by newest first
4. Click row → inspect full result details (parameters, calculations, notes)
5. Check job status & error messages (if any)

**Next steps**:

1. Create aggregate dashboard (compare LGD-ER vs LGD-CS results side-by-side)
2. Add batch job trigger (run all methods for single period in parallel)
3. Export snapshot to Excel/CSV
4. Add data quality validation dashboard (anomaly detection per job)

---

## [2026-09-29] Phase 16 — E2E Validation: Job Dispatch → Run Log → Snapshots

**Status**: ✅ Done

**Modul**: E2E Test Coverage, Job Integration

**Ref PRD**: Bab 5, 9, 10

**Summary**: Created E2E feature tests validating complete flow: dispatch action → job executes → run_log updates → snapshots written.

**Changes**:

1. **LgdCalculationE2eTest** (`tests/Feature/`)
   - Test 4 scenarios: LGD-ER dispatch, LGD-CS dispatch, idempotency (skip if Completed), failure handling (update error_message)
   - Verify run_log transitions: Pending → Processing → Completed (or Failed)
   - Verify snapshot created with correct calculation_period, usage_type, lgd_rate bounds [0,1]
   - Use `dispatchSync()` for synchronous execution in tests

2. **Job Fixes** (LgdErCalculationJob)
   - Use `app()` container for dependency injection (calculator needs AkadCalculationRulesRepository)
   - Remove invalid `setWindowYears()` / `setUseAllAccount()` calls — window_years already set via constructor

**Test Results**:
- ✅ All 97 tests pass (1 skipped)
- ✅ Zero regressions
- ✅ E2E flow validated end-to-end

**Files Created**:
- `tests/Feature/LgdCalculationE2eTest.php` (NEW)

**Files Updated**:
- `app/Jobs/LgdErCalculationJob.php` (dependency injection fix)

**Next steps**:

1. Add snapshot preview/aggregation UI (view calculated results per period/segment)
2. Add batch job trigger (run all methods for single period in parallel)
3. Export snapshot results to Excel/CSV
4. Add data quality validation dashboard (anomaly detection per job)

---

## [2026-09-29] Phase 14 — Filament Actions for Job Dispatching

**Status**: ✅ Done (fixed)

**Modul**: Filament Actions, CalculationSegmentationConfigResource

**Ref PRD**: Bab 5, 7, 8, 9, 10

**Summary**: Created 3 Filament Action classes to dispatch calculation jobs with user-selected dimensions via admin UI. Actions integrated into CalculationSegmentationConfigResource as header buttons. Fixed schema mismatch (model uses `period`, `run_type`, `notes` not `calculation_period`, `method_key`, `description`).

**Changes**:

1. **DispatchPdNetflowCalculationAction** (`app/Filament/Actions/`)
   - Form: select usage_type, calculation_period, segment_dimensions
   - Creates CalculationRunLog with period, run_type=RunType::PdNetflow, notes
   - Dispatches PdNetflowCalculationJob with dimensions

2. **DispatchPdMigrationCalculationAction** (`app/Filament/Actions/`)
   - Same pattern, run_type=RunType::PdMigration

3. **DispatchLgdCalculationAction** (`app/Filament/Actions/`)
   - Radio to select: LGD ER, LGD CS, or both
   - run_type=RunType::LgdExpectedRecoveries or RunType::LgdCollateralShortfall
   - Dispatches LgdErCalculationJob and/or LgdCsCalculationJob

4. **CalculationSegmentationConfigResource** (updated)
   - Added `->headerActions([...])` with 3 dispatch buttons
   - Admin can configure & trigger calculations

**Fix Applied**:
- Changed from `method_key` → `run_type` (Enum)
- Changed from `calculation_period` → `period`
- Changed from `description` → `notes`
- All Actions now match CalculationRunLog schema exactly

**Test Results**:
- ✅ All 93 tests pass (1 skipped)
- ✅ Zero regressions

**Files Created/Modified**:
- `app/Filament/Actions/DispatchPdNetflowCalculationAction.php` (NEW, fixed)
- `app/Filament/Actions/DispatchPdMigrationCalculationAction.php` (NEW, fixed)
- `app/Filament/Actions/DispatchLgdCalculationAction.php` (NEW, fixed)
- `app/Filament/Resources/CalculationSegmentationConfigResource.php` (updated)

**Workflow** (Admin):
1. Navigate to "Calculation Segmentation Configs" in Filament
2. Configure dimensions per method (checkboxes: office_code, akad_code, usage_type)
3. Click "Jalankan Perhitungan PD Netflow" / "PD Migration" / "LGD" button
4. Fill form: usage_type, period, optionally override dimensions
5. Submit → CalculationRunLog created, Job dispatched to queue, notification shown
6. Queue worker processes, writes segmented snapshots

**Backward Compatibility**: 
- Legacy dispatch unchanged (segmentDimensions default empty)
- Jobs can be queued programmatically or via UI

**Next steps**:

1. Create CalculationRunLog Filament Resource to view job status/history
2. Add snapshot preview per segment in admin UI
3. E2E validation: test full workflow (UI → job → snapshot)

---

## [2026-09-29] Phase 13 — Update All Job Classes for Dynamic Segmentation

**Status**: ✅ Done

**Modul**: PdMigrationCalculationJob, LgdErCalculationJob, LgdCsCalculationJob

**Ref PRD**: Bab 7, 8, 9, 10

**Summary**: All remaining Job classes updated to accept `segmentDimensions` parameter. Each job now branches between single calculate() and calculateDynamic() paths based on whether dimensions provided.

**Changes**:

1. **PdMigrationCalculationJob** (`app/Jobs/`)
   - Constructor added `array $segmentDimensions = []` parameter
   - handle() branches: if dimensions → calculateDynamic() else → legacy single calculate()
   - Added `writeSegmentResult()` helper to write each segment's results (PD rates + migration matrix)

2. **LgdErCalculationJob** (`app/Jobs/`)
   - Constructor added `array $segmentDimensions = []` parameter
   - handle() branches similarly; calculateDynamic() dispatches per segment
   - Added `writeSegmentResult()` helper to write each segment's LGD ER snapshot

3. **LgdCsCalculationJob** (`app/Jobs/`)
   - Constructor added `array $segmentDimensions = []` parameter
   - handle() branches; calculateDynamic() dispatches per segment
   - Added `writeSegmentResult()` helper to write per-account + aggregate snapshots per segment

**Pattern**: All jobs follow identical branching:
```php
if (!empty($this->segmentDimensions)) {
    $dynamicResult = $calculator->calculateDynamic(..., $this->segmentDimensions);
    foreach ($dynamicResult['segment_results'] as $segmentData) {
        $this->writeSegmentResult(..., $segmentData['segment']);
    }
    return;  // Skip legacy path
}
// Legacy single calculate() path
```

**Test Results**:
- ✅ All 93 tests pass (1 skipped)
- ✅ Zero regressions
- ✅ Dynamic segmentation tests all pass (9 tests)

**Files Modified**:
- `app/Jobs/PdMigrationCalculationJob.php`
- `app/Jobs/LgdErCalculationJob.php`
- `app/Jobs/LgdCsCalculationJob.php`

**Backward Compatibility**: 
- Legacy API unchanged (segmentDimensions default empty)
- Existing jobs dispatched without dimensions continue to work via single calculate() path
- No DB migrations needed

**Next steps**:

1. Create Filament Actions/Buttons to dispatch jobs with dimensions from UI
2. E2E test: configure dimensions → dispatch job → verify segmented snapshots
3. Verify snapshot output structure matches expectations per segment

---

## [2026-09-29] Phase 12 — Apply Dynamic Segmentation to All Calculators

**Status**: ✅ Done

**Modul**: PdMigrationCalculator, LgdExpectedRecoveriesCalculator, LgdCollateralShortfallCalculator

**Ref PRD**: Bab 5, Bab 8, Bab 9, Bab 10

**Summary**: Dynamic segmentation pattern applied to remaining 3 calculators (PD Migration, LGD ER, LGD CS). Each now supports `calculateDynamic()` method.

**Changes**:

1. **PdMigrationCalculator** (`app/Domain/Ckpn/Pd/Migration/`)
   - Added `calculateDynamic()` → loops over segment combinations
   - Added `extractSegmentDataFromMatrix()` → extract office_code, akad_code from financing_outstanding_quarterly

2. **LgdExpectedRecoveriesCalculator** (`app/Domain/Ckpn/Lgd/ExpectedRecoveries/`)
   - Added `calculateDynamic()` → loops over segment combinations
   - Added `extractSegmentDataFromWriteoff()` → extract from financing_accounts with writeoff records
   - Added `getWindowStartYear()` → helper untuk window year calculation

3. **LgdCollateralShortfallCalculator** (`app/Domain/Ckpn/Lgd/CollateralShortfall/`)
   - Added `calculateDynamic()` → loops over segment combinations
   - Added `extractSegmentDataFromCollateral()` → extract from financing_accounts with collateral

**Pattern**: All calculators follow same interface:
```php
public function calculateDynamic(
    UsageType $usageType,
    string $calculationPeriod,
    array $segmentDimensions = [],
): array {
    // Return: ['dimensions' => [...], 'segment_results' => [...], 'total_segments' => N]
}
```

**Test Results**:
- ✅ All 93 tests pass (1 skipped)
- ✅ Zero regressions
- ✅ No new test failures

**Files Modified**:
- `app/Domain/Ckpn/Pd/Migration/PdMigrationCalculator.php`
- `app/Domain/Ckpn/Lgd/ExpectedRecoveries/LgdExpectedRecoveriesCalculator.php`
- `app/Domain/Ckpn/Lgd/CollateralShortfall/LgdCollateralShortfallCalculator.php`

**Next steps**:

1. Update corresponding Job classes (PdMigrationCalculationJob, LgdErCalculationJob, LgdCsCalculationJob) to accept segmentDimensions parameter
2. Create Job dispatchers & UI actions to trigger calculations with dimensions
3. E2E integration test: configure dimensions → run job → verify segmented snapshots

---

## [2026-09-29] Phase 11 — Dynamic Segmentation Architecture (Complete)

**Status**: ✅ Done

**Modul**: PD Netflow, Segmentation Config, Filament UI

**Ref PRD**: Bab 5, Bab 7

**Summary**:

Dynamic segmentation implemented. User dapat memilih dimensi segmentasi (office_code, akad_code, usage_type) via admin UI. System generate semua kombinasi & hitung PD per segment.

**Tasks**:

**Task 1 (✅)**: CalculationSegmentationConfig model & migration
- Table: method, segment_dimensions (JSON), is_active, notes
- Helper: `getSegmentDimensions(method)` retrieves active config

**Task 2 (✅)**: DynamicSegmentationResolver + PdNetflowCalculator::calculateDynamic()
- `generateSegmentCombinations()` → all combinations from raw data
- `filterBySegment()` → filter data by segment criteria
- `PdNetflowCalculator::calculateDynamic()` → loop over combinations, call calculate() per segment

**Task 3 (✅)**: PdNetflowCalculationJob updated
- Constructor accepts `segmentDimensions` parameter
- handle() calls `calculateDynamic()` if dimensions provided
- Helper `writeSegmentResult()` writes each segment result

**Task 4 (✅)**: SnapshotWriter already flexible
- writePdNetflowResult, writePdNetflowDetail, writePdNetflowHistory accept officeCode & akadCode

**Task 5 (✅)**: Filament Resource (CalculationSegmentationConfigResource)
- CheckboxList untuk select dimensions per method
- Admin dapat enable/disable configs

**Task 6 (✅)**: Integration tests (DynamicSegmentationTest)
- 6 tests covering resolver, config storage, retrieval
- All pass: combination generation, empty dimensions, filtering, persistence

**Files**:

- `database/migrations/2026_09_29_203841_create_calculation_segmentation_configs_table.php`
- `app/Models/CalculationSegmentationConfig.php`
- `app/Domain/Ckpn/Pd/Netflow/DynamicSegmentationResolver.php`
- `app/Domain/Ckpn/Pd/Netflow/PdNetflowCalculator.php` (added calculateDynamic)
- `app/Domain/Ckpn/Pd/Netflow/OutstandingMapLoader.php` (added loadRawForSegmentation)
- `app/Jobs/PdNetflowCalculationJob.php` (added segmentDimensions param + helper)
- `app/Filament/Resources/CalculationSegmentationConfigResource.php` (new)
- `tests/Feature/DynamicSegmentationTest.php` (new)

**Test Results**: 
- ✅ All 93 tests pass (1 skipped)
- ✅ 6 new dynamic segmentation tests pass
- ✅ No regressions

**Next steps**:

1. Apply same dynamic segmentation pattern to PdMigration, LgdExpectedRecoveries, LgdCollateralShortfall calculators
2. Create job dispatchers/UI actions to trigger PdNetflowCalculationJob with segmentDimensions
3. E2E testing: configure dimensions → run job → verify snapshots per segment

---

## [2026-09-29] Phase 10 — Cache Clear & Queue Restart

**Status**: ✅ Done

**Modul**: Queue Worker Cache Management

**Ref PRD**: General

**Perubahan**:

- Clear config cache, application cache, queue restart untuk resolve parameter cache issues
- Error `Unknown named parameter $office_code` resolved via cache refresh (old compiled version still in memory)

**Test**: ✅ All 87 tests pass, 1 skipped (265 assertions)

**Next step**:

- Queue worker ready untuk production job execution
- Monitor calculation results

---

## Open Items

1. **TODO**: Confirm which akad codes require `tgkmdl` vs `outstanding_balance`. Currently: akad 01 → outstanding_balance, akad 05 → tgkmdl, others → outstanding_balance.
2. **TODO**: After dynamic segmentation phase complete, apply same pattern to PdMigration, LgdExpectedRecoveries, LgdCollateralShortfall calculators.
