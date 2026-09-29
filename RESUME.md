# RESUME PROYEK CKPN (PSAK 414)

**Status Proyek**: 🔄 **85% Selesai** | 22 dari 27 phases complete (Phase 22 CKPN Collective just added to completed list)

**Last Updated**: 2026-09-29 | **Total Test Pass**: 97 tests ✅ (0 failures, 1 skipped)

---

## 📋 Ringkasan Singkat

Sistem perhitungan **CKPN (Credit Risk/Capital Requirement)** berbasis **PSAK 414** untuk lembaga keuangan syariah. Mengukur **Probability of Default (PD) × Loss Given Default (LGD)** per rekening/segmen dengan dukungan **multi-method calculation**, **dynamic segmentation**, **batch processing async**, dan **data quality validation**.

Stack: **Laravel 13 + Filament 4 Admin Panel + Pest Testing + MySQL 8**

---

## 🎯 Fungsi Utama Sistem

### 1. **Upload & Master Data Management**
- Upload data via Excel (5 tipe: master rekening, jaminan, historis periode, kantor, jenis jaminan)
- Validasi & error reporting per baris
- Async job processing via queue (`ckpn-calculation`)

### 2. **PD Calculation (2 Metode)**
| Metode | Deskripsi | Status |
|--------|-----------|--------|
| **Netflow** | Berbasis pergerakan bucket (0-13 hari, 14-30 hari, ..., 180+ hari) | ✅ Done |
| **Migration** | Berbasis transition matrix antar bucket | ✅ Done |

**Formula**: PD_rate = f(bucket_movement, rolling_window_N_bulan, quality_grade)

### 3. **LGD Calculation (2 Metode)**
| Metode | Deskripsi | Status |
|--------|-----------|--------|
| **Expected Recoveries (ER)** | LGD = 1 - (recovery_amount / writeoff_amount) | ✅ Done |
| **Collateral Shortfall (CS)** | LGD = shortfall / outstanding | ✅ Done |

**Formula**: LGD_rate = f(collateral_value, outstanding, recovery_history, account_segmentation)

### 4. **CKPN Calculation (2 Level)**
| Level | Deskripsi | Status |
|-------|-----------|--------|
| **Individual** | CKPN = PD_rate × LGD_rate per rekening | ✅ Done |
| **Collective** | CKPN = aggregate(Individual) per segmen | ✅ Done |

**Formula**: CKPN_rate = PD_rate × LGD_rate × EAD (Exposure at Default)

### 5. **Dynamic Segmentation**
Admin dapat memilih dimensi segmentasi (office_code, akad_code, usage_type) → sistem otomatis hitung semua kombinasi per metode → hasil tersimpan per-segment.

**Status**: ✅ Full support PD Netflow, PD Migration, LGD ER, LGD CS, CKPN Individual, CKPN Collective

### 6. **Data Quality Validation Dashboard**
- Deteksi anomali LGD-ER, LGD-CS snapshot
- Severity: Critical (must fix), Warning (investigate), Info (notice)
- UI dengan summary cards + detailed anomaly list

**Status**: ✅ Dashboard operational

### 7. **Batch Job Dispatcher & Monitoring**
- User select: period (YYYYMM), usage_type, methods (PD Netflow, PD Migration, LGD-ER, LGD-CS)
- Trigger semua jobs paralel via queue
- Queue worker async processing + run_log tracking
- Notification saat job complete/fail

**Status**: ✅ Full integration

### 8. **Export & Reporting**
- Excel export: LGD-ER, LGD-CS snapshot results
- Formatted: headers (blue bg), data (thousand separator, 2-8 decimals)
- Multiple report views (CKPN Final Report, Summary, Data Quality Anomaly)

**Status**: ✅ Operational

---

## 🔄 Alur Kerja (End-to-End)

```
1. UPLOAD DATA
   ├─ Excel file (master/collateral/period/office/type)
   ├─ Async job validation & import to financing_* tables
   └─ Error log jika ada

2. CLASSIFY PERIOD DATA
   ├─ Job: ClassifyPeriodDataJob
   ├─ Extract financing_account_periods dengan office_code, akad_code, usage_type
   └─ Write to ckpn_period_classifications

3. CALCULATE PD
   ├─ Select method: Netflow OR Migration
   ├─ Select period & usage_type
   ├─ Optional: select segment dimensions (office_code, akad_code, usage_type)
   ├─ Dispatch job → queue
   ├─ Job process:
   │  ├─ If dimensions: calculateDynamic() loop per segment
   │  └─ Else: calculate() single all-account
   ├─ Write snapshots:
   │  ├─ pd_netflow_result (IF Netflow)
   │  ├─ pd_netflow_detail (per bucket)
   │  ├─ pd_netflow_history (rolling window)
   │  └─ pd_migration_matrix, pd_migration_result (IF Migration)
   └─ Update run_log status: Pending → Processing → Completed

4. CALCULATE LGD
   ├─ Select method: Expected Recoveries OR Collateral Shortfall (or both)
   ├─ Dispatch job(s) → queue
   ├─ Job process:
   │  ├─ If dimensions: calculateDynamic() loop per segment
   │  ├─ LGD-ER: aggregate writeoff/recovery per period/segment
   │  └─ LGD-CS: per-account shortfall vs outstanding
   ├─ Write snapshots:
   │  ├─ lgd_expected_recoveries_result
   │  ├─ lgd_collateral_shortfall_result
   │  └─ lgd_collateral_shortfall_by_segment_result (segment summary)
   └─ Update run_log

5. DATA QUALITY VALIDATION
   ├─ Admin navigate to Data Quality Validation page
   ├─ Select period & usage_type
   ├─ System detect anomalies:
   │  ├─ LGD rate out of [0,1]
   │  ├─ Recovery + LGD ≠ 1.0
   │  ├─ Zero accounts/writeoff
   │  └─ Shortfall > outstanding
   └─ Display severity-coded list (critical/warning/info)

6. CALCULATE CKPN INDIVIDUAL
   ├─ Dispatch job → queue
   ├─ Per-account: CKPN = latest PD_rate (from pd_netflow_result) × LGD_rate (from lgd_expected_recoveries_result)
   ├─ Write: ckpn_individual_result (period, usage_type, account, ckpn_rate)
   └─ Idempotent: skip if period already Completed/Approved

7. CALCULATE CKPN COLLECTIVE
   ├─ Dispatch job → queue
   ├─ Aggregate per segment: ∑(CKPN Individual) / account_count
   ├─ Write: ckpn_collective_result
   └─ Idempotent guard

8. EXPORT & REPORT
   ├─ View results in Filament resource tables (read-only)
   ├─ Filter by period, usage_type, office_code
   ├─ Bulk export to Excel
   └─ Generate final reports (Rekapitulasi, Summary, Final Report)
```

---

## 📊 Struktur Database (Snapshot Tables)

| Tabel | Deskripsi | Immutable | Snapshot |
|-------|-----------|-----------|----------|
| `pd_netflow_result` | PD rate per period/segment/method | ✅ Insert-only | ✅ Yes |
| `pd_netflow_detail` | PD breakdown per bucket | ✅ Insert-only | ✅ Yes |
| `pd_netflow_history` | PD per rolling window | ✅ Insert-only | ✅ Yes |
| `pd_migration_matrix` | Transition matrix | ✅ Insert-only | ✅ Yes |
| `pd_migration_result` | PD Migration rate | ✅ Insert-only | ✅ Yes |
| `lgd_expected_recoveries_result` | LGD ER per period/segment | ✅ Insert-only | ✅ Yes |
| `lgd_collateral_shortfall_result` | LGD CS aggregate | ✅ Insert-only | ✅ Yes |
| `lgd_collateral_shortfall_by_segment_result` | LGD CS per segment | ✅ Insert-only | ✅ Yes |
| `ckpn_individual_result` | CKPN per account | ✅ Insert-only | ✅ Yes |
| `ckpn_collective_result` | CKPN aggregate | ✅ Insert-only | ✅ Yes |

**Implication**: Semua snapshot bersifat immutable (no update/delete bebas). Modifikasi hanya via approval workflow atau migration versi baru.

---

## 📁 Struktur Kode (Domain-Driven)

```
app/Domain/Ckpn/
├─ Pd/
│  ├─ Netflow/
│  │  ├─ PdNetflowCalculator (core logic)
│  │  ├─ DynamicSegmentationResolver (segment combination generator)
│  │  ├─ OutstandingMapLoader (data extraction)
│  │  ├─ BucketMovementValidator (data quality)
│  │  └─ BucketingService (hari tunggakan → bucket mapping)
│  ├─ Migration/
│  │  ├─ PdMigrationCalculator
│  │  └─ MigrationMatrixBuilder
│  └─ Contracts/
│     └─ PdCalculationMethodInterface
├─ Lgd/
│  ├─ ExpectedRecoveries/
│  │  └─ LgdExpectedRecoveriesCalculator
│  ├─ CollateralShortfall/
│  │  └─ LgdCollateralShortfallCalculator
│  ├─ Contracts/
│  │  └─ LgdCalculationMethodInterface
│  └─ LgdFinalCalculator (combined ER + CS)
├─ Individual/
│  └─ CkpnIndividualCalculator (PD × LGD per account)
├─ Collective/
│  └─ CkpnCollectiveCalculator (aggregate Individual)
├─ Services/
│  ├─ SnapshotWriter (insert-only result tables)
│  ├─ RollingWindowResolver (period windowing logic)
│  ├─ BucketingService (lazy-load bucket mapping)
│  ├─ DataQualityValidationService (anomaly detection)
│  ├─ CalculationDispatchService (batch job trigger)
│  ├─ AkadCalculationRulesRepository (dynamic field selection)
│  └─ OfficeSegmentResolver, AkadSegmentResolver (segmentation)
└─ Preview/
   ├─ CkpnPreviewService
   └─ CkpnPreviewResult, CkpnPreviewRow (DTO)

app/Jobs/
├─ PdNetflowCalculationJob (dispatch & queue handler)
├─ PdMigrationCalculationJob
├─ LgdErCalculationJob
├─ LgdCsCalculationJob
├─ CkpnIndividualCalculationJob
├─ CkpnCollectiveCalculationJob
├─ ClassifyPeriodDataJob (extract financing_account_periods → ckpn_period_classifications)
└─ CsvExportJob, PdNetflowDetailBreakdownExportJob (batch export)

app/Models/
├─ Calculation*: CalculationRunLog, CalculationSegmentationConfig, CalculationParameter*
├─ Result*: PdNetflowResult, LgdExpectedRecoveriesResult, CkpnIndividualResult, etc.
├─ Financing*: FinancingAccount, FinancingAccountPeriod, FinancingOffice
├─ Collateral*: Collateral, CollateralType, CollateralSalesData
└─ Master: Bucket, QualityGrade, RiskSegment, DataQualityAnomaly

app/Filament/
├─ Actions/ (dispatch calculation jobs from UI)
│  ├─ DispatchPdNetflowCalculationAction
│  ├─ DispatchLgdCalculationAction
│  ├─ BatchCalculationAction (trigger all methods at once)
│  ├─ ExportSnapshotAction (bulk export to Excel)
│  └─ DispatchCkpnIndividualCalculationAction, DispatchCkpnCollectiveCalculationAction
├─ Pages/ (Filament custom pages)
│  ├─ CalculationBatchPage (UI form for batch dispatch)
│  └─ DataQualityValidationPage (anomaly dashboard)
└─ Resources/ (CRUD interfaces)
   ├─ PdNetflowResultResource (read-only snapshot viewer)
   ├─ LgdExpectedRecoveriesResultResource
   ├─ CkpnIndividualResultResource
   └─ CalculationRunLogResource (job monitoring)

app/Enums/
├─ RunType (PdNetflow, PdMigration, LgdExpectedRecoveries, LgdCollateralShortfall, CkpnIndividual, CkpnCollective)
├─ RunStatus (Pending, Processing, Completed, Failed, Approved)
├─ UsageType (Investasi, Konsumsi, ModalKerja, etc.)
├─ Bucket (0-13h, 14-30h, ..., 180+h enum)
└─ AnomalySeverity (Critical, Warning, Info)
```

**Prinsip**: Single Responsibility — setiap class satu tanggung jawab. Calculation logic ≠ Job handler ≠ Repository.

---

## ✅ Fitur Sudah Selesai (Phase 1-21)

| Phase | Modul | Status | Catatan |
|-------|-------|--------|---------|
| 1 | Schema & Akad Rules | ✅ Done | Dynamic field selection per akad_code |
| 2 | ClassifyPeriodDataJob | ✅ Done | Extract + populate office_code, akad_code |
| 3 | UI Filter Updates | ✅ Done | CkpnClassificationIndex, PdNetflowResultIndex |
| 4 | PD Migration & LGD Dynamic Field | ✅ Done | MigrationMatrixBuilder, LgdExpectedRecoveries, LgdCS |
| 5 | Bug Fix & Test Validation | ✅ Done | Syntax error, recovery rate formula |
| 6 | UI Column Name Fix | ✅ Done | FinancingOffice query, CalculationParameterIndex |
| 7 | Livewire Component Audit | ✅ Done | PdNetflowPivotIndex, CkpnClassificationIndex rendering |
| 10 | Cache Clear & Queue Restart | ✅ Done | Parameter cache fix |
| 11 | Dynamic Segmentation Architecture | ✅ Done | CalculationSegmentationConfig, DynamicSegmentationResolver, PdNetflowCalculator::calculateDynamic() |
| 12 | Apply Segmentation to All Calculators | ✅ Done | PdMigration, LgdER, LgdCS all support calculateDynamic() |
| 13 | Update All Job Classes | ✅ Done | PdNetflowCalcJob, PdMigrationCalcJob, LgdErCalcJob, LgdCsCalcJob support segmentDimensions |
| 14 | Filament Actions for Job Dispatch | ✅ Done | DispatchPdNetflow, DispatchPdMigration, DispatchLgdCalculation actions |
| 16 | E2E Validation | ✅ Done | LgdCalculationE2eTest: dispatch → job → snapshot verified |
| 17 | Snapshot Preview UI | ✅ Done | LgdErResultResource, LgdCsResultResource (read-only viewers) |
| 18 | Batch Calculation Trigger | ✅ Done | BatchCalculationAction, CalculationBatchPage |
| 19 | Export to Excel | ✅ Done | ExportSnapshotAction, LgdErExport, LgdCsExport |
| 20 | Data Quality Validation Dashboard | ✅ Done | DataQualityValidationService, DataQualityValidationPage |
| 21 | CKPN Individual Calculation | ✅ Done | CkpnIndividualCalculator, CkpnIndividualCalculationJob, snapshot + Filament resource |
| 22 | CKPN Collective Calculation | ✅ Done | CkpnCollectiveCalculator (PD×LGD×EAD per account), CkpnCollectiveCalculationJob, Filament resource, dynamic segmentation |

**Total**: 22 phases selesai (infrastructure + all calculation engines + Filament UI) = ~85% project complete

---

## ⏳ Tugas Belum Selesai (Phase 22+)

| Phase | Task | Priority | Est. Work | Status |
|-------|------|----------|-----------|--------|
| 22 | **CKPN Collective Calculation** | 🟢 Done | — | ✅ Implemented: CkpnCollectiveCalculator, CkpnCollectiveCalculationJob, Filament resource, dynamic segmentation support |
| 23 | **PD Netflow & Migration Resource UI** | 🟡 Medium | Small | ⏳ Pending — Read-only Filament resources untuk view PD results (PdNetflowResultResource, PdMigrationResultResource); filters + export |
| 24 | **Approval Workflow** | 🟡 Medium | Large | ⏳ Pending — Mark snapshots as Approved/Rejected; audit trail; policy protection per status (Completed → lock snapshot); Filament action buttons |
| 25 | **Final Report Generation** | 🟡 Medium | Medium | ⏳ Pending — Rekapitulasi per period/segmen; export PDF; mail delivery; schedule via cron |
| 26 | **Performance Optimization** | 🟢 Low | Small | ⏳ Pending — Query indexing (already done mostly); cache result snapshots; pagination large tables |
| 27 | **Production Checklist** | 🟢 Low | Small | ⏳ Pending — Environment config review; queue monitoring setup; backup strategy; error logging (Sentry/similar) |

**Blocking**:
- Phase 22 (CKPN Collective) ✅ **DONE** — now ready for Phase 23+
- Phase 23-24 wajib sebelum dapat report final + immutability lock

---

## 🧪 Test Coverage

**Total Tests**: 97 ✅ (1 skipped) | **Assertions**: 265+

| Kategori | Count | Notes |
|----------|-------|-------|
| Unit Tests | 45+ | Formula validation (PD, LGD, CKPN calculation) |
| Feature Tests | 30+ | End-to-end: job dispatch → snapshot → validation |
| E2E Tests | 5+ | Full flow: data upload → classify → calculate → export |
| Factories | 35+ | Model factories untuk seeding test data |

**Quality**: All pass, zero failures, zero regressions (as of Phase 21).

---

## 🔐 Security & Compliance

- **Role-Based Access Control**: Shield permission (super_admin, risk_analyst, approver, viewer)
- **Snapshot Immutability**: Insert-only; no update/delete post-Completed
- **Audit Trail**: CalculationRunLog tracks job execution + error messages
- **Data Quality**: Anomaly detection + severity flagging (PRD Bab 7.3)
- **Input Validation**: File upload validation, field type enforcement, FK constraints

---

## 📈 Performance Notes

- **Batch Processing**: Async jobs via queue (non-blocking)
- **Indexed Queries**: Composite indexes on (period, office_code, akad_code, usage_type) for fast lookups
- **Lazy Loading**: BucketingService loads buckets per-query (not constructor cache)
- **Raw SQL**: Only for heavy aggregations (bucket movement, compound flow) with comment rationale

---

## 📝 Key Architectural Decisions

1. **Strategy Pattern**: Each PD/LGD method implements interface → pluggable, extensible
2. **Immutable Snapshots**: Insert-only, no update post-Completed → audit-safe
3. **Idempotent Jobs**: Check status before executing; skip if already Completed/Approved
4. **Dynamic Segmentation**: Admin select dimensions → system auto-generate combinations → per-segment results
5. **Async Queue**: All heavy calculations (batch processing) via queue, never blocking request
6. **Service Layer**: Logic in Domain/, not Controller/Resource → testable, reusable
7. **DTO/Value Objects**: Strong typing for calculation results (not loose array)
8. **PSR-12 + Strict Types**: Type safety + linting via Pint

---

## 🚀 How to Run

```bash
# Setup
composer install && npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed

# Dev (all-in-one: server + queue + vite + log tail)
composer run dev

# Or manually
php artisan serve                                    # Terminal 1: Web server
php artisan queue:work --queue=ckpn-calculation     # Terminal 2: Queue worker
npm run dev                                          # Terminal 3: Vite assets

# Access
http://localhost:8000/admin
```

**Default Credentials**: See `.env` file (seeded via AdminUserSeeder)

---

## 📚 Documentation

| Doc | Path | Purpose |
|-----|------|---------|
| **PRD** | (not in repo yet) | Spesifikasi bisnis lengkap (formula, domain, data model) |
| **AGENTS** | `AGENTS.md` | Panduan kerja AI agent (token efficiency, standards) |
| **Progress** | `PROGRESS.md` | Detailed log per phase (files changed, test results) |
| **README** | `README.md` | Quick start, stack, directory structure |
| **This Resume** | `RESUME.md` | High-level overview (functions, workflow, tasks) |

---

## 🎯 Next Steps (Priority Order)

1. **Complete Phase 22**: CKPN Collective Calculation
   - Implement formula, job, snapshot table, Filament resource
   - E2E test
   - ~2-3 hours

2. **Phase 23**: PD Netflow & Migration Resource UI
   - Read-only Filament resources (if not done)
   - Filters + export button
   - ~1-2 hours

3. **Phase 24**: Approval Workflow
   - Approve/Reject actions
   - Immutability lock post-Approved
   - Audit trail
   - ~3-4 hours

4. **Phase 25**: Final Report Generation
   - Rekapitulasi export
   - PDF generation
   - Email delivery
   - ~2-3 hours

5. **Phase 26-27**: Performance + Production Checklist
   - Monitoring, backups, error logging
   - ~1-2 hours

**Estimated Remaining**: ~8-12 hours to full completion (Phase 23-27; Phase 22 ✅ complete)

---

## 📌 Key Metrics (as of Phase 21)

- **Code Quality**: PSR-12 + Pint + strict types
- **Test Coverage**: 97 tests pass, 265+ assertions
- **Models**: 45+ Eloquent models
- **Calculators**: 6 (PD Netflow, PD Migration, LGD ER, LGD CS, CKPN Individual, CKPN Collective)
- **Filament Resources**: 15+ (master data + snapshot viewers)
- **Jobs**: 12+ async job handlers
- **Enums**: 14+ (RunType, RunStatus, UsageType, Bucket, etc.)
- **Database Tables**: 65+ (master + staging + snapshot)
- **Segmentation Dimensions**: 3 (office_code, akad_code, usage_type) — combinatorial generation

---

**Project Health**: 🟢 **Stable & Production-Ready (except final phases)**

*Generated: 2026-09-29 | Framework: Laravel 13 + Filament 4 | Language: ID/EN*
