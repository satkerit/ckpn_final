# PROGRESS.md — Log Progres Pembangunan Sistem CKPN

> Format entri: lihat `AGENTS.md` Bab 12. Tulis ringkas & padat (hemat token) — ini log kerja, bukan laporan naratif.
> Entri terbaru ditambahkan **di paling atas**.

---

## [2026-08-18] Fase 11 — FinancingAccountResource + Feature Tests

- Status: Done
- Modul: DataPembiayaan Resource, Feature Tests end-to-end
- Ref PRD: Bab 6.1, 15
- Perubahan:
    1. `FinancingAccountResource` di cluster `DataPembiayaanCluster` — View/Edit, filter aktif/jenis penggunaan
    2. `tests/Pest.php` dibuat — `uses(TestCase::class, RefreshDatabase::class)->in('Feature')`
    3. `RiskSegmentFactory` fix — `words()` diganti string literal (FakerPHP v2 compat)
    4. `CkpnIndividualFeatureTest` — 4 feature tests: empty segment, kol-3 kalkulasi, filter kol 3/4/5, top-N selection
    5. `FinancingImportTest` — 4 feature tests: import valid, skip nokontrak kosong, upsert no-dup, parseDate
    6. `ExampleTest` — 1 test HTTP response
- Test: 9/9 Feature PASS + 36/36 Unit PASS = 45 tests total
- Next step: Sistem siap untuk testing manual via browser; opsional — queue supervisor config

---

## [2026-08-18] Fase 10 — Master Data Resources + Bucket/QualityGrade Seeder

- Status: Done
- Modul: MasterDataCluster Resources, Seeders Bucket & QualityGrade
- Ref PRD: Bab 5, 7, 8, 10.2, 15
- Perubahan:
    1. 5 Filament Resources di `MasterDataCluster`: `RiskSegmentResource`, `CollateralTypeResource`, `CalculationParameterResource`, `BucketResource`, `QualityGradeResource` — semua dengan Create/Edit/Delete
    2. 15 Pages files untuk semua resources di atas
    3. `BucketSeeder` — 7 bucket default (B0-BWO); TODO(PRD Bab 12.1) rentang SEMENTARA
    4. `QualityGradeSeeder` — 5 kualitas (L, DPK, KL, D, M) dengan flag is_npl
    5. `DatabaseSeeder` diperbarui — 6 seeders berjalan berurutan
    6. `db:seed` sukses semua
- Test: 36/36 PASS (65 assertions)
- Next step: Feature tests end-to-end, FinancingAccountResource di cluster DataPembiayaan

---

## [2026-08-18] Fase 9 — Filament Shield, Seeders Data Awal

- Status: Done
- Modul: Role & Permission, Master Data Seeder
- Ref PRD: FR-14
- Perubahan:
    1. Install `bezhansalleh/filament-shield 4.3.1` + `spatie/laravel-permission 8.3.0`
    2. `HasRoles` trait ditambahkan ke `User` model
    3. `shield:install admin` — Shield terdaftar di panel admin
    4. `shield:generate --all --panel=admin` — 19 policies + 235 permissions untuk 26 entities
    5. `RolesAndPermissionsSeeder` — 4 roles: super_admin, risk_analyst, approver, viewer
    6. `RiskSegmentSeeder` — 6 segmen risiko default (MRB, MRA, KCL, KMN, KBB, KPN)
    7. `CollateralTypeSeeder` — 6 tipe jaminan default dengan discount rate
    8. `CalculationParameterSeeder` — 8 parameter kalkulasi default (rolling window, forward projection, selling cost, top-N, PD method)
    9. `DatabaseSeeder` diperbarui — memanggil semua seeders + assign super_admin ke admin user
    10. `db:seed` sukses — semua 4 seeders DONE
- Test: 36/36 PASS (65 assertions)
- Next step: Feature tests end-to-end, queue supervisor config production, UI login test

---

## [2026-08-18] Fase 8 — Excel Template, Period Management, Workflow Approval

- Status: Done
- Modul: Export template, CkpnPeriod, Approval workflow
- Ref PRD: Bab 12a Step 2 & 7, FR-11
- Perubahan:
    1. `FinancingUploadTemplateExport` — template Excel dengan heading row + baris contoh, bold header
    2. Action "Download Template" ditambahkan ke `FinancingUploadBatchResource`
    3. Migration + Model `CkpnPeriod` — status: draft → in_progress → completed → approved
    4. `CkpnPeriodResource` di cluster `CkpnCluster` — CRUD periode, actions: Mulai / Tandai Selesai / Approve / Reject
    5. `CalculationRunLogResource` — ditambah row actions Approve + Reject dengan form catatan
- Test: 36/36 PASS (65 assertions)
- Next step: Filament Shield (role & permission), queue worker config, seeding data awal

---

## [2026-08-18] Fase 7 — Upload Excel, Data Pembiayaan, Reporting

- Status: Done
- Modul: Import Excel + DataPembiayaan Cluster + Reporting Cluster
- Ref PRD: Bab 15, 12a Step 7
- Perubahan:
    1. Install `maatwebsite/excel 4.0.1`
    2. `app/Imports/FinancingPeriodUploadImport.php` — import Excel kolom nokontrak/kdprd/pokpby/osmdlc/colbaru/tglwo/stsrec/stsacc/tgleff/tglexp/periode; upsert `financing_accounts` + `financing_period_uploads`; validasi 4 kolom wajib; skip-on-failure
    3. `ProcessFinancingUploadJob` — async, idempotent, dispatch dari Filament action
    4. `FinancingUploadBatchResource` di `DataPembiayaanCluster` — upload Excel via action, status badge (Pending/Processing/Done/Failed), modal lihat error per baris
    5. `CkpnSummaryResource` di `ReportingCluster` — konsolidasi CKPN Total = Individual + Kolektif per periode+segmen
    6. View `financing-upload-errors.blade.php` untuk modal error
- Test: 36/36 PASS (65 assertions)
- Next step: Excel template generator (download template kosong), Period Management page, workflow approval CKPN

---

## [2026-08-18] Fase 6 — Domain CKPN Individual + Kolektif

- Status: Done
- Modul: CKPN Individual (Bab 6.1) + CKPN Kolektif (Bab 11)
- Ref PRD: Bab 6.1, 11, 12a
- Perubahan:
    1. 2 migration baru: `ckpn_individual_result` (insert-only, created_at only), `ckpn_collective_result` (insert-only, created_at only)
    2. `CkpnIndividualCalculator` — kol 3/4/5 + top-N outstanding terbesar per segmen; parameter dari `calculation_parameters`
    3. `CkpnCollectiveCalculator` — PD × LGD × EAD per akun; LGD CS untuk kol-5/WO, LGD ER untuk lainnya; PD method configurable
    4. 2 Eloquent Model: `CkpnIndividualResult`, `CkpnCollectiveResult` (UPDATED_AT=null)
    5. `SnapshotWriter::writeCkpnIndividualResults()` + `writeCkpnCollectiveResults()` — insert-only
    6. 2 Jobs: `CkpnIndividualCalculationJob`, `CkpnCollectiveCalculationJob` — idempotent, ambil parameter dari `calculation_parameters`
    7. Filament Resources di cluster `CkpnCluster`: `CkpnIndividualResultResource` + `CkpnCollectiveResultResource` — read-only + header action trigger job
    8. 2 Factories: `CkpnIndividualResultFactory`, `CkpnCollectiveResultFactory`
    9. 14 unit tests, semua PASS (16 assertions)
- Next step: Fase 7 — Upload Excel handler, Period Management, Reporting/Konsolidasi CKPN Total
- Open items: TODO(PRD Bab 12.2): konfirmasi apakah biaya penjualan Individual = shared param LGD CS; TODO(PRD Bab 12.3): skema kombinasi PD final

---

## [2026-08-18] Fase 4 — Domain PD Migration (Migration, Models, Calculator, Job, Resource, Tests)

- Status: Done
- Modul: PD Migration — Database + Domain Layer + Queue Job + Filament Resource
- Ref PRD: Bab 8, 8.3, 13.2, 16
- Perubahan:
    1. 2 migration baru dijalankan: `pd_migration_matrix` (FK restrict, unique partial tanpa to_quality_grade_id, created_at only), `pd_migration_result` (FK restrict, unique per run+segment+grade, created_at only)
    2. `MigrationMatrixBuilder` — build matrix per cohort (source/dest outstanding ratio + WO proportional, TODO: per-grade WO tracing PRD Bab 12)
    3. `PdMigrationCalculator implements PdCalculationMethodInterface` — resolveCohorts (quarterly, rolling window), average WO rate per grade
    4. 2 Eloquent Model: `PdMigrationMatrix`, `PdMigrationResult` (timestamps=false, created_at only, relasi BelongsTo nullable untuk toQualityGrade)
    5. `SnapshotWriter::writePdMigrationResult()` — insert-only per qualityGradeId
    6. `PdMigrationCalculationJob` — idempotent, ambil `pd_migration_window_years` dari `calculation_parameters`, default 3
    7. Filament Resource `PdMigrationResultResource` di cluster `PdMigrationCluster` — read-only, header action "Jalankan Perhitungan PD Migration", filter segmen & periode
    8. Unit tests: `PdMigrationCalculatorTest` — 4 tests/25 assertions PASS
    9. `pint` — 7 file diformat, clean
- File utama yang berubah:
    - `database/migrations/2026_08_18_14363[3-4]_*.php` (2 file)
    - `app/Domain/Ckpn/Pd/Migration/{MigrationMatrixBuilder,PdMigrationCalculator}.php`
    - `app/Domain/Ckpn/Services/SnapshotWriter.php`
    - `app/Models/{PdMigrationMatrix,PdMigrationResult}.php`
    - `app/Jobs/PdMigrationCalculationJob.php`
    - `app/Filament/Clusters/PdMigration/Resources/PdMigrationResultResource.php`
    - `app/Filament/Clusters/PdMigration/Resources/PdMigrationResultResource/Pages/ListPdMigrationResults.php`
    - `tests/Unit/Domain/PdMigration/PdMigrationCalculatorTest.php`
- Next step: Fase 5 — LGD Expected Recoveries (PRD Bab 9)

---

## [2026-08-18] Fase 3 — Domain PD Netflow (Migration, Models, Calculator, Job, Tests)

- Status: Done
- Modul: PD Netflow — Database + Domain Layer + Queue Job
- Ref PRD: Bab 7, 7.1, 7.3, 7.4, 13.2, 16
- Perubahan:
    1. 3 migration baru dijalankan: `pd_netflow_bucket_movement`, `pd_netflow_compound_rate`, `pd_netflow_result` (FK restrict, unique constraint, created_at only)
    2. Domain classes baru: `PdCalculationMethodInterface`, `PeriodHelper`, `RollingWindowResolver`, `BucketingService`, `SnapshotWriter`
    3. `BucketMovementValidator` — validasi anomali empty bucket & destination > source, flag ke `data_quality_anomalies`
    4. `PdNetflowCalculator` — implementasi penuh 7-langkah PRD (transition rate, proyeksi forward, compound flow, rata-rata PD)
    5. 3 Eloquent Model baru: `PdNetflowBucketMovement`, `PdNetflowCompoundRate`, `PdNetflowResult` (timestamps=false, created_at only, relasi BelongsTo)
    6. `PdNetflowCalculationJob` — idempotent, queue-able, ambil parameter dari `calculation_parameters`
    7. Bug fix: `PeriodHelper::shiftBack` refactor ke 0-based month encoding (`floor()` ganti `intdiv()`); `RollingWindowResolver` formula diperbaiki sesuai PRD (projectionStart = shiftBack(period, forward\*2-1); outstandingStart = shiftBack(outstandingEnd, windowMonths))
    8. Unit tests: `PeriodHelperTest` (4 tests/10 assertions PASS), `RollingWindowResolverTest` (6 tests/6 assertions PASS)
    9. `pint` — 20 file diformat, clean
- File utama yang berubah:
    - `database/migrations/2026_08_18_09272[6-9]_*.php` (3 file)
    - `app/Domain/Ckpn/Pd/Contracts/PdCalculationMethodInterface.php`
    - `app/Domain/Ckpn/Pd/Netflow/BucketMovementValidator.php`
    - `app/Domain/Ckpn/Pd/Netflow/PdNetflowCalculator.php`
    - `app/Domain/Ckpn/Services/{PeriodHelper,RollingWindowResolver,BucketingService,SnapshotWriter}.php`
    - `app/Models/{PdNetflowBucketMovement,PdNetflowCompoundRate,PdNetflowResult}.php`
    - `app/Jobs/PdNetflowCalculationJob.php`
    - `tests/Unit/Domain/PdNetflow/{PeriodHelperTest,RollingWindowResolverTest}.php`
- Next step: Fase 3b — Filament Resource untuk PD Netflow (trigger job, tampilkan hasil, anomaly list)

---

## [2026-08-18] Fase 2 — Filament Clusters & Resources

- Status: Done
- Modul: Filament Panel — Clusters & Resources (MasterData + DataPembiayaan)
- Ref PRD: Bab 5, 15
- Perubahan:
    - 7 Filament Clusters dibuat: MasterData, DataPembiayaan, PdNetflow, PdMigration, Lgd, Ckpn, Reporting — masing-masing dengan icon Heroicon dan navigationLabel
    - 8 Filament Resources + Pages dibuat: RiskSegment, Bucket, QualityGrade, CollateralType, FinancingOffice, CalculationParameter (cluster MasterData); FinancingAccount, FinancingUploadBatch (cluster DataPembiayaan, read-only)
    - AdminPanelProvider diupdate: discoverClusters(), Color::Blue, strict_types=1
    - AdminUserSeeder dibuat dan dijalankan (admin@ckpn.local)
    - Fix Filament 4 API: form()/infolist() signature diubah ke Schema $schema: Schema (breaking change dari Filament 3)
    - 31 admin routes terdaftar, ExampleTest: 2 passed
- File utama: app/Filament/Clusters/_, app/Filament/Resources/_, app/Providers/Filament/AdminPanelProvider.php, database/seeders/AdminUserSeeder.php
- Next step: Fase 3 — Domain kalkulasi PD Netflow (Bab 7 PRD)

---

## Template Entri (copy saat menambah entri baru)

```
## [yyyy-mm-dd] Ringkasan Perubahan
- Status: Done / In Progress / Blocked
- Modul: (mis. PD Netflow - Bucket Movement Validator)
- Ref PRD: Bab X.Y
- Perubahan: ringkas 1-3 baris
- File utama yang berubah: path saja
- Next step / blocker (jika ada)
```

---

## [2026-08-18] Fase 1 — PHP Enums, Eloquent Models & Factories

- Status: Done
- Modul: App Layer — Enums, Models, Factories (Fase 1)
- Ref PRD: Bab 5, 7, 8, 9, 10, 13.2, 15, 17
- Perubahan:
    1. Buat 7 PHP native enum di `app/Enums/`: `RunStatus`, `UploadBatchStatus`, `RunType`, `UsageType`, `AnomalyType`, `FinancingStatus`, `WriteoffStatus`
    2. Generate 18 Eloquent Model via `artisan make:model -f` lalu edit: `declare(strict_types=1)`, `$fillable`, `casts()` (decimal:2 uang, decimal:8 rate, enum cast), relasi HasMany/BelongsTo sesuai PRD Bab 15
    3. Edit 18 Factory di `database/factories/` dengan data realistis (factory-chaining untuk FK, enum random, nilai finansial realistis)
    4. `composer dump-autoload` — 9538 class, no error; `php artisan test --filter=ExampleTest` — 2 passed; `pint` — format clean (binary_operator_spaces, concat_space)
- File utama yang berubah:
    - `app/Enums/` (7 file baru)
    - `app/Models/` (18 file diedit)
    - `database/factories/` (18 file diedit)
- Next step: Fase 2 — Filament Resources (Master Data cluster: RiskSegment, Bucket, QualityGrade, CollateralType, FinancingOffice, CalculationParameter)

---

## [2026-08-18] Fase 1 — Generate & Migrate 18 Migration Tabel CKPN

- Status: Done
- Modul: Database — Fase 1 (Master, Transaksional, Log)
- Ref PRD: Bab 15, Bab 17
- Perubahan:
    1. Generate 18 migration via `php artisan make:migration` (urutan timestamp menjamin FK dependency terpenuhi)
    2. Edit semua migration: `declare(strict_types=1)`, tipe kolom sesuai spesifikasi (DECIMAL(20,2) uang, DECIMAL(10,8) rate, CHAR(6) periode, VARCHAR no ENUM MySQL native)
    3. FK `onDelete('restrict')` pada semua tabel transaksional; tidak ada `softDeletes()` di tabel snapshot
    4. `php artisan migrate` — 18 migration DONE tanpa error
- File utama yang berubah: `database/migrations/2026_08_18_08484[8-9]_*.php` s/d `2026_08_18_084907_*.php` (18 file)
- Next step: Generate Eloquent Models + Factories untuk setiap tabel Fase 1

---

## [2026-08-18] Klarifikasi Alur Sistem & Mekanisme PD Netflow Rolling Window

- Status: Done
- Modul: PRD — Bab 7 (PD Netflow), Bab 12 (Open Items), Bab 12a (Alur Sistem baru)
- Ref PRD: Bab 7.1, 7.4, 12, 12a
- Perubahan:
    1. Bab 7.1 — pertegas pemisahan rentang data: outstanding (window+1 titik), rate perpindahan aktual, proyeksi 6 bulan, compound flow (tidak termasuk proyeksi). Contoh konkret Desember 2026 window 36 bulan.
    2. Bab 7.4 — tabel parameter diperbarui dengan nilai dikonfirmasi (36 bulan default, 6 bulan proyeksi).
    3. Bab 12a (baru) — alur sistem 7 langkah end-to-end beserta dependensi antar langkah.
    4. Bab 12 — item yang sudah dikonfirmasi dipindah ke bagian "sudah dikonfirmasi"; open item no. 5 (rolling window) dan no. 8 (sumber data) dihapus karena sudah terjawab; renumber.
- File utama yang berubah: `docs/PRD.md`
- Next step: konfirmasi open items sisa (bucket 2–13, nilai N CKPN Individual, kebijakan kombinasi PD/LGD per segmen), lalu mulai Fase 1 — generate migration Laravel.

---

## [2026-08-18] Tambah Struktur Upload Data Pembiayaan & Master Kantor

- Status: Done
- Modul: Database Design — Data Transaksional & Upload
- Ref PRD: Bab 15 (tabel transaksional), Bab 7.2 (data outstanding)
- Perubahan:
    1. `financing_accounts` — diperluas dengan kolom: `product_code` (kdprd), `akad_code` (pokpby), `office_code` (kdloc), `economic_sector`, `usage_type` (jenis_penggunaan 1/2/3), `origination_date` (tgleff), `maturity_date` (tglexp)
    2. Tabel baru `financing_offices` — master kantor/cabang (kdloc, name, is_active)
    3. Tabel baru `financing_period_uploads` — data per periode upload Excel (nokontrak, osmdlc, colbaru, tglwo, stsrec, stsacc, periode) + FK ke `financing_upload_batches`
    4. Tabel baru `financing_upload_batches` — audit trail batch upload (filename, user, status, error_summary)
    5. Urutan migration diperbarui: total 18 langkah (dari 15)
- File utama yang berubah: `docs/DATABASE.md`
- Next step: konfirmasi open items PRD Bab 12, lalu mulai Fase 1 — generate migration Laravel (urutan sesuai Bab 8 DATABASE.md)

---

## [2026-08-18] Technical Design & Database Schema

- Status: Done (draft teknis)
- Modul: Database Design
- Ref PRD: Bab 15 (daftar tabel), Bab 5/6/7/8/9/10/11 (detail bisnis per tabel)
- Perubahan: Dibuat `DATABASE_DESIGN.md` — skema lengkap 24 tabel (master, transaksional, hasil PD Netflow, PD Migration, LGD-ER, LGD-CS, konsolidasi CKPN) dengan kolom, tipe data, index, FK, dan urutan migration. Beberapa kolom ditandai ⚠️ TBD menunggu open item PRD Bab 12.
- File utama yang berubah: `DATABASE_DESIGN.md` (baru)
- Next step: konfirmasi open items (rentang bucket 2-13, kebijakan kombinasi PD/LGD, pemisahan tabel writeoff_data per konteks), lalu mulai Fase 1 — generate migration Laravel.

---

## [2026-08-18] Inisialisasi Dokumentasi Proyek

- Status: Done
- Modul: Dokumentasi (PRD, AGENTS, Progress)
- Ref PRD: seluruh bab (dokumen awal)
- Perubahan: PRD.md v2.0 selesai (mencakup CKPN Individual, PD Netflow, PD Migration, LGD Expected Recoveries, LGD Collateral Shortfall). AGENTS.md selesai (standar teknis, efisiensi token, clean code, reusability, kewajiban update progress/dokumentasi).
- File utama yang berubah: `PRD.md`, `AGENTS.md`, `PROGRESS.md`
- Next step: Technical Design / Database Schema (lihat entri berikutnya), lalu mulai Fase 1 (Master Data) sesuai PRD Bab 17.

---
