# Panduan Teknis & Arsitektur Sistem CKPN (PSAK 414)

**Versi:** 1.0  
**Tanggal:** 2026-08-21  
**Platform:** Laravel 13 + Filament 4 + MySQL 8  
**URL Aplikasi:** `http://localhost/CKPN_FINAL/public` atau `http://localhost:8000`  
**Panel Admin:** `/admin`

---

## Daftar Isi

1. [Gambaran Umum](#1-gambaran-umum)
2. [Persyaratan Sistem](#2-persyaratan-sistem)
3. [Instalasi & Konfigurasi](#3-instalasi--konfigurasi)
4. [Arsitektur Sistem](#4-arsitektur-sistem)
5. [Alur Kerja Lengkap](#5-alur-kerja-lengkap)
6. [Panduan Menu per Cluster](#6-panduan-menu-per-cluster)
7. [Manajemen Upload Data](#7-manajemen-upload-data)
8. [Kalkulasi CKPN Step-by-Step](#8-kalkulasi-ckpn-step-by-step)
9. [Parameter Kalkulasi](#9-parameter-kalkulasi)
10. [Role & Hak Akses](#10-role--hak-akses)
11. [Struktur Database](#11-struktur-database)
12. [Queue & Job](#12-queue--job)
13. [Troubleshooting](#13-troubleshooting)

---

## 1. Gambaran Umum

Sistem CKPN (Cadangan Kerugian Penurunan Nilai) adalah aplikasi berbasis web untuk menghitung cadangan kerugian pembiayaan sesuai standar PSAK 71 / IFRS 9. Sistem ini mengelola dua jalur perhitungan utama:

- **CKPN Individual** — untuk debitur NPF (Non-Performing Financing) dengan outstanding terbesar (Top-N)
- **CKPN Kolektif** — untuk seluruh debitur di luar kriteria individual, menggunakan metode PD × LGD × EAD

### Komponen Utama

```
Data Historis Pembiayaan
        ↓
Penetapan Periode (Baseline)
        ↓
Klasifikasi Data (Individual / Kolektif)
        ↓                    ↓
Hitung CKPN Individual   Hitung PD (Netflow / Migration)
                              ↓
                         Hitung LGD (ER + CS)
                              ↓
                         Hitung CKPN Kolektif Final
        ↓                    ↓
         Dashboard & Laporan CKPN
```

---

## 2. Persyaratan Sistem

| Komponen | Versi Minimum            |
| -------- | ------------------------ |
| PHP      | 8.3+                     |
| MySQL    | 8.0+                     |
| Composer | 2.x                      |
| Node.js  | 18+ (untuk build assets) |
| Laravel  | 13.x                     |
| Filament | 4.x                      |

### PHP Extensions yang Dibutuhkan

- `pdo_mysql`
- `mbstring`
- `openssl`
- `tokenizer`
- `xml`
- `bcmath`
- `gd` (untuk export laporan)

---

## 3. Instalasi & Konfigurasi

### 3.1 Clone & Install Dependency

```bash
git clone <repo-url> CKPN_FINAL
cd CKPN_FINAL

composer install
npm install && npm run build
```

### 3.2 Konfigurasi Environment

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` sesuai environment:

```env
APP_NAME="Sistem CKPN"
APP_URL=http://localhost/CKPN_FINAL/public
APP_TIMEZONE=Asia/Jakarta
APP_LOCALE=id

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ckpn_db
DB_USERNAME=root
DB_PASSWORD=

QUEUE_CONNECTION=sync     # Ganti ke 'database' atau 'redis' untuk production
```

> **Catatan:** Gunakan `QUEUE_CONNECTION=sync` untuk development. Untuk production, gunakan `database` atau `redis` dan jalankan queue worker.

### 3.3 Migrasi & Seeding

```bash
php artisan migrate
php artisan db:seed
```

Seeder yang tersedia:

- `CalculationParameterSeeder` — parameter default kalkulasi
- `RolesAndPermissionsSeeder` — role dan permission awal

### 3.4 Buat User Admin Pertama

```bash
php artisan make:filament-user
```

Atau lewat tinker:

```bash
php artisan tinker
> App\Models\User::create(['name'=>'Admin','email'=>'admin@ckpn.id','password'=>bcrypt('password')])
```

### 3.5 Jalankan Queue Worker (Production)

```bash
php artisan queue:work --queue=ckpn-calculation --sleep=3 --tries=3
```

---

## 4. Arsitektur Sistem

### 4.1 Struktur Direktori Utama

```
app/
├── Domain/Ckpn/           # Logika kalkulasi domain
│   ├── Pd/
│   │   ├── Netflow/       # PdNetflowCalculator
│   │   └── Migration/     # PdMigrationCalculator
│   ├── Lgd/
│   │   ├── ExpectedRecoveries/
│   │   └── CollateralShortfall/
│   ├── Individual/        # CkpnIndividualCalculator
│   └── Collective/        # CkpnCollectiveCalculator
├── Filament/Clusters/     # UI panel per domain
├── Jobs/                  # Queue jobs (async processing)
├── Models/                # Eloquent models
├── Enums/                 # PHP native enums
├── Policies/              # Authorization per model
└── Services/              # Helper lintas domain
```

### 4.2 Pola Design

| Pola                | Penerapan                                                           |
| ------------------- | ------------------------------------------------------------------- |
| Strategy Pattern    | PD (Netflow vs Migration) dan LGD (ER vs CS) dipilih secara dinamis |
| Repository/Snapshot | Hasil kalkulasi disimpan insert-only, immutable per periode         |
| Queue Job           | Semua proses batch berjalan async, tidak blocking HTTP request      |
| RBAC                | Filament Shield + Spatie Permission, per Resource/Cluster           |

---

## 5. Alur Kerja Lengkap

### Tahap 1 — Persiapan Data

Sebelum menghitung CKPN, pastikan data berikut sudah terupload:

| Data                | Menu                              | Format       |
| ------------------- | --------------------------------- | ------------ |
| Master Pembiayaan   | Data Pembiayaan → Upload Master   | Excel/CSV    |
| Historis Pembiayaan | Data Pembiayaan → Upload Historis | Excel/CSV    |
| Data Jaminan        | Data Pembiayaan → Upload Jaminan  | Excel/CSV    |
| Jenis Jaminan       | Master Data → Jenis Jaminan       | Manual/Excel |
| Data Recovery       | Upload Data → (sesuai template)   | Excel/CSV    |
| Data Write-off      | Upload Data → (sesuai template)   | Excel/CSV    |

### Tahap 2 — Penetapan Periode

1. Buka **CKPN → Penetapan Periode**
2. Klik **Tambah Periode**
3. Input periode baseline dalam format `yyyymm` (contoh: `202412`)
4. Klik **Simpan**

Sistem otomatis akan:

- Mengambil data historis pembiayaan sesuai periode (`financing_status = 'A'`, `writeoff_status <> 'W'`)
- Menyimpan daftar debitur ke tabel staging `ckpn_period_classifications`

### Tahap 3 — Klasifikasi Data

1. Buka **CKPN → Klasifikasi Data**
2. Klik tombol **Klasifikasi Data** di header tabel
3. Pilih periode yang akan diklasifikasi
4. Klik **Jalankan Klasifikasi**

Kriteria klasifikasi:

| Jenis          | Kriteria                                                                                                                                   |
| -------------- | ------------------------------------------------------------------------------------------------------------------------------------------ |
| **Individual** | Aktif + Bukan Write-off + Kolektibilitas ≥ `npl_min_collectibility` + Masuk Top-N `ckpn_individual_top_n_outstanding` Outstanding Terbesar |
| **Kolektif**   | Semua akun di luar kriteria Individual                                                                                                     |

> Parameter `npl_min_collectibility` dan `ckpn_individual_top_n_outstanding` dapat dikonfigurasi di **Master Data → Parameter Kalkulasi**.

Untuk mengulang klasifikasi: klik **Reset Klasifikasi**, pilih periode, lalu jalankan kembali.

### Tahap 4 — Hitung CKPN Individual

1. Buka **CKPN → Hasil CKPN Individual**
2. Klik tombol **Hitung CKPN Individual**
3. Pilih periode dan segmen risiko
4. Klik **Jalankan**

Formula yang digunakan:

```
CKPN Individual = Baki Debet
                  - Total Nilai Likuidasi Jaminan
                  - (Total Nilai Likuidasi Jaminan × Biaya Penjualan)
```

### Tahap 5 — Hitung PD (Probability of Default)

User memilih salah satu metode PD:

#### Metode A: PD Netflow

1. Buka **PD Netflow → Hasil PD Netflow**
2. Klik **Hitung PD Netflow**
3. Pilih periode dan segmen
4. Sistem menghitung pergerakan bucket (bucket movement) dan compound rate

#### Metode B: PD Migration

1. Buka **PD Migration → Hasil PD Migration**
2. Klik **Hitung PD Migration**
3. Pilih periode, segmen, dan rentang rolling window
4. Sistem membangun migration matrix antar bucket

### Tahap 6 — Hitung LGD (Loss Given Default)

Hitung kedua komponen LGD:

#### LGD Expected Recoveries (LGD-ER)

1. Buka **LGD → Hasil LGD Expected Recoveries**
2. Klik **Hitung LGD-ER**
3. Pilih periode dan segmen
4. Sistem menghitung berdasarkan data recovery historis

#### LGD Collateral Shortfall (LGD-CS)

1. Buka **LGD → Hasil LGD Collateral Shortfall**
2. Klik **Hitung LGD-CS**
3. Pilih periode dan segmen
4. Sistem menghitung berdasarkan data nilai jaminan vs outstanding

### Tahap 7 — Hitung CKPN Kolektif Final

1. Buka **CKPN → Hasil CKPN Kolektif**
2. Klik **Hitung CKPN Kolektif**
3. Pilih:
    - Periode baseline
    - Segmen risiko
    - Metode PD yang digunakan (Netflow atau Migration)
4. Klik **Jalankan**

Formula:

```
CKPN Kolektif = EAD × PD × LGD

di mana:
  EAD = Outstanding Balance
  PD  = Rate dari metode yang dipilih (Netflow atau Migration)
  LGD = Kombinasi LGD-ER dan LGD-CS (sesuai kebijakan)
```

### Tahap 8 — Review & Approval

1. Buka **CKPN → Penetapan Periode**
2. Periode yang sudah selesai dihitung dapat ditandai **Selesai**
3. Approver dapat mengubah status ke **Disetujui**
4. Hasil final tampil di **Reporting → Ringkasan CKPN**

---

## 6. Panduan Menu per Cluster

### Master Data

| Menu                | Fungsi                                                      |
| ------------------- | ----------------------------------------------------------- |
| Segmen Risiko       | Kelola segmentasi pembiayaan untuk kalkulasi kolektif       |
| Bucket              | Definisi bucket hari tunggakan (mis. 1–30 hari, 31–60 hari) |
| Quality Grade       | Grade kualitas data untuk flagging anomali                  |
| Jenis Jaminan       | Tipe agunan + discount rate likuidasi                       |
| Kantor Pembiayaan   | Master cabang/kantor                                        |
| Parameter Kalkulasi | Semua parameter numerik kalkulasi (lihat Bab 9)             |

### Data Pembiayaan

| Menu            | Fungsi                                                |
| --------------- | ----------------------------------------------------- |
| Daftar Rekening | Lihat seluruh data master rekening pembiayaan         |
| Upload Master   | Upload data master rekening (format: sesuai template) |
| Upload Historis | Upload data historis bulanan per rekening             |
| Daftar Historis | Lihat data historis yang sudah terupload              |
| Daftar Jaminan  | Lihat data agunan per rekening                        |
| Upload Jaminan  | Upload data agunan (format: sesuai template)          |

### Upload Data

| Menu           | Fungsi                                                 |
| -------------- | ------------------------------------------------------ |
| Upload Data    | Upload berbagai jenis data (recovery, write-off, dsb.) |
| Template Query | Kelola template SQL untuk ekstraksi data custom        |

### CKPN

| Menu                  | Fungsi                                           |
| --------------------- | ------------------------------------------------ |
| Penetapan Periode     | Input periode baseline, trigger populate debitur |
| Klasifikasi Data      | Lihat & jalankan klasifikasi individual/kolektif |
| Hasil CKPN Individual | Lihat hasil + trigger hitung CKPN Individual     |
| Hasil CKPN Kolektif   | Lihat hasil + trigger hitung CKPN Kolektif       |

### PD Netflow / PD Migration

| Menu               | Fungsi                                  |
| ------------------ | --------------------------------------- |
| Hasil PD Netflow   | Lihat hasil rate PD Netflow per segmen  |
| Hasil PD Migration | Lihat hasil migration matrix per segmen |

### LGD

| Menu         | Fungsi                                         |
| ------------ | ---------------------------------------------- |
| Hasil LGD ER | Lihat rate LGD Expected Recoveries per segmen  |
| Hasil LGD CS | Lihat rate LGD Collateral Shortfall per segmen |

### Reporting

| Menu                 | Fungsi                                              |
| -------------------- | --------------------------------------------------- |
| Ringkasan CKPN       | Dashboard agregat hasil CKPN per periode            |
| Ringkasan LGD        | Ringkasan rate LGD per segmen                       |
| Anomali Data Quality | Daftar flag anomali data yang perlu ditindaklanjuti |

### Administrasi

| Menu          | Fungsi                                                       |
| ------------- | ------------------------------------------------------------ |
| Pengguna      | CRUD user dan assign role                                    |
| Log Kalkulasi | Audit trail semua proses kalkulasi (periode, status, durasi) |

---

## 7. Manajemen Upload Data

### 7.1 Format File

Semua upload menggunakan format **Excel (.xlsx)** atau **CSV**. Template tersedia di masing-masing halaman upload.

### 7.2 Kolom Wajib Upload Master Pembiayaan

| Kolom          | Keterangan                                                |
| -------------- | --------------------------------------------------------- |
| `nokont`       | Nomor rekening                                            |
| `nama`         | Nama nasabah                                              |
| `stsrec`       | Status rekening (`A` = Aktif)                             |
| `stsacc`       | Status hapus buku (`W` = Write-off)                       |
| `usage_type`   | Jenis penggunaan (1=Modal Kerja, 2=Investasi, 3=Konsumsi) |
| `segment_code` | Kode segmen risiko                                        |

### 7.3 Kolom Wajib Upload Historis Pembiayaan

| Kolom     | Keterangan                       |
| --------- | -------------------------------- |
| `nokont`  | Nomor rekening                   |
| `period`  | Periode data format `yyyymm`     |
| `bkdeb`   | Baki debet (outstanding balance) |
| `kolekt`  | Kolektibilitas (1–5)             |
| `tgkhari` | Hari tunggakan                   |
| `stsrec`  | Status rekening                  |
| `stsacc`  | Status hapus buku                |

### 7.4 Monitor Status Upload

Buka **Data Pembiayaan → Upload Historis** untuk melihat:

- Status batch: `pending` → `processing` → `completed` / `failed`
- Jumlah baris berhasil dan dilewati (`skipped_rows`)
- Log progres proses

---

## 8. Kalkulasi CKPN Step-by-Step

### 8.1 Urutan yang Benar

```
[1] Upload semua data
[2] Isi Master Data (segmen, bucket, parameter)
[3] Penetapan Periode
[4] Klasifikasi Data
[5] Hitung CKPN Individual (paralel dengan langkah 6–7)
[6] Hitung PD (pilih Netflow ATAU Migration)
[7] Hitung LGD-ER + LGD-CS
[8] Hitung CKPN Kolektif (gunakan hasil PD + LGD dari langkah 6–7)
[9] Review di Reporting
[10] Approve periode
```

### 8.2 Dependency Antar Langkah

```
Penetapan Periode → Klasifikasi Data → CKPN Individual
                                    → PD Netflow ──┐
                                    → PD Migration ─┼→ CKPN Kolektif
                                    → LGD-ER ───────┤
                                    → LGD-CS ───────┘
```

Langkah 5 (Individual) dan 6–7 (PD + LGD) dapat dijalankan **paralel** setelah klasifikasi selesai.

### 8.3 Cek Status Perhitungan

Buka **Administrasi → Log Kalkulasi** untuk memantau:

- Status setiap job: `processing` / `completed` / `failed`
- Waktu mulai dan selesai
- Pesan error jika gagal

---

## 9. Parameter Kalkulasi

Semua parameter dikelola di **Master Data → Parameter Kalkulasi**. Nilai dapat diubah per segmen risiko atau global (tanpa segmen = berlaku untuk semua).

### Parameter Global (Tanpa Segmen)

| Parameter Key                       | Nilai Default | Keterangan                                        |
| ----------------------------------- | ------------- | ------------------------------------------------- |
| `npl_min_collectibility`            | 3             | Kolektibilitas minimum untuk NPL                  |
| `ckpn_individual_top_n_outstanding` | 20            | Jumlah debitur top-N untuk Individual             |
| `rolling_window_months`             | 12            | Panjang rolling window perhitungan PD/LGD (bulan) |

### Parameter per Segmen

| Parameter Key                       | Keterangan                                        |
| ----------------------------------- | ------------------------------------------------- |
| `ckpn_individual_selling_cost_rate` | Biaya penjualan jaminan (%) untuk CKPN Individual |
| `lgd_er_recovery_period`            | Periode recovery untuk LGD-ER (bulan)             |
| `lgd_cs_discount_rate`              | Discount rate untuk LGD-CS                        |

> Untuk menambah parameter baru: isi `parameter_key`, `parameter_value`, pilih segmen (opsional), dan `effective_date`.

---

## 10. Role & Hak Akses

### Role Tersedia

| Role           | Akses                                              |
| -------------- | -------------------------------------------------- |
| `super_admin`  | Akses penuh semua fitur                            |
| `admin`        | Kelola user, parameter, master data                |
| `risk_analyst` | Upload data, jalankan kalkulasi, lihat semua hasil |
| `approver`     | Approve periode CKPN (tidak bisa ubah data)        |
| `viewer`       | Lihat laporan dan hasil kalkulasi (read-only)      |

### Konfigurasi Role

1. Buka **Administrasi → Pengguna**
2. Edit user → pilih role
3. Atau gunakan Filament Shield di `/admin/shield` untuk konfigurasi permission granular per Resource

---

## 11. Struktur Database

### Tabel Utama

| Tabel                       | Fungsi                                                     |
| --------------------------- | ---------------------------------------------------------- |
| `financing_accounts`        | Master data rekening pembiayaan                            |
| `financing_account_periods` | Historis data per periode (ex: `financing_period_uploads`) |
| `financing_upload_batches`  | Log batch upload                                           |
| `collaterals`               | Data agunan per rekening                                   |
| `collateral_types`          | Jenis agunan + discount rate                               |
| `writeoff_data`             | Data hapus buku historis                                   |
| `recoveries_data`           | Data recovery historis                                     |

### Tabel Master/Referensi

| Tabel                    | Fungsi                         |
| ------------------------ | ------------------------------ |
| `risk_segments`          | Segmen risiko                  |
| `buckets`                | Definisi bucket hari tunggakan |
| `calculation_parameters` | Parameter kalkulasi            |
| `quality_grades`         | Grade kualitas data            |
| `financing_offices`      | Master kantor/cabang           |

### Tabel CKPN Process

| Tabel                         | Fungsi                                         |
| ----------------------------- | ---------------------------------------------- |
| `ckpn_periods`                | Daftar periode yang ditetapkan untuk kalkulasi |
| `ckpn_period_classifications` | Staging + klasifikasi debitur per periode      |

### Tabel Hasil Kalkulasi (Insert-Only / Snapshot)

| Tabel                              | Isi                                     |
| ---------------------------------- | --------------------------------------- |
| `pd_netflow_bucket_movement`       | Pergerakan debitur antar bucket         |
| `pd_netflow_compound_rate`         | Rate PD Netflow final per bucket/segmen |
| `pd_netflow_results`               | Hasil PD Netflow per periode/segmen     |
| `pd_migration_matrix`              | Matrix transisi antar kolektibilitas    |
| `pd_migration_results`             | Hasil PD Migration per periode/segmen   |
| `lgd_expected_recoveries_results`  | Rate LGD-ER per segmen                  |
| `lgd_collateral_shortfall_results` | Rate LGD-CS per segmen                  |
| `ckpn_individual_results`          | Hasil CKPN Individual per debitur       |
| `ckpn_collective_results`          | Hasil CKPN Kolektif per segmen          |

> Tabel hasil bersifat **immutable per periode**. Update tidak diperbolehkan pada periode berstatus `completed` atau `approved`.

---

## 12. Queue & Job

### Daftar Job & Trigger-nya

| Job                            | Trigger                         | Queue              |
| ------------------------------ | ------------------------------- | ------------------ |
| `PopulatePeriodDebtorsJob`     | Saat periode baru disimpan      | `ckpn-calculation` |
| `ClassifyPeriodDataJob`        | Tombol "Klasifikasi Data"       | `ckpn-calculation` |
| `CkpnIndividualCalculationJob` | Tombol "Hitung CKPN Individual" | `ckpn-calculation` |
| `PdNetflowCalculationJob`      | Tombol "Hitung PD Netflow"      | `ckpn-calculation` |
| `PdMigrationCalculationJob`    | Tombol "Hitung PD Migration"    | `ckpn-calculation` |
| `LgdErCalculationJob`          | Tombol "Hitung LGD-ER"          | `ckpn-calculation` |
| `LgdCsCalculationJob`          | Tombol "Hitung LGD-CS"          | `ckpn-calculation` |
| `CkpnCollectiveCalculationJob` | Tombol "Hitung CKPN Kolektif"   | `ckpn-calculation` |
| `ProcessFinancingUploadJob`    | Setelah file upload disimpan    | `default`          |

### Menjalankan Queue Worker

```bash
# Development (proses satu job per kali)
php artisan queue:work --queue=ckpn-calculation

# Production (daemon, restart otomatis)
php artisan queue:work --queue=ckpn-calculation,default --sleep=3 --tries=3 --max-time=3600
```

### Monitor Queue

```bash
# Lihat failed jobs
php artisan queue:failed

# Retry failed job
php artisan queue:retry <job-id>

# Retry semua
php artisan queue:retry all
```

---

## 13. Troubleshooting

### Job tidak berjalan / data tidak muncul

**Penyebab:** `QUEUE_CONNECTION` bukan `sync` dan queue worker tidak berjalan.

**Solusi:**

```bash
# Opsi 1: Ubah ke sync (development)
# Edit .env: QUEUE_CONNECTION=sync

# Opsi 2: Jalankan worker
php artisan queue:work --queue=ckpn-calculation
```

### Error "Route not defined"

Jalankan:

```bash
php artisan route:clear
php artisan route:cache
```

### Error Carbon "Could not parse '-'"

Data historis memiliki nilai tanggal berupa `-`. Pastikan kolom tanggal di model menggunakan cast `'string'` bukan `'date'`. Model yang sudah diperbaiki: `FinancingAccountPeriod`, `FinancingAccount`, `Collateral`.

### Klasifikasi menghasilkan jumlah individual yang tidak sesuai

Periksa parameter:

- `ckpn_individual_top_n_outstanding` — harus sesuai jumlah yang diinginkan
- `npl_min_collectibility` — threshold kolektibilitas NPL

Lalu **Reset Klasifikasi** dan jalankan ulang.

### Upload gagal / baris dilewati

Buka **Data Pembiayaan → Upload Historis**, klik batch yang gagal, lihat kolom `progress_log` untuk detail baris yang dilewati dan alasannya.

### Cache setelah perubahan config

```bash
php artisan config:clear
php artisan cache:clear
php artisan view:clear
```

---

## Lampiran — Perintah Artisan yang Sering Digunakan

```bash
# Migrasi ulang (HATI-HATI: menghapus semua data)
php artisan migrate:fresh --seed

# Jalankan test
php artisan test --filter=NamaTest

# Format kode (PSR-12)
./vendor/bin/pint

# Generate user Filament
php artisan make:filament-user

# Sync permissions Shield
php artisan shield:generate --all
```

---

_Dokumen ini dibuat otomatis berdasarkan state codebase per 2026-08-21. Update dokumen ini setiap ada perubahan signifikan pada alur kerja atau struktur sistem._
