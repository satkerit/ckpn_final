# Sistem CKPN (PSAK 414)

Sistem perhitungan CKPN berbasis PSAK 414 untuk lembaga keuangan syariah. Dibangun di atas Laravel 13 + Filament 4 dengan pendekatan modular berbasis domain.

---

## Tech Stack

| Komponen | Versi |
|---|---|
| PHP | ^8.3 |
| Laravel | ^13.0 |
| Filament | 4.12 |
| Filament Shield | ^4.3 |
| Maatwebsite Excel | ^4.0 |
| Database | MySQL 8.x |
| Testing | Pest ^4.7 |
| Queue | Database/Redis |

---

## Prasyarat

- PHP 8.3+
- MySQL 8.x
- Composer
- Node.js + npm

---

## Setup Awal

```bash
# Clone & install
composer install
npm install

# Environment
cp .env.example .env
php artisan key:generate

# Database
php artisan migrate --seed

# Build assets
npm run build
```

Atau gunakan script setup otomatis:

```bash
composer run setup
```

---

## Menjalankan Aplikasi

```bash
# Development (server + queue + vite + log watcher sekaligus)
composer run dev
```

Akses panel admin di `http://localhost:8000/admin`

---

## Akun Default

Setelah `migrate --seed`, akun super admin dibuat via `AdminUserSeeder`. Lihat file `.env` untuk konfigurasi kredensial default.

---

## Struktur Modul (Filament Clusters)

| Cluster | Deskripsi |
|---|---|
| **Upload Data** | Upload & kelola semua tipe data input (master, historis, jaminan, kantor, jenis jaminan) |
| **Query Wizard** | Template query SQL Server untuk ekstrak data ke MySQL |
| **Master Data** | Bucket, parameter kalkulasi, segmen risiko, grade kualitas, jenis jaminan, kantor |
| **Data Pembiayaan** | Data rekening pembiayaan, agunan, historis periode |
| **PD Netflow** | Hasil perhitungan PD metode Netflow + log kalkulasi |
| **PD Migration** | Hasil perhitungan PD metode Migration Matrix |
| **LGD** | Hasil LGD Expected Recoveries + LGD Collateral Shortfall |
| **CKPN** | Hasil CKPN Individual & Kolektif + manajemen periode |
| **Reporting** | Laporan dan rekap hasil CKPN |
| **Administrasi** | Manajemen user, role, permission (Shield) |

---

## Struktur Domain

```
app/
  Domain/
    Ckpn/
      Pd/
        Netflow/        PdNetflowCalculator
        Migration/      PdMigrationCalculator
        Contracts/      PdCalculationMethodInterface
      Lgd/
        ExpectedRecoveries/   LgdExpectedRecoveriesCalculator
        CollateralShortfall/  LgdCollateralShortfallCalculator
        Contracts/            LgdCalculationMethodInterface
      Individual/       CkpnIndividualCalculator
      Collective/       CkpnCollectiveCalculator
      Services/         SnapshotWriter, RollingWindowResolver, BucketingService
  Jobs/                 Job batch per perhitungan (idempotent, queue-able)
  Models/               Eloquent models (1 model = 1 tabel)
  Policies/             Policy per model (konvensi Shield)
  Enums/                Bucket, QualityGrade, RunStatus, UploadBatchStatus, dll.
```

---

## Role & Permission

| Role | Akses |
|---|---|
| `super_admin` | Semua permission (261 permission) |
| `risk_analyst` | CRUD master data + input + upload + trigger kalkulasi (94 permission) |
| `approver` | Read-only semua + approve/reject CkpnPeriod (39 permission) |
| `viewer` | ViewAny + View semua resource (38 permission) |

Permission mengikuti konvensi Shield: `Action:ModelName` — misal `Update:CkpnPeriod`, `Create:QueryTemplate`.

---

## Fitur Upload Data

Upload data via file Excel (.xlsx) dengan 5 tipe:

| Tipe | Deskripsi | Tabel Tujuan |
|---|---|---|
| `master` | Master rekening pembiayaan | `financing_accounts` |
| `collateral` | Data jaminan/agunan | `collaterals` |
| `period` | Historis pembiayaan bulanan | `financing_period_uploads` |
| `office` | Master kantor/cabang | `financing_offices` |
| `collateral_type` | Master jenis jaminan | `collateral_types` |

Setiap tipe memiliki:
- Template Excel yang dapat diunduh
- Job asinkron via queue (`ckpn-calculation`)
- Validasi dan error reporting per baris

---

## Query Wizard

Template query SQL Server siap pakai untuk ekstrak data ke MySQL. Di-seed dengan 8 query default mencakup semua tipe data. Mendukung parameter dinamis (`:period`, `:branch_code`, dll.).

---

## Queue Worker

Job perhitungan dan upload berjalan di background via queue:

```bash
php artisan queue:work --queue=ckpn-calculation
```

---

## Testing

```bash
# Run semua test
php artisan test

# Run test spesifik
php artisan test --filter=NamaTest
```

Test coverage:
- Unit: formula kalkulasi (PD Netflow, PD Migration, LGD ER, LGD CS)
- Feature: alur upload, trigger kalkulasi, snapshot hasil

---

## Format & Linting

```bash
./vendor/bin/pint
```

---

## Dokumentasi

| Dokumen | Deskripsi |
|---|---|
| `PRD.md` | Spesifikasi bisnis lengkap (rumus, domain, data model) |
| `AGENTS.md` | Panduan kerja AI coding agent |
| `PROGRESS.md` | Log perubahan dan status pengerjaan |

---

## Lisensi

Proprietary — digunakan secara internal.
