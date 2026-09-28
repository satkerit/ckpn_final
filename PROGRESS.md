## [2026-09-28] Fix Komprehensif Progress Bar Tidak Bergerak / Tidak Muncul

- Status: Done
- Modul: Upload Data — Progress Tracking (session lock, cache init, interval, UI, API fallback)
- Ref PRD: Bab 3
- Perubahan (5 akar masalah):
  1. **Session lock** (`SESSION_DRIVER=database`): request polling `/api/upload-progress/{id}` (middleware `auth`) mengantre di belakang request `wire.executeUpload()` yang memegang lock session → progress macet. Fix: `session()->save()` + `session_write_close()` sebelum proses berat di `UploadIndex::executeUpload()`.
  2. `initializeProgress()` dipanggil SETELAH pre-scan `countTotalRows()` → cache progress belum ada saat polling awal → API 404 terus-menerus. Fix: init SEBELUM pre-scan + `setTotalSteps($totalRows)` (method baru di `HasProgressTracking`) setelah pre-scan.
  3. Modulo tetap `% 250` / `% 500` tidak pernah true untuk file kecil → progress 0% sampai selesai. Fix: `$progressInterval = max(10, min(100, ceil($totalRows / 20)))` (properti baru) dipakai di 5 pipeline, plus `elseif` update progress untuk `financing_office` & `collateral_type` yang sebelumnya hanya update saat buffer flush.
  4. Dialog SweetAlert2 tidak punya angka persen & elemen bisa null saat render async. Fix: tambah `#progress-percentage`, simpan `progressState` + `applyProgress()` + `didOpen` di `resources/js/app.js`, dan `npm run build`.
  5. API 404 tanpa fallback saat cache miss. Fix: `UploadProgressController::buildProgressFromBatch()` menghitung persentase dari `processed_rows/total_rows` batch record.
- File: `app/Traits/HasProgressTracking.php`, `app/Services/UploadProcessorService.php`, `app/Livewire/UploadData/UploadIndex.php`, `app/Http/Controllers/Api/UploadProgressController.php`, `resources/js/app.js`, `public/build/*`
- Verifikasi: pint PASS (330 files); `php -l` bersih; `npm run build` sukses; `view:clear` OK.

## [2026-09-28] Fix Progress Bar — Persentase Tidak Bergerak

- Status: Done
- Modul: Upload Data — UploadProcessorService + HasProgressTracking
- Perubahan:
  - Root cause: `initializeProgress((string) $batchId)` dipanggil tanpa `$totalRows`, sehingga `$this->totalSteps = 0` selamanya dan persentase selalu 0.
  - Fix: di `process()`, scan cepat total baris via `countTotalRows()` (sudah ada di `StreamableExcelUpload`) sebelum `initializeProgress`. `initializeProgress((string) $batchId, $totalRows)` kini menerima total baris yang benar.
  - Efek: `updateBatchProgress()` → `setProgress($processedRows)` → `percentage = processedRows/totalRows * 100` kini menghasilkan nilai yang akurat.
- File: `app/Services/UploadProcessorService.php`

## [2026-09-28] Fix Upload Jaminan — Dedup Benar & Fallback Noreg

- Status: Done
- Modul: Upload Data — processCollateral
- Perubahan:
  - Tambah in-memory dedup dengan kunci `(financing_account_id|collateral_code|sequence_number)` — satu akun **boleh** punya banyak jaminan dengan noreg yang sama asalkan no urut berbeda.
  - Perbaiki fallback `collateral_code` kosong: dari `JMN-{account_number}` (selalu sama per akun) menjadi `JMN-{account_number}-{sequence_number}` agar setiap jaminan tetap unik.
  - Tambah alias heading `noreg` untuk kolom `collateral_code`.
  - `duplicateRows` dilaporkan di `error_summary` dan `progress_log.skipped_duplicates`.
- File: `app/Services/UploadProcessorService.php`

## [2026-09-28] Fix Fungsi Lihat Detail Error Upload Batch

- Status: Done
- Modul: Upload Data — UploadBatchIndex (Riwayat Upload)
- Ref PRD: Bab 3
- Perubahan:
  - **UploadProcessorService**: normalisasi format `error_summary` agar semua tipe upload menyimpan error sebagai `{row, field, error, value}` — sebelumnya `flushOfficeBuffer`, `flushCollateralTypeBuffer`, `processCollateralType`, dan `processCollateral` menyimpan string biasa yang tidak bisa dibaca modal Alpine.
  - **upload-batch-index.blade.php**: hapus `x-data=""` nested di tombol gagal (reliable issue di Alpine v3), ganti `json_encode` ke `Js::from()` untuk HTML escaping yang benar.
  - **Modal template**: tambah branch `x-if` untuk handle format string sebagai fallback (backward compat data lama di DB).
- File utama: `app/Services/UploadProcessorService.php`, `resources/views/livewire/upload-data/upload-batch-index.blade.php`

## [2026-09-28] Perbaikan Klik Detail Error pada Riwayat Upload

- Status: Done
- Modul: Upload Data — Riwayat Upload (UploadBatchIndex)
- Ref PRD: Bab 3, Bab 7.3
- Perubahan:
  - Mengubah modal detail error dari berbasis Alpine dispatch (`$dispatch('show-error-details')` + script JS inline) menjadi modal server-rendered Livewire murni (`$showErrorModal`, `$selectedBatchId`, `openErrorModal()`, `closeErrorModal()`). Hal ini mengatasi:
    1. Race condition JS function pada navigasi `wire:navigate` (script tidak selalu dijalankan ulang).
    2. Kerusakan escaping HTML saat batch memiliki ribuan entri error di atribut `@click`.
  - Tombol klik detail error kini aktif tidak hanya saat `failed_rows > 0`, melainkan untuk seluruh batch yang memiliki `error_summary` atau berstatus `Failed` (ditambah tombol "Lihat detail" langsung pada badge status Gagal).
  - Menambahkan method `hasErrorDetails()` pada model `FinancingUploadBatch`.
  - Menambahkan CSS rule `[x-cloak] { display: none !important; }` pada `resources/css/app.css`.
  - Memperbaiki bug kolom pencarian `file_name` -> `filename` pada query Livewire.
- File utama: `app/Livewire/UploadData/UploadBatchIndex.php`, `resources/views/livewire/upload-data/upload-batch-index.blade.php`, `app/Models/FinancingUploadBatch.php`, `resources/css/app.css`
- Verifikasi: pint PASS; test suite PASS; `php artisan view:cache` PASS.

## [2026-09-28] Perbaikan Detail Pesan Error Upload

- Status: Done
- Modul: Upload Data — Error Reporting
- Ref PRD: Bab 3, Bab 7.3
- Perubahan:
  - `executeUpload()` kini menangkap pesan exception (`$e->getMessage()`) dan menampilkannya sebagai pesan utama, tidak lagi generik.
  - Error per baris/kolom dari `error_summary` diformat & ditampilkan langsung di kartu upload (maks 5 baris + sisa "dan N error lainnya"), sebelumnya hanya menyuruh buka halaman Riwayat.
  - `UploadProcessorService::markFailed()` menormalkan semua entri error ke struktur `{row, field, error}`; ditambah `normalizeError()` & `describeError()` (prefix "Baris N:" / "Kolom X:").
  - Blok `createReader()`/`countTotalRows()` dipindah ke dalam try/catch — file rusak/tidak terbaca sekarang menandai batch `Failed` + pesan jelas, tidak lagi nyangkut `Processing`.
- File utama: `app/Livewire/UploadData/UploadIndex.php`, `app/Services/UploadProcessorService.php`, `resources/views/livewire/upload-data/upload-index.blade.php`
- Verifikasi: pint PASS; `php artisan test tests/Feature/UploadProcessorServiceTest.php` → 3 passed; GetDiagnostics bersih.

## [2026-09-28] Hapus Method down() pada Seluruh Migration

- Status: Done
- Modul: Database Migrations (74 file)
- Ref PRD: —
- Perubahan: menghapus seluruh `public function down()` (beserta PHPDoc-nya) dari 74 file migration agar migrasi satu arah (rollback tidak digunakan); 1 import `Blueprint` orphan ikut dibersihkan.
- File utama: `database/migrations/*.php`
- Verifikasi: `pint database/migrations` PASS (74 files); `php artisan migrate:status` OK.

## [2026-09-28] Rebuild Fitur Upload Data — Tanpa Queue + Dialog Progress Bar

- Status: Done
- Modul: Upload Data Pembiayaan (semua 5 tipe) — Logika, Service, UI
- Ref PRD: Bab 3 (Upload Data), Bab 7.3 (data quality)
- Perubahan:
  - **Hapus arsitektur queue**: 5 job upload (`ProcessFinancingPeriodUploadJob`, `ProcessCollateralUploadJob`, `ProcessFinancingMasterUploadJob`, `ProcessFinancingOfficeUploadJob`, `ProcessCollateralTypeUploadJob`) + `UploadJobBase` dihapus. Hosting user tanpa terminal tidak bisa jalankan `queue:work`.
  - **UploadProcessorService** (sinkron, streaming OpenSpout, chunk bulk `upsert`/`insert`): memori konstan, `set_time_limit(1800)` + `max_execution_time`/`memory_limit` override.
  - **Dedup ketat histori pembiayaan**: 2 lapis — in-memory `seenFileKeys` (duplikat dalam file) + cek DB existing per chunk. Nokontrak sama pada periode sama dilewati & dilaporkan di `error_summary`/`progress_log.skipped_duplicates`.
  - **Pola dua tahap tanpa queue**: `processUpload()` (cepat: validasi + simpan file + buat batch + dispatch event `start-upload-progress`) → JS buka dialog progress, polling `/api/upload-progress/{batchId}`, lalu panggil `executeUpload($batchId)` tanpa await (background HTTP request) → UI tetap responsif.
  - **Keamanan**: path file dibaca server-side dari kolom baru `financing_upload_batches.file_path` (bukan dari client) + `abort_unless` ownership check. Route progress API turun dari `auth:sanctum` ke `auth` (polling pakai session cookie web).
  - **Dialog progress bar** (SweetAlert2 `window.showUploadProgress`/`updateProgress`) via Alpine listener `start-upload-progress.window` & `upload-finished.window` di `upload-index.blade.php`.
  - **LOKASI FILE**: `LOAD DATA LOCAL INFILE` dihapus (config tidak punya `PDO::MYSQL_ATTR_LOCAL_INFILE`) — diganti bulk `upsert`/`insert` per chunk.
- File utama: `app/Services/UploadProcessorService.php`, `app/Livewire/UploadData/UploadIndex.php`, `app/Traits/HasProgressTracking.php`, `resources/views/livewire/upload-data/upload-index.blade.php`, `app/Models/FinancingUploadBatch.php`, `database/migrations/2026_09_28_070048_add_file_path_to_financing_upload_batches_table.php`, `routes/web.php`, `database/factories/UserFactory.php`, `tests/Feature/UploadProcessorServiceTest.php`
- Verifikasi: `./vendor/bin/pint` PASS; `php artisan test tests/Feature/UploadProcessorServiceTest.php` → **3 passed (13 assertions)** (collateral upsert, idempotency, dedup histori nokontrak+periode).
- Catatan: `UserFactory::$password` static dihapus — cache hash lintas-test memicu `Could not verify the hashed value's configuration` (order-dependent).
- Next / risiko: satu request sinkron panjang bisa kena timeout shared hosting pada file sangat besar; pertimbangkan chunked resume bila muncul di produksi.

## [2026-09-27] Fase Perbaikan Hasil Audit Kode

- Status: Done
- Modul: Lintas Modul — Upload, Security, Dead Code, Snapshot Immutability
- Ref PRD: Bab 3, 10, 13.2 (FR-13), 16
- Perubahan:
  - **Bug kritis OpenSpout**: `StreamableExcelUpload` mengakses property private `Row::$cells` (fatal error) → diganti `Row::getCells()` di `extractHeadings()` & `streamRows()`.
  - **Deadlock/retry**: `retry_after` queue DB (1860s) dipastikan > `$timeout` job maksimum (1800s) agar job tidak di-retry sebelum selesai.
  - **Redundansi progress**: hapus panggilan `initializeProgress()` ganda di `ProcessFinancingPeriodUploadJob` & `ProcessFinancingMasterUploadJob` (sudah dipanggil `UploadJobBase::handle()`).
  - **Snapshot immutability**: `LgdCsResultIndex::rekalkulasi()` hanya menghapus snapshot hasil, log run lama dipertahankan sebagai history (konsisten `LgdFinalResultIndex`).
  - **Dead code dibersihkan**: `resources/js/upload-manager.js`, `resources/views/components/upload-progress-modal.blade.php`, `resources/views/test-dialogs.blade.php`, route dev `/test-dialogs`, `resources/views/welcome.blade.php`, `storage/dbg.log`. Referensi Vite input & import `app.js` ikut dibersihkan.
  - **Test suite** disinkronkan dengan schema & DTO terbaru (kolom `cs_total_shortfall`, konstruktor `CkpnIndividualCalculator`, otorisasi `actingAsSuperAdmin()`, dispatch async job upload).
  - Audit findings terdokumentasi di `docs/CODE_AUDIT.md`.
- File utama: `app/Traits/StreamableExcelUpload.php`, `app/Jobs/ProcessFinancingPeriodUploadJob.php`, `app/Jobs/ProcessFinancingMasterUploadJob.php`, `app/Livewire/Lgd/LgdCsResultIndex.php`, `routes/web.php`, `vite.config.js`, `resources/js/app.js`, `docs/CODE_AUDIT.md`
- Verifikasi: `./vendor/bin/pint` (4 file) + `php artisan test` → **82 passed (243 assertions)**, 0 failed; `npm run build` sukses.
- Next: tidak ada blocker.

## [2026-09-27] Implementasi OpenSpout untuk Upload Excel Hemat Memori

- Status: Done
- Modul: Upload Data — Semua Job Upload (Period, Collateral, Master, Office, CollateralType)
- Ref PRD: Bab 3 — Upload Data Pembiayaan
- Perubahan:
  - Install package OpenSpout v5.12.0 via Composer untuk streaming read Excel (.xlsx) tanpa load seluruh file ke memori.
  - Buat trait `StreamableExcelUpload` dengan method reusable: `createReader()`, `extractHeadings()`, `streamRows()`, `closeReader()`, `resolveColIndex()`, `parseDate()`, `parseDecimal()`, `parseInt()`, `parseString()`.
  - Refactor seluruh job upload ke OpenSpout streaming:
    - `ProcessFinancingPeriodUploadJob` — streaming + LOAD DATA LOCAL INFILE untuk insert super cepat (20-100x lebih cepat dari INSERT批量).
    - `ProcessCollateralUploadJob` — streaming + bulk upsert.
    - `ProcessFinancingMasterUploadJob` — streaming + bulk upsert.
    - `ProcessFinancingOfficeUploadJob` — streaming + bulk upsert.
    - `ProcessCollateralTypeUploadJob` — streaming + bulk upsert.
  - Ubah `UploadProcessorService` dari `dispatchSync()` ke `dispatch()` untuk queue async (tidak blocking HTTP request).
- Keuntungan:
  - Memory konstan ~30-50MB berapapun ukuran file (sebelumnya bisa 512MB+ untuk file besar).
  - Dapat memproses file ratusan ribu baris tanpa memory limit exceeded.
  - Performa baca 2-5x lebih cepat dari PhpSpreadsheet.
- File utama:
  - `app/Traits/StreamableExcelUpload.php`
  - `app/Jobs/ProcessFinancingPeriodUploadJob.php`
  - `app/Jobs/ProcessCollateralUploadJob.php`
  - `app/Jobs/ProcessFinancingMasterUploadJob.php`
  - `app/Jobs/ProcessFinancingOfficeUploadJob.php`
  - `app/Jobs/ProcessCollateralTypeUploadJob.php`
  - `app/Services/UploadProcessorService.php`
- Catatan penting:
  - Pastikan queue worker berjalan: `php artisan queue:work --queue=ckpn-calculation`
  - Import class lama (`FinancingMasterUploadImport`, dll.) masih ada tapi tidak dipakai — dapat dihapus jika tidak dibutuhkan.
- Next: tidak ada blocker.

## [2026-09-27] Tambah Kolom PPKA ke financing_account_periods

- Status: Done
- Modul: Data Pembiayaan — Historis Periode (`FinancingAccountPeriod`)
- Ref PRD: Bab 15
- Perubahan:
  - Migration: `add_ppka_to_financing_account_periods` (`DECIMAL(20,2) nullable`) — sudah dijalankan.
  - Model: kolom `ppka` sudah ada di `$fillable` & cast `decimal:2`.
  - Job: `ProcessFinancingPeriodUploadJob` — baca kolom `ppka` dari Excel (header `ppka`), simpan ke buffer & `LOAD DATA INFILE`.
  - Template Excel upload: `FinancingPeriodDataSheet` — tambah kolom `ppka` di heading & contoh data; `FinancingPeriodPetunjukSheet` — tambah baris petunjuk `ppka`.
  - Export: `FinancingPeriodExport` — tambah kolom `PPKA (Rp)` di query select, headings & map.
  - View: `financing-period-index.blade.php` — tambah kolom PPKA di tabel, colspan empty-state 10→11.
- File utama: `database/migrations/2026_09_27_152619_add_ppka_to_financing_account_periods.php`, `app/Models/FinancingAccountPeriod.php`, `app/Jobs/ProcessFinancingPeriodUploadJob.php`, `app/Exports/FinancingPeriodDataSheet.php`, `app/Exports/FinancingPeriodPetunjukSheet.php`, `app/Exports/FinancingPeriodExport.php`, `resources/views/livewire/data-pembiayaan/financing-period-index.blade.php`
- Next: tidak ada blocker.

## [2026-09-15] Perbaikan Spacing & Kontras View PD/LGD

- Status: Done
- Modul: UI/UX — seluruh view PD (Netflow, Migration, Pivot) & LGD (ER, CS, Final) + tab container
- Ref PRD: Bab 7–11 (tampilan hasil)
- Perubahan:
  - Standarisasi spacing: header `mb-6` + sub-teks `mt-1`, panel kontrol `p-5` dengan `gap-4`, judul panel `mb-4`, label field `mb-1 text-zinc-400`.
  - Samakan warna label field (sebagian `text-zinc-300` → `text-zinc-400`) dan border panel `zinc-700` → `zinc-800` agar konsisten.
  - Kontras WCAG 2.1 AA: badge status (Completed/Failed/Processing/Approved) pakai `border + bg-*-950/40 + text-*-300`; angka LGD/shortfall `text-red-700` → `text-rose-400`; recovery `text-emerald-700` → `text-emerald-400`; header Migration Matrix `bg-blue-700` → `bg-blue-950/60`.
  - Backdrop modal PD Netflow & Pivot disamakan ke `bg-black/60 backdrop-blur-sm`; tombol hapus modal `bg-rose-600`.
  - Input pencarian hasil PD Netflow diselaraskan ke `bg-zinc-950 + placeholder-zinc-500`.
  - Container tab PD/LGD: nav `flex-wrap gap-x-2 gap-y-1` agar tab tidak overflow/menempel di layar sempit.
- File utama: `resources/views/livewire/kalkulasi/{probabilitas-default-index,loss-given-default-index}.blade.php`, `resources/views/livewire/pd-netflow/*`, `resources/views/livewire/pd-migration/*`, `resources/views/livewire/lgd/*`, `resources/views/livewire/reporting/lgd-summary-index.blade.php`
- Verifikasi: `php artisan view:clear && php artisan view:cache` (semua Blade terkompilasi, exit 0).
- Next: tidak ada blocker.
